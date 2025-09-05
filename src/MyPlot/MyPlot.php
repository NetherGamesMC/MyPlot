<?php
declare(strict_types=1);

namespace MyPlot;

use Closure;
use MyPlot\command\BaseCommand;
use MyPlot\database\MyPlotDatabase;
use MyPlot\events\MyPlotClearEvent;
use MyPlot\events\MyPlotDisposeEvent;
use MyPlot\events\MyPlotFillEvent;
use MyPlot\events\MyPlotGenerationEvent;
use MyPlot\events\MyPlotResetEvent;
use MyPlot\events\MyPlotSettingEvent;
use MyPlot\events\MyPlotTeleportEvent;
use MyPlot\provider\ConfigDataProvider;
use MyPlot\provider\DataProvider;
use MyPlot\provider\MySQLProvider;
use MyPlot\provider\SQLiteDataProvider;
use MyPlot\task\CleanEntitiesTask;
use MyPlot\task\ClearPlotTask;
use MyPlot\task\FillPlotTask;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\lang\Language;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\permission\PermissionAttachmentInfo;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\biome\Biome;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use function abs;
use function array_filter;
use function class_exists;
use function count;
use function is_numeric;
use function str_starts_with;
use function strlen;
use function substr;
use const PHP_INT_MAX;

class MyPlot extends PluginBase
{

    private static ?MyPlot $instance;
    public array $stopTime = [];
    public array $bannedItems = [
        -BlockTypeIds::TNT,
        ItemTypeIds::SQUID_SPAWN_EGG,
        ItemTypeIds::VILLAGER_SPAWN_EGG,
        ItemTypeIds::ZOMBIE_SPAWN_EGG,
        ItemTypeIds::POTION,
        ItemTypeIds::LINGERING_POTION,
        ItemTypeIds::SPLASH_POTION,
    ];
    private NGEssentials $ess;
    private DataProvider $dataProvider;
    private Language $Language;
    private Commands $commands;

    // in PM5, item ID for a block is negative
    /** @var PlotLevelSettings[] $worlds */
    private array $worlds = [];

    private MyPlotDatabase $database;

    /**
     * Returns the Multi-lang management class
     *
     * @return Language
     * @api
     *
     */
    public function getLanguage(): Language
    {
        return $this->Language;
    }

    /**
     * Returns the fallback language class
     *
     * @return Language
     * @internal
     *
     */
    public function getFallBackLang(): Language
    {
        return new Language(Language::FALLBACK_LANGUAGE, $this->getFile() . "resources/");
    }

    /**
     * Generate a new plot world with optional settings
     *
     * @param string $levelName
     * @param string $generator
     * @param mixed[] $settings
     *
     * @return bool
     * @api
     *
     */
    public function generateWorld(string $levelName, string $generator = MyPlotGenerator::NAME, array $settings = []): bool
    {
        $ev = new MyPlotGenerationEvent($levelName, $generator, $settings);
        $ev->call();
        if ($ev->isCancelled() or $this->getServer()->getWorldManager()->isWorldGenerated($levelName)) {
            return false;
        }
        $generator = GeneratorManager::getInstance()->getGenerator($generator);
        if (count($settings) === 0) {
            $this->getConfig()->reload();
            $settings = $this->getConfig()->get("DefaultWorld", []);
        }
        $default = array_filter((array)$this->getConfig()->get("DefaultWorld", []), function ($key): bool {
            return !in_array($key, ["PlotSize", "GroundHeight", "RoadWidth", "RoadBlock", "WallBlock", "PlotFloorBlock", "PlotFillBlock", "BottomBlock"], true);
        }, ARRAY_FILTER_USE_KEY);
        new Config($this->getDataFolder() . "worlds" . DIRECTORY_SEPARATOR . $levelName . ".yml", Config::YAML, $default);
        $return = $this->getServer()->getWorldManager()->generateWorld($levelName, WorldCreationOptions::create()->setGeneratorClass($generator->getGeneratorClass())->setGeneratorOptions(json_encode($settings)), true);
        $level = $this->getServer()->getWorldManager()->getWorldByName($levelName);
        $level?->setSpawnLocation(new Vector3(0, $this->getConfig()->getNested("DefaultWorld.GroundHeight", 64) + 1, 0));
        return $return;
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    /**
     * Get all the plots a player owns (in a certain world if $worldName is provided)
     *
     * @param string $username
     * @param string $worldName
     *
     * @return Plot[]
     * @api
     *
     */
    public function getPlotsOfPlayer(string $username, string $worldName): array
    {
        return $this->dataProvider->getPlotsByOwner($username, $worldName);
    }

    /**
     * Get the next free plot in a world
     *
     * @param string $worldName
     * @param int $limitXZ
     *
     * @return Plot|null
     * @api
     *
     */
    public function getNextFreePlot(string $worldName, int $limitXZ = 0): ?Plot
    {
        return $this->dataProvider->getNextFreePlot($worldName, $limitXZ);
    }

    /**
     * Detects if the given position is bordering a plot
     *
     * @param Position $position
     *
     * @return bool
     * @api
     *
     */
    public function isPositionBorderingPlot(Position $position): bool
    {
        if (!$position->isValid()) {
            return false;
        }
        for ($i = Facing::NORTH; $i <= Facing::EAST; ++$i) {
            $pos = $position->getSide($i);
            $x = $pos->getFloorX();
            $z = $pos->getFloorZ();
            $levelName = $pos->getWorld()->getFolderName();

            if (!$this->isLevelLoaded($levelName)) {
                return false;
            }

            $plotLevel = $this->getLevelSettings($levelName);
            $plotSize = $plotLevel->plotSize;
            $roadWidth = $plotLevel->roadWidth;
            $totalSize = $plotSize + $roadWidth;
            if ($x >= 0) {
                $difX = $x % $totalSize;
            } else {
                $difX = abs(($x - $plotSize + 1) % $totalSize);
            }
            if ($z >= 0) {
                $difZ = $z % $totalSize;
            } else {
                $difZ = abs(($z - $plotSize + 1) % $totalSize);
            }
            if (($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
                continue;
            }
            return true;
        }
        for ($i = Facing::NORTH; $i <= Facing::EAST; ++$i) {
            for ($n = Facing::NORTH; $n <= Facing::EAST; ++$n) {
                if ($i === $n or Facing::opposite($i) === $n) {
                    continue;
                }
                $pos = $position->getSide($i)->getSide($n);
                $x = $pos->getFloorX();
                $z = $pos->getFloorZ();
                $levelName = $pos->getWorld()->getFolderName();

                $plotLevel = $this->getLevelSettings($levelName);
                $plotSize = $plotLevel->plotSize;
                $roadWidth = $plotLevel->roadWidth;
                $totalSize = $plotSize + $roadWidth;
                if ($x >= 0) {
                    $difX = $x % $totalSize;
                } else {
                    $difX = abs(($x - $plotSize + 1) % $totalSize);
                }
                if ($z >= 0) {
                    $difZ = $z % $totalSize;
                } else {
                    $difZ = abs(($z - $plotSize + 1) % $totalSize);
                }
                if (($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a plot world is loaded
     *
     * @param string $worldName
     *
     * @return bool
     * @api
     *
     */
    public function isLevelLoaded(string $worldName): bool
    {
        return isset($this->worlds[$worldName]);
    }

    /**
     * Returns a PlotLevelSettings object which contains all the settings of a world
     *
     * @param string $worldName
     *
     * @return PlotLevelSettings
     * @api
     *
     */

    public function getLevelSettings(string $worldName): PlotLevelSettings
    {
        if (!isset($this->worlds[$worldName])) {
            throw new AssumptionFailedError("Provided level name is not a MyPlot level or is not loaded");
        }
        return $this->worlds[$worldName];
    }

    /**
     * Retrieves the plot adjacent to teh given position
     *
     * @param Position $position
     *
     * @return Plot|null
     * @api
     *
     */
    public function getPlotBorderingPosition(Position $position): ?Plot
    {
        if (!$position->isValid()) {
            return null;
        }
        for ($i = Facing::NORTH; $i <= Facing::EAST; ++$i) {
            $pos = $position->getSide($i);
            $x = $pos->getFloorX();
            $z = $pos->getFloorZ();
            $levelName = $pos->getWorld()->getFolderName();

            if (!$this->isLevelLoaded($levelName)) {
                return null;
            }

            $plotLevel = $this->getLevelSettings($levelName);
            $plotSize = $plotLevel->plotSize;
            $roadWidth = $plotLevel->roadWidth;
            $totalSize = $plotSize + $roadWidth;
            if ($x >= 0) {
                $X = (int)floor($x / $totalSize);
                $difX = $x % $totalSize;
            } else {
                $X = (int)ceil(($x - $plotSize + 1) / $totalSize);
                $difX = abs(($x - $plotSize + 1) % $totalSize);
            }
            if ($z >= 0) {
                $Z = (int)floor($z / $totalSize);
                $difZ = $z % $totalSize;
            } else {
                $Z = (int)ceil(($z - $plotSize + 1) / $totalSize);
                $difZ = abs(($z - $plotSize + 1) % $totalSize);
            }
            if (($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
                if ($this->getPlotByPosition($pos) instanceof Plot) {
                    return $this->getPlotByPosition($pos);
                }
                continue;
            }
            return $this->dataProvider->getPlot($levelName, $X, $Z);
        }
        return null;
    }

    /**
     * Finds the plot at a certain position or null if there is no plot at that position
     *
     * @param Position $position
     *
     * @return Plot|null
     * @api
     *
     */
    public function getPlotByPosition(Position $position): ?Plot
    {
        $x = $position->x;
        $z = $position->z;

        $levelName = $position->getWorld()->getFolderName();
        if (!$this->isLevelLoaded($levelName)) {
            return null;
        }
        $plotLevel = $this->getLevelSettings($levelName);

        $plot = $this->getPlotFast($x, $z, $plotLevel);
        if ($plot instanceof Plot) {
            return $plot;
        }

        return null;
    }

    /**
     * @param float $x
     * @param float $z
     * @param PlotLevelSettings $plotLevel
     *
     * @return Plot|null
     */
    private function getPlotFast(float &$x, float &$z, PlotLevelSettings $plotLevel): ?Plot
    {
        $plotSize = $plotLevel->plotSize;
        $roadWidth = $plotLevel->roadWidth;

        $totalSize = $plotSize + $roadWidth;
        if ($x >= 0) {
            $difX = floor($x) % $totalSize;
            $x = (int)floor($x / $totalSize);
        } else {
            $difX = abs((floor($x) - $plotSize + 1) % $totalSize);
            $x = (int)ceil(($x - $plotSize + 1) / $totalSize);
        }
        if ($z >= 0) {
            $difZ = floor($z) % $totalSize;
            $z = (int)floor($z / $totalSize);
        } else {
            $difZ = abs((floor($z) - $plotSize + 1) % $totalSize);
            $z = (int)ceil(($z - $plotSize + 1) / $totalSize);
        }

        if (($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
            return null;
        }

        return $this->dataProvider->getPlot($plotLevel->name, $x, $z);
    }

    /**
     * Returns the DataProvider that is being used
     *
     * @return DataProvider
     * @api
     *
     */
    public function getProvider(): DataProvider
    {
        return $this->dataProvider;
    }

    /**
     * Claims a plot in a players name
     *
     * @param Plot $plot
     * @param string $claimer
     * @param string $plotName
     *
     * @return bool
     * @api
     *
     */
    public function claimPlot(Plot $plot, string $claimer, string $plotName = ""): bool
    {
        $newPlot = clone $plot;
        $newPlot->owner = $claimer;
        $ev = new MyPlotSettingEvent($plot, $newPlot);
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        $plot = $ev->getPlot();
        if ($plotName !== "") {
            $this->renamePlot($plot, $plotName);
        }
        if (!$this->savePlot($plot)) {
            return false;
        }
        return true;
    }

    /**
     * Renames a plot
     *
     * @param Plot $plot
     * @param string $newName
     *
     * @return bool
     * @api
     *
     */
    public function renamePlot(Plot $plot, string $newName = ""): bool
    {
        $newPlot = clone $plot;
        $newPlot->name = $newName;
        $ev = new MyPlotSettingEvent($plot, $newPlot);
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        return $this->savePlot($ev->getPlot());
    }

    /**
     * Saves provided plot if changed
     *
     * @param Plot $plot
     *
     * @return bool
     * @api
     *
     */
    public function savePlot(Plot $plot): bool
    {
        return $this->dataProvider->savePlot($plot);
    }

    /**
     * Fills the whole plot with a block
     *
     * @param Plot $plot
     * @param Block $plotFillBlock
     * @param int $maxBlocksPerTick
     *
     * @return bool
     * @api
     *
     */
    public function fillPlot(Plot $plot, Block $plotFillBlock, int $maxBlocksPerTick = 256): bool
    {
        $ev = new MyPlotFillEvent($plot, $maxBlocksPerTick);
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        $plot = $ev->getPlot();
        if (!$this->isLevelLoaded($plot->levelName)) {
            return false;
        }
        $maxBlocksPerTick = $ev->getMaxBlocksPerTick();
        foreach ($this->getServer()->getWorldManager()->getWorldByName($plot->levelName)->getEntities() as $entity) {
            if ($this->getPlotBB($plot)->isVectorInXZ($entity->getPosition()) && $entity->getPosition()->y <= $this->getLevelSettings($plot->levelName)->groundHeight) {
                if (!$entity instanceof Player) {
                    $entity->flagForDespawn();
                } else {
                    $this->teleportPlayerToPlot($entity, $plot);
                }
            }
        }
        $this->getScheduler()->scheduleTask(new FillPlotTask($this, $plot, $plotFillBlock, $maxBlocksPerTick));
        return true;
    }

    /**
     * Returns the AABB of the plot area
     *
     * @param Plot $plot
     *
     * @return AxisAlignedBB
     * @api
     *
     */
    public function getPlotBB(Plot $plot): AxisAlignedBB
    {
        $plotWorld = $this->getLevelSettings($plot->levelName);
        $plotSize = $plotWorld->plotSize - 1;
        $pos = $this->getPlotPosition($plot);
        $xMax = (int)($pos->x + $plotSize);
        $zMax = (int)($pos->z + $plotSize);

        return new AxisAlignedBB(
            min($pos->x, $xMax),
            0,
            min($pos->z, $zMax),
            max($pos->x, $xMax),
            $pos->getWorld()->getMaxY(),
            max($pos->z, $zMax)
        );
    }

    /**
     * Get the beginning position of a plot
     *
     * @param Plot $plot
     *
     * @return Position
     * @api
     */
    public function getPlotPosition(Plot $plot): Position
    {
        $plotLevel = $this->getLevelSettings($plot->levelName);
        $plotSize = $plotLevel->plotSize;
        $roadWidth = $plotLevel->roadWidth;
        $totalSize = $plotSize + $roadWidth;
        $x = $totalSize * $plot->X;
        $z = $totalSize * $plot->Z;
        $level = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
        return new Position($x, $plotLevel->groundHeight, $z, $level);
    }

    /**
     * Teleport a player to a plot
     *
     * @param Player $player
     * @param Plot $plot
     * @param bool $center
     * @param Closure|null $onSuccess
     * @param Closure|null $onFailure
     * @api
     *
     */
    public function teleportPlayerToPlot(Player $player, Plot $plot, bool $center = false, ?Closure $onSuccess = null, ?Closure $onFailure = null): void
    {
        $ev = new MyPlotTeleportEvent($plot, $player, $center);
        $ev->call();
        if ($ev->isCancelled()) {
            if ($onFailure !== null) $onFailure();
            return;
        }
        if ($center) {
            $this->teleportMiddle($player, $plot, $onSuccess, $onFailure);
            return;
        }
        $plotWorld = $this->getLevelSettings($plot->levelName);
        $pos = $this->getPlotPosition($plot);
        $pos->x += floor($plotWorld->plotSize / 2);
        $pos->y += 1.5;
        $pos->z -= 1;
        $world = Server::getInstance()->getWorldManager()->getWorldByName($plot->levelName);
        if ($world->getOrLoadChunkAtPosition($pos) === null) {
            $world->orderChunkPopulation($pos->getFloorX() >> Chunk::COORD_BIT_SIZE, $pos->getFloorZ() >> Chunk::COORD_BIT_SIZE, null);
        }
        $this->teleport($player, $pos, $onSuccess, $onFailure);
    }

    /**
     * Teleports the player to the exact center of the plot at nearest open space to the ground world
     *
     * @param Plot $plot
     * @param Player $player
     * @param Closure|null $onSuccess
     * @param Closure|null $onFailure
     * @internal
     *
     */
    private function teleportMiddle(Player $player, Plot $plot, ?Closure $onSuccess = null, ?Closure $onFailure = null): void
    {
        $mid = $this->getPlotMid($plot);
        if ($mid === null) {
            if ($onFailure !== null) $onFailure();
            return;
        }
        $this->teleport($player, $mid, $onSuccess, $onFailure);
    }


    /**
     * Finds the exact center of the plot at ground world
     *
     * @param Plot $plot
     *
     * @return Position|null
     * @api
     *
     */
    public function getPlotMid(Plot $plot): ?Position
    {
        if (!$this->isLevelLoaded($plot->levelName)) {
            return null;
        }
        $plotLevel = $this->getLevelSettings($plot->levelName);
        $plotSize = $plotLevel->plotSize;
        $pos = $this->getPlotPosition($plot);
        return new Position($pos->x + ($plotSize / 2), $pos->y + 1, $pos->z + ($plotSize / 2), $pos->getWorld());
    }

    /**
     * Workaround for PM4 teleport crashes
     *
     * @param Player $player
     * @param Position $pos
     * @param Closure|null $onSuccess
     * @param Closure|null $onFailure
     * @internal
     *
     */
    private function teleport(Player $player, Position $pos, ?Closure $onSuccess = null, ?Closure $onFailure = null): void
    {
        $world = $pos->getWorld();
        $world->orderChunkPopulation($pos->getFloorX() >> Chunk::COORD_BIT_SIZE, $pos->getFloorZ() >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
            function () use ($player, $pos, $onSuccess, $onFailure): void {
                if ($player->teleport($pos)) {
                    if ($onSuccess !== null) $onSuccess();
                } else {
                    if ($onFailure !== null) $onFailure();
                }
            },
            function () use ($onFailure): void {
                if ($onFailure !== null) $onFailure();
            }
        );
    }

    /**
     * Clear and dispose a plot
     *
     * @param Plot $plot
     * @param int $maxBlocksPerTick
     *
     * @return bool
     * @api
     *
     */
    public function resetPlot(Plot $plot, int $maxBlocksPerTick = 256): bool
    {
        $ev = new MyPlotResetEvent($plot);
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        if ($this->disposePlot($plot)) {
            return $this->clearPlot($plot, $maxBlocksPerTick);
        }
        return false;
    }

    /**
     * Delete the plot data
     *
     * @param Plot $plot
     *
     * @return bool
     * @api
     *
     */
    public function disposePlot(Plot $plot): bool
    {
        $ev = new MyPlotDisposeEvent($plot);
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        return $this->getProvider()->deletePlot($plot);
    }

    /**
     * Reset all the blocks inside a plot
     *
     * @param Plot $plot
     * @param int $maxBlocksPerTick
     *
     * @return bool
     * @api
     *
     */
    public function clearPlot(Plot $plot, int $maxBlocksPerTick = 256): bool
    {
        $ev = new MyPlotClearEvent($plot, $maxBlocksPerTick);
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        $plot = $ev->getPlot();
        if (!$this->isLevelLoaded($plot->levelName)) {
            return false;
        }
        $maxBlocksPerTick = $ev->getMaxBlocksPerTick();
        $level = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
        if ($level === null) {
            return false;
        }
        foreach ($level->getEntities() as $entity) {
            if ($this->getPlotBB($plot)->isVectorInXZ($entity->getPosition())) {
                if (!$entity instanceof Player) {
                    $entity->flagForDespawn();
                } else {
                    $this->teleportPlayerToPlot($entity, $plot);
                }
            }
        }
        $this->getScheduler()->scheduleTask(new ClearPlotTask($this, $plot, $maxBlocksPerTick));
        return true;
    }

    /**
     * Changes the biome of a plot
     *
     * @param Plot $plot
     * @param Biome $biome
     *
     * @return bool
     * @api
     *
     */
    public function setPlotBiome(Plot $plot, Biome $biome): bool
    {
        $newPlot = clone $plot;
        $newPlot->biome = str_replace(" ", "_", strtoupper($biome->getName()));
        $ev = new MyPlotSettingEvent($plot, $newPlot);
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        $plot = $ev->getPlot();
        if (defined(BiomeIds::class . "::" . $plot->biome) and is_int(constant(BiomeIds::class . "::" . $plot->biome))) {
            $biome = constant(BiomeIds::class . "::" . $plot->biome);
        } else {
            $biome = BiomeIds::PLAINS;
        }
        $biome = BiomeRegistry::getInstance()->getBiome($biome);
        if (!$this->isLevelLoaded($plot->levelName)) {
            return false;
        }
        $failed = false;
        if (!$this->savePlot($plot)) {
            $failed = true;
        }
        $plotLevel = $this->getLevelSettings($plot->levelName);
        $level = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
        if ($level === null) {
            return false;
        }
        foreach ($this->getPlotChunks($plot) as [$chunkX, $chunkZ, $chunk]) {
            for ($x = 0; $x < 16; ++$x) {
                for ($z = 0; $z < 16; ++$z) {
                    $chunkPlot = $this->getPlotByPosition(new Position(($chunkX << Chunk::COORD_BIT_SIZE) + $x, $plotLevel->groundHeight, ($chunkZ << Chunk::COORD_BIT_SIZE) + $z, $level));
                    if ($chunkPlot instanceof Plot and $chunkPlot->isSame($plot)) {
                        for ($y = World::Y_MIN; $y < World::Y_MAX; $y++) {
                            $chunk->setBiomeId($x, $y, $z, $biome->getId());
                        }
                    }
                }
            }
            $level->setChunk($chunkX, $chunkZ, $chunk);
        }
        return !$failed;
    }

    /**
     * Returns the Chunks contained in a plot
     *
     * @param Plot $plot
     *
     * @return array<array<int|Chunk>>
     * @api
     *
     */
    public function getPlotChunks(Plot $plot): array
    {
        if (!$this->isLevelLoaded($plot->levelName)) {
            return [];
        }

        $plotLevel = $this->getLevelSettings($plot->levelName);
        $level = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
        if ($level === null) {
            return [];
        }

        $plotSize = $plotLevel->plotSize;
        $chunks = [];

        $pos = $this->getPlotPosition($plot);
        $xMax = ($pos->x + $plotSize) >> 4;
        $zMax = ($pos->z + $plotSize) >> 4;
        for ($x = $pos->x >> 4; $x <= $xMax; $x++) {
            for ($z = $pos->z >> 4; $z <= $zMax; $z++) {
                $chunks[] = [$x, $z, $level->isChunkLoaded($x, $z) ? $level->getChunk($x, $z) : $level->loadChunk($x, $z)];
            }
        }

        return $chunks;
    }

    public function setPlotPvp(Plot $plot, bool $pvp): bool
    {
        $newPlot = clone $plot;
        $newPlot->pvp = $pvp;
        $ev = new MyPlotSettingEvent($plot, $newPlot);
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        return $this->savePlot($ev->getPlot());
    }

    public function addPlotHelper(Plot $plot, string $player): bool
    {
        $newPlot = clone $plot;
        $ev = new MyPlotSettingEvent($plot, $newPlot);
        $newPlot->addHelper($player) ? $ev->uncancel() : $ev->cancel();
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        return $this->savePlot($ev->getPlot());
    }

    public function removePlotHelper(Plot $plot, string $player): bool
    {
        $newPlot = clone $plot;
        $ev = new MyPlotSettingEvent($plot, $newPlot);
        $newPlot->removeHelper($player) ? $ev->uncancel() : $ev->cancel();
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        return $this->savePlot($ev->getPlot());
    }

    public function addPlotDenied(Plot $plot, string $player): bool
    {
        $newPlot = clone $plot;
        $ev = new MyPlotSettingEvent($plot, $newPlot);
        $newPlot->banPlayer($player) ? $ev->uncancel() : $ev->cancel();
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        return $this->savePlot($ev->getPlot());
    }

    public function removePlotDenied(Plot $plot, string $player): bool
    {
        $newPlot = clone $plot;
        $ev = new MyPlotSettingEvent($plot, $newPlot);
        $newPlot->unBanPlayer($player) ? $ev->uncancel() : $ev->cancel();
        $ev->call();
        if ($ev->isCancelled()) {
            return false;
        }
        return $this->savePlot($ev->getPlot());
    }

    /**
     * Returns the PlotLevelSettings of all the loaded worlds
     *
     * @return PlotLevelSettings[]
     * @api
     *
     */
    public function getPlotLevels(): array
    {
        return $this->worlds;
    }

    /**
     * Get the maximum number of plots a player can claim
     *
     * @param Player $player
     *
     * @return int
     * @api
     *
     */
    public function getMaxPlotsOfPlayer(Player $player): int
    {
        $levelName = $player->getWorld()->getFolderName();
        $length = strlen($levelName);

        if ($player->hasPermission("myplot.claimplots.$levelName.unlimited")) {
            return PHP_INT_MAX;
        }

        $playerPermissions = array_filter($player->getEffectivePermissions(), static fn(PermissionAttachmentInfo $attachment) => $attachment->getValue() && str_starts_with($attachment->getPermission(), "myplot.claimplots.$levelName."));
        $perms = array_map(static fn(PermissionAttachmentInfo $attachment) => $attachment->getPermission(), $playerPermissions);
        if (count($perms) === 0) {
            return 0;
        }

        rsort($perms, SORT_FLAG_CASE | SORT_NATURAL);

        $maxPlots = substr($perms[0], 19 + $length);
        if (is_numeric($maxPlots)) {
            return (int)$maxPlots;
        }

        return 0;
    }

    public function getDatabase(): MyPlotDatabase
    {
        return $this->database;
    }

    public function getEssentials(): NGEssentials
    {
        return $this->ess;
    }

    public function getCommands(): Commands
    {
        return $this->commands;
    }

    public function addLevelSettings(string $worldName, PlotLevelSettings $settings): bool
    {
        $this->worlds[$worldName] = $settings;
        return true;
    }

    public function unloadLevelSettings(string $worldName): bool
    {
        if (isset($this->worlds[$worldName])) {
            unset($this->worlds[$worldName]);
            $this->getLogger()->debug("World " . $worldName . " settings unloaded!");
            return true;
        }
        return false;
    }

    protected function onLoad(): void
    {
        $this->getLogger()->debug(TF::BOLD . "Loading...");
        self::$instance = $this;
        $this->getLogger()->debug(TF::BOLD . "Loading Configs");
        $this->reloadConfig();
        @mkdir($this->getDataFolder() . "worlds");
        $this->getLogger()->debug(TF::BOLD . "Loading MyPlot Generator");
        GeneratorManager::getInstance()->addGenerator(MyPlotGenerator::class, "myplot", fn() => null, true);
        $this->getLogger()->debug(TF::BOLD . "Loading Languages");
        // Loading Languages
        /** @var string $lang */
        $lang = $this->getConfig()->get("Language", Language::FALLBACK_LANGUAGE);
        if ((bool)$this->getConfig()->get("CustomMessages", false)) {
            if (!file_exists($this->getDataFolder() . "lang.ini")) {
                /** @var string|resource $resource */
                $resource = $this->getResource($lang . ".ini") ?? file_get_contents($this->getFile() . "resources/" . Language::FALLBACK_LANGUAGE . ".ini");
                file_put_contents($this->getDataFolder() . "lang.ini", $resource);
                if (is_resource($resource)) {
                    fclose($resource);
                }
                $this->saveResource(Language::FALLBACK_LANGUAGE . ".ini", true);
                $this->getLogger()->debug("Custom Language ini created");
            }
            $this->Language = new Language("lang", $this->getDataFolder());
        } else {
            if (file_exists($this->getDataFolder() . "lang.ini")) {
                unlink($this->getDataFolder() . "lang.ini");
                unlink($this->getDataFolder() . Language::FALLBACK_LANGUAGE . ".ini");
                $this->getLogger()->debug("Custom Language ini deleted");
            }
            $this->Language = new Language($lang, $this->getFile() . "resources/");
        }
        $this->getLogger()->debug(TF::BOLD . "Loading Data Provider settings");
        // Initialize DataProvider
        /** @var int $cacheSize */
        $cacheSize = $this->getConfig()->get("PlotCacheSize", 256);
        $dataProvider = $this->getConfig()->get("DataProvider", "sqlite3");
        if (!is_string($dataProvider)) {
            $this->dataProvider = new ConfigDataProvider($this, $cacheSize);
        } else {
            try {
                switch (strtolower($dataProvider)) {
                    case "mysqli":
                    case "mysql":
                        if (extension_loaded("mysqli")) {
                            $settings = (array)$this->getConfig()->get("MySQLSettings");
                            $this->dataProvider = new MySQLProvider($this, $cacheSize, $settings);
                        } else {
                            $this->getLogger()->warning("MySQLi is not installed in your php build! JSON will be used instead.");
                            $this->dataProvider = new ConfigDataProvider($this, $cacheSize);
                        }
                        break;
                    case "yaml":
                        if (extension_loaded("yaml")) {
                            $this->dataProvider = new ConfigDataProvider($this, $cacheSize, true);
                        } else {
                            $this->getLogger()->warning("YAML is not installed in your php build! JSON will be used instead.");
                            $this->dataProvider = new ConfigDataProvider($this, $cacheSize);
                        }
                        break;
                    case "sqlite3":
                    case "sqlite":
                        if (extension_loaded("sqlite3")) {
                            $this->dataProvider = new SQLiteDataProvider($this, $cacheSize);
                        } else {
                            $this->getLogger()->warning("SQLite3 is not installed in your php build! JSON will be used instead.");
                            $this->dataProvider = new ConfigDataProvider($this, $cacheSize);
                        }
                        break;
                    case "json":
                    default:
                        $this->dataProvider = new ConfigDataProvider($this, $cacheSize);
                        break;
                }
            } catch (\Exception) {
                $this->getLogger()->error("The selected data provider crashed. JSON will be used instead.");
                $this->dataProvider = new ConfigDataProvider($this, $cacheSize);
            }
        }

        $this->database = new MyPlotDatabase($this, $cacheSize);
        $this->database->init();
    }

    protected function onEnable(): void
    {
        $ess = $this->getServer()->getPluginManager()->getPlugin('NGEssentials');
        if(!$ess instanceof NGEssentials) {
            $this->getServer()->getPluginManager()->disablePlugin($this);
            $this->getServer()->shutdown();
            self::$instance = null;
            return;
        }

        if($this->isDisabled()) {
            return;
        }

        $this->ess = $ess;

        foreach (["Creative", "MEGA", "Platinum", "p1"] as $world) {
            $this->getServer()->getWorldManager()->loadWorld($world, true);
        }

        $this->getScheduler()->scheduleRepeatingTask(new CleanEntitiesTask($this), 20 * 60 * 5);

        $this->getLogger()->debug(TF::BOLD . "Loading MyPlot Commands");
        $this->commands = new Commands($this);
        $this->getServer()->getCommandMap()->register("myplot", $this->commands);

        if (self::essentialsExists()) {
            BaseCommand::registerCommands($this);
        }

        $this->getLogger()->debug(TF::BOLD . "Loading Events");
        $eventListener = new EventListener($this);
        $this->getServer()->getPluginManager()->registerEvents($eventListener, $this);

        $this->getLogger()->debug(TF::BOLD . "Registering Loaded Worlds");
        foreach ($this->getServer()->getWorldManager()->getWorlds() as $level) {
            $eventListener->onLevelLoad(new WorldLoadEvent($level));
        }

        $this->database->postInit();
        $this->getLogger()->debug(TF::BOLD . TF::GREEN . "Enabled!");
    }

    public static function essentialsExists(): bool
    {
        return class_exists("NetherGames\NGEssentials\NGEssentials");
    }

    protected function onDisable(): void
    {
        $this->dataProvider->close();

        $worldManager = $this->getServer()->getWorldManager();
        foreach (["Creative", "MEGA", "Platinum"] as $world) {
            if ($worldManager->getWorldByName($world) !== null) {
                $worldManager->getWorldByName($world)->save(true);
            } else {
                $this->getLogger()->error("World: " . $world . " not found!");
            }
        }

        self::$instance = null;
    }
}
