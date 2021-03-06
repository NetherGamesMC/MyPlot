<?php
declare(strict_types=1);

namespace MyPlot;

use muqsit\worldstyler\shapes\CommonShape;
use muqsit\worldstyler\shapes\Cuboid;
use muqsit\worldstyler\WorldStyler;
use MyPlot\command\BaseCommand;
use MyPlot\events\MyPlotClearEvent;
use MyPlot\events\MyPlotCloneEvent;
use MyPlot\events\MyPlotDisposeEvent;
use MyPlot\events\MyPlotGenerationEvent;
use MyPlot\events\MyPlotResetEvent;
use MyPlot\events\MyPlotSettingEvent;
use MyPlot\events\MyPlotTeleportEvent;
use MyPlot\provider\DataProvider;
use MyPlot\provider\EconomyProvider;
use MyPlot\provider\EconomySProvider;
use MyPlot\provider\JSONDataProvider;
use MyPlot\provider\MySQLProvider;
use MyPlot\provider\SQLiteDataProvider;
use MyPlot\provider\YAMLDataProvider;
use MyPlot\task\ClearBorderTask;
use MyPlot\task\ClearPlotTask;
use NetherGames\NGEssentials\NGEssentials;
use onebone\economyapi\EconomyAPI;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\item\ItemIds;
use pocketmine\lang\Language;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\permission\Permission;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\biome\Biome;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\Position;
use pocketmine\world\World;
use function count;
use function strlen;
use const PHP_INT_MAX;

class MyPlot extends PluginBase{
	/** @var MyPlot|null $instance */
	private static $instance = null;

	/** @var NGEssentials */
	private $ess;
	/** @var PlotLevelSettings[] $worlds */
	private $worlds = [];
	/** @var DataProvider $dataProvider */
	private $dataProvider = null;
	/** @var EconomyProvider $economyProvider */
	private $economyProvider = null;
	/** @var Language $baseLang */
	private $baseLang = null;
	/** @var Commands */
	private $commands;

	/** @var array */
	public $stopTime = [];
	/** @var array */
	public $bannedItems = [
		ItemIds::TNT,
		ItemIds::SPAWN_EGG,
		ItemIds::POTION,
		ItemIds::LINGERING_POTION,
		ItemIds::SPLASH_POTION,
	];

	/**
	 * @return MyPlot|null
	 */
	public static function getInstance() : ?self {
		return self::$instance;
	}

	/**
	 * Returns the Multi-lang management class
	 *
	 * @api
	 *
	 * @return Language
	 */
	public function getLanguage() : Language {
		return $this->baseLang;
	}

	/**
	 * Returns the fallback language class
	 *
	 * @internal
	 *
	 * @return Language
	 */
	public function getFallBackLang() : Language {
		return new Language(Language::FALLBACK_LANGUAGE, $this->getFile() . "resources/");
	}

	/**
	 * Returns the DataProvider that is being used
	 *
	 * @api
	 *
	 * @return DataProvider
	 */
	public function getProvider() : DataProvider {
		return $this->dataProvider;
	}

	/**
	 * Returns the EconomyProvider that is being used
	 *
	 * @api
	 *
	 * @return EconomyProvider|null
	 */
	public function getEconomyProvider() : ?EconomyProvider {
		return $this->economyProvider;
	}

	/**
	 * Allows setting the economy provider to a custom provider or to null to disable economy mode
	 *
	 * @api
	 *
	 * @param EconomyProvider|null $provider
	 */
	public function setEconomyProvider(?EconomyProvider $provider) : void {
		if($provider === null) {
			$this->getConfig()->set("UseEconomy", false);
			$this->getLogger()->info("Economy mode disabled!");
		}else{
			$this->getLogger()->info("A custom economy provider has been registered. Economy mode now enabled!");
			$this->getConfig()->set("UseEconomy", true);
			$this->economyProvider = $provider;
		}
	}

	/**
	 * Returns a PlotLevelSettings object which contains all the settings of a world
	 *
	 * @api
	 *
	 * @param string $worldName
	 *
	 * @return PlotLevelSettings|null
	 */
	public function getLevelSettings(string $worldName) : ?PlotLevelSettings {
		return $this->worlds[$worldName] ?? null;
	}

	/**
	 * Checks if a plot world is loaded
	 *
	 * @api
	 *
	 * @param string $worldName
	 *
	 * @return bool
	 */
	public function isLevelLoaded(string $worldName) : bool {
		return isset($this->worlds[$worldName]);
	}

	/**
	 * Generate a new plot world with optional settings
	 *
	 * @api
	 *
	 * @param string $worldName
	 * @param string $generator
	 * @param array $settings
	 *
	 * @return bool
	 */
	public function generateWorld(string $worldName, string $generator = "myplot", array $settings = []) : bool {
		$ev = new MyPlotGenerationEvent($worldName, $generator, $settings);
		$ev->call();
		$worldManager = $this->getServer()->getWorldManager();
		if($ev->isCancelled() or $worldManager->isWorldGenerated($worldName)) {
			return false;
		}
		$generator = GeneratorManager::getInstance()->getGenerator($generator, false);
		if(empty($settings)) {
			$this->getConfig()->reload();
			$settings = $this->getConfig()->get("DefaultWorld", []);
		}
		$default = array_filter($this->getConfig()->get("DefaultWorld", []), function($key) {
			return !in_array($key, ["PlotSize", "GroundHeight", "RoadWidth", "RoadBlock", "WallBlock", "PlotFloorBlock", "PlotFillBlock", "BottomBlock"]);
		}, ARRAY_FILTER_USE_KEY);
		new Config($this->getDataFolder() . "worlds" . DIRECTORY_SEPARATOR . $worldName . ".yml", Config::YAML, $default);
		$settings = ["preset" => json_encode($settings)];
		$return = $worldManager->generateWorld($worldName, null, $generator, $settings);
		$world = $worldManager->getWorldByName($worldName);
		if($world !== null)
			$world->setSpawnLocation(new Vector3(0, (int)$this->getConfig()->getNested("DefaultWorld.GroundHeight", 64) + 1, 0));
		return $return;
	}

	/**
	 * Saves provided plot if changed
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return bool
	 */
	public function savePlot(Plot $plot) : bool {
		return $this->dataProvider->savePlot($plot);
	}

	/**
	 * Get all the plots a player owns (in a certain world if $worldName is provided)
	 *
	 * @api
	 *
	 * @param string $username
	 * @param string $worldName
	 *
	 * @return Plot[]
	 */
	public function getPlotsOfPlayer(string $username, string $worldName) : array {
		return $this->dataProvider->getPlotsByOwner($username, $worldName);
	}

	/**
	 * Get the next free plot in a world
	 *
	 * @api
	 *
	 * @param string $worldName
	 * @param int $limitXZ
	 *
	 * @return Plot|null
	 */
	public function getNextFreePlot(string $worldName, int $limitXZ = 0) : ?Plot {
		return $this->dataProvider->getNextFreePlot($worldName, $limitXZ);
	}

	/**
	 * Finds the plot at a certain position or null if there is no plot at that position
	 *
	 * @api
	 *
	 * @param Position $position
	 *
	 * @return Plot|null
	 */
	public function getPlotByPosition(Position $position) : ?Plot {
		$x = $position->x;
		$z = $position->z;
		$worldName = $position->world->getFolderName();

		$plotWorld = $this->getLevelSettings($worldName);
		if($plotWorld === null)
			return null;
		$plotSize = $plotWorld->plotSize;
		$roadWidth = $plotWorld->roadWidth;
		$totalSize = $plotSize + $roadWidth;
		if($x >= 0) {
			$X = (int)floor($x / $totalSize);
			$difX = $x % $totalSize;
		}else{
			$X = (int)ceil(($x - $plotSize + 1) / $totalSize);
			$difX = abs(($x - $plotSize + 1) % $totalSize);
		}
		if($z >= 0) {
			$Z = (int)floor($z / $totalSize);
			$difZ = $z % $totalSize;
		}else{
			$Z = (int)ceil(($z - $plotSize + 1) / $totalSize);
			$difZ = abs(($z - $plotSize + 1) % $totalSize);
		}
		if(($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
			return null;
		}
		return $this->dataProvider->getPlot($worldName, $X, $Z);
	}

	/**
	 * Get the begin position of a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return Position|null
	 */
	public function getPlotPosition(Plot $plot) : ?Position {
		$plotWorld = $this->getLevelSettings($plot->levelName);
		if($plotWorld === null)
			return null;
		$plotSize = $plotWorld->plotSize;
		$roadWidth = $plotWorld->roadWidth;
		$totalSize = $plotSize + $roadWidth;
		$x = $totalSize * $plot->X;
		$z = $totalSize * $plot->Z;
		$world = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
		return new Position($x, $plotWorld->groundHeight, $z, $world);
	}

	/**
	 * Detects if the given position is bordering a plot
	 *
	 * @api
	 *
	 * @param Position $position
	 *
	 * @return bool
	 */
	public function isPositionBorderingPlot(Position $position) : bool {
		if(!$position->isValid())
			return false;
		for($i = Facing::NORTH; $i <= Facing::EAST; ++$i){
			$pos = $position->getSide($i);
			$x = $pos->x;
			$z = $pos->z;
			$levelName = $pos->getWorld()->getFolderName();

			$plotLevel = $this->getLevelSettings($levelName);
			if($plotLevel === null)
				return false;
			$plotSize = $plotLevel->plotSize;
			$roadWidth = $plotLevel->roadWidth;
			$totalSize = $plotSize + $roadWidth;
			if($x >= 0) {
				$X = (int)floor($x / $totalSize);
				$difX = $x % $totalSize;
			}else{
				$X = (int)ceil(($x - $plotSize + 1) / $totalSize);
				$difX = abs(($x - $plotSize + 1) % $totalSize);
			}
			if($z >= 0) {
				$Z = (int)floor($z / $totalSize);
				$difZ = $z % $totalSize;
			}else{
				$Z = (int)ceil(($z - $plotSize + 1) / $totalSize);
				$difZ = abs(($z - $plotSize + 1) % $totalSize);
			}
			if(($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
				continue;
			}
			$plot = $this->dataProvider->getPlot($levelName, $X, $Z);
			if($plot !== null)
				return true;
		}
		for($i = Facing::NORTH; $i <= Facing::EAST; ++$i){
			for($n = Facing::NORTH; $n <= Facing::EAST; ++$n){
				if($i === $n or Facing::opposite($i) === $n)
					continue;
				$pos = $position->getSide($i)->getSide($n);
				$x = $pos->x;
				$z = $pos->z;
				$levelName = $pos->getWorld()->getFolderName();

				$plotLevel = $this->getLevelSettings($levelName);
				if($plotLevel === null)
					return false;
				$plotSize = $plotLevel->plotSize;
				$roadWidth = $plotLevel->roadWidth;
				$totalSize = $plotSize + $roadWidth;
				if($x >= 0) {
					$X = (int)floor($x / $totalSize);
					$difX = $x % $totalSize;
				}else{
					$X = (int)ceil(($x - $plotSize + 1) / $totalSize);
					$difX = abs(($x - $plotSize + 1) % $totalSize);
				}
				if($z >= 0) {
					$Z = (int)floor($z / $totalSize);
					$difZ = $z % $totalSize;
				}else{
					$Z = (int)ceil(($z - $plotSize + 1) / $totalSize);
					$difZ = abs(($z - $plotSize + 1) % $totalSize);
				}
				if(($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
					continue;
				}
				$plot = $this->dataProvider->getPlot($levelName, $X, $Z);
				if($plot !== null)
					return true;
			}
		}
		return false;
	}

	/**
	 * Retrieves the plot adjacent to teh given position
	 *
	 * @api
	 *
	 * @param Position $position
	 *
	 * @return Plot|null
	 */
	public function getPlotBorderingPosition(Position $position) : ?Plot {
		if(!$position->isValid())
			return null;
		for($i = Facing::NORTH; $i <= Facing::EAST; ++$i){
			$pos = $position->getSide($i);
			$x = $pos->x;
			$z = $pos->z;
			$levelName = $pos->getWorld()->getFolderName();

			$plotLevel = $this->getLevelSettings($levelName);
			if($plotLevel === null)
				return null;
			$plotSize = $plotLevel->plotSize;
			$roadWidth = $plotLevel->roadWidth;
			$totalSize = $plotSize + $roadWidth;
			if($x >= 0) {
				$X = (int)floor($x / $totalSize);
				$difX = $x % $totalSize;
			}else{
				$X = (int)ceil(($x - $plotSize + 1) / $totalSize);
				$difX = abs(($x - $plotSize + 1) % $totalSize);
			}
			if($z >= 0) {
				$Z = (int)floor($z / $totalSize);
				$difZ = $z % $totalSize;
			}else{
				$Z = (int)ceil(($z - $plotSize + 1) / $totalSize);
				$difZ = abs(($z - $plotSize + 1) % $totalSize);
			}
			if(($difX > $plotSize - 1) or ($difZ > $plotSize - 1)) {
				continue;
			}
			$plot = $this->dataProvider->getPlot($levelName, $X, $Z);
			if($plot !== null)
				return $plot;
		}
		return null;
	}

	/**
	 * Returns the AABB of the plot area
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return AxisAlignedBB|null
	 */
	public function getPlotBB(Plot $plot) : ?AxisAlignedBB {
		$plotWorld = $this->getLevelSettings($plot->levelName);
		if($plotWorld === null)
			return null;
		$pos = $this->getPlotPosition($plot);
		$plotSize = $plotWorld->plotSize - 1;

		return new AxisAlignedBB(
			min($pos->x, $pos->x + $plotSize),
			0,
			min($pos->z, $pos->z + $plotSize),
			max($pos->x, $pos->x + $plotSize),
			$pos->getWorld()->getWorldHeight(),
			max($pos->z, $pos->z + $plotSize)
		);
	}

	/**
	 * Teleport a player to a plot
	 *
	 * @api
	 *
	 * @param Player $player
	 * @param Plot $plot
	 * @param bool $center
	 *
	 * @return bool
	 */
	public function teleportPlayerToPlot(Player $player, Plot $plot, bool $center = false) : bool {
		$ev = new MyPlotTeleportEvent($plot, $player, $center);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		if($center)
			return $this->teleportMiddle($player, $plot);
		$plotWorld = $this->getLevelSettings($plot->levelName);
		if($plotWorld === null)
			return false;
		$pos = $this->getPlotPosition($plot);
		$pos->x += floor($plotWorld->plotSize / 2);
		$pos->y += 1.5;
		$pos->z -= 1;
		return $player->teleport($pos);
	}

	/**
	 * Claims a plot in a players name
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param string $claimer
	 * @param string $plotName
	 *
	 * @return bool
	 */
	public function claimPlot(Plot $plot, string $claimer, string $plotName = "") : bool {
		$newPlot = clone $plot;
		$newPlot->owner = $claimer;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$plot = $ev->getPlot();
		if(!empty($plotName)) {
			$this->renamePlot($plot, $plotName);
		}
		return $this->savePlot($plot);
	}

	/**
	 * Renames a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param string $newName
	 *
	 * @return bool
	 */
	public function renamePlot(Plot $plot, string $newName = "") : bool {
		$newPlot = clone $plot;
		$newPlot->name = $newName;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	/**
	 * Clones a plot to another location
	 *
	 * @api
	 *
	 * @param Plot $plotFrom
	 * @param Plot $plotTo
	 *
	 * @return bool
	 */
	public function clonePlot(Plot $plotFrom, Plot $plotTo) : bool {
		$styler = $this->getServer()->getPluginManager()->getPlugin("WorldStyler");
		if(!$styler instanceof WorldStyler) {
			return false;
		}
		foreach($this->getPlotChunks($plotTo) as $chunk){
			foreach($chunk->getEntities() as $entity){
				if($this->getPlotBB($plotTo)->isVectorInXZ($entity->getPosition())) {
					if($entity instanceof Player) {
						$this->teleportPlayerToPlot($entity, $plotTo);
					}
				}
			}
		}
		$ev = new MyPlotCloneEvent($plotFrom, $plotTo);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$plotFrom = $ev->getPlot();
		$plotTo = $ev->getClonePlot();
		if(!$this->isLevelLoaded($plotFrom->levelName) or !$this->isLevelLoaded($plotTo->levelName)) {
			return false;
		}
		$plotLevel = $this->getLevelSettings($plotFrom->levelName);
		$plotSize = $plotLevel->plotSize - 1;
		$plotBeginPos = $this->getPlotPosition($plotFrom);
		$level = $plotBeginPos->getWorld();
		$plotBeginPos = $plotBeginPos->subtract(1, 0, 1);
		$plotBeginPos->y = 0;
		$plugin = $this;
		$selection = $styler->getSelection(99997);
		$selection->setPosition(1, $plotBeginPos);
		$vec2 = new Vector3($plotBeginPos->x + $plotSize + 1, $level->getWorldHeight() - 1, $plotBeginPos->z + $plotSize + 1);
		$selection->setPosition(2, $vec2);
		$cuboid = Cuboid::fromSelection($selection);
		//$cuboid = $cuboid->async(); // do not use async because WorldStyler async is very broken right now
		$cuboid->copy($level, $vec2, function(float $time, int $changed) use ($plugin) : void {
			$plugin->getLogger()->debug(TF::GREEN . 'Copied ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's to the MyPlot clipboard.');
		});

		$plotLevel = $this->getLevelSettings($plotTo->levelName);
		$plotSize = $plotLevel->plotSize - 1;
		$plotBeginPos = $this->getPlotPosition($plotTo);
		$level = $plotBeginPos->getWorld();
		$plotBeginPos = $plotBeginPos->subtract(1, 0, 1);
		$plotBeginPos->y = 0;
		$selection->setPosition(1, $plotBeginPos);
		$vec2 = new Vector3($plotBeginPos->x + $plotSize + 1, $level->getWorldHeight() - 1, $plotBeginPos->z + $plotSize + 1);
		$selection->setPosition(2, $vec2);
		$commonShape = CommonShape::fromSelection($selection);
		//$commonShape = $commonShape->async(); // do not use async because WorldStyler async is very broken right now
		$commonShape->paste($level, $vec2, true, function(float $time, int $changed) use ($plugin) : void {
			$plugin->getLogger()->debug(TF::GREEN . 'Pasted ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's from the MyPlot clipboard.');
		});
		$styler->removeSelection(99997);
		foreach($this->getPlotChunks($plotTo) as $chunkHash => $chunk){
		    World::getXZ($chunkHash, $chunkX, $chunkZ);
			$level->setChunk($chunkX, $chunkZ, $chunk, false);
		}
		return true;
	}

	/**
	 * Reset all the blocks inside a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param int $maxBlocksPerTick
	 *
	 * @return bool
	 */
	public function clearPlot(Plot $plot, int $maxBlocksPerTick = 256) : bool {
		$ev = new MyPlotClearEvent($plot, $maxBlocksPerTick);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$plot = $ev->getPlot();
		if(!$this->isLevelLoaded($plot->levelName)) {
			return false;
		}
		$maxBlocksPerTick = $ev->getMaxBlocksPerTick();
		foreach($this->getServer()->getWorldManager()->getWorldByName($plot->levelName)->getEntities() as $entity){
			if($this->getPlotBB($plot)->isVectorInXZ($entity->getPosition())) {
				if(!$entity instanceof Player) {
					$entity->flagForDespawn();
				}else{
					$this->teleportPlayerToPlot($entity, $plot);
				}
			}
		}
		if($this->getConfig()->get("FastClearing", false)) {
			$styler = $this->getServer()->getPluginManager()->getPlugin("WorldStyler");
			if(!$styler instanceof WorldStyler) {
				return false;
			}
			$plotWorld = $this->getLevelSettings($plot->levelName);
			$plotSize = $plotWorld->plotSize - 1;
			$plotBeginPos = $this->getPlotPosition($plot);
			$plugin = $this;
			// Above ground
			$selection = $styler->getSelection(99998);
			$plotBeginPos->y = $plotWorld->groundHeight + 1;
			$selection->setPosition(1, $plotBeginPos);
			$selection->setPosition(2, new Vector3($plotBeginPos->x + $plotSize, World::Y_MAX, $plotBeginPos->z + $plotSize));
			$cuboid = Cuboid::fromSelection($selection);
			//$cuboid = $cuboid->async();
			$cuboid->set($plotBeginPos->world, VanillaBlocks::AIR()->getFullId(), function(float $time, int $changed) use ($plugin) : void {
				$plugin->getLogger()->debug('Set ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's');
			});
			$styler->removeSelection(99998);
			// Ground Surface
			$selection = $styler->getSelection(99998);
			$plotBeginPos->y = $plotWorld->groundHeight;
			$selection->setPosition(1, $plotBeginPos);
			$selection->setPosition(2, new Vector3($plotBeginPos->x + $plotSize, $plotWorld->groundHeight, $plotBeginPos->z + $plotSize));
			$cuboid = Cuboid::fromSelection($selection);
			//$cuboid = $cuboid->async();
			$cuboid->set($plotBeginPos->world, $plotWorld->plotFloorBlock->getFullId(), function(float $time, int $changed) use ($plugin) : void {
				$plugin->getLogger()->debug('Set ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's');
			});
			$styler->removeSelection(99998);
			// Ground
			$selection = $styler->getSelection(99998);
			$plotBeginPos->y = 1;
			$selection->setPosition(1, $plotBeginPos);
			$selection->setPosition(2, new Vector3($plotBeginPos->x + $plotSize, $plotWorld->groundHeight - 1, $plotBeginPos->z + $plotSize));
			$cuboid = Cuboid::fromSelection($selection);
			//$cuboid = $cuboid->async();
			$cuboid->set($plotBeginPos->world, $plotWorld->plotFillBlock->getFullId(), function(float $time, int $changed) use ($plugin) : void {
				$plugin->getLogger()->debug('Set ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's');
			});
			$styler->removeSelection(99998);
			// Bottom of world
			$selection = $styler->getSelection(99998);
			$plotBeginPos->y = 0;
			$selection->setPosition(1, $plotBeginPos);
			$selection->setPosition(2, new Vector3($plotBeginPos->x + $plotSize, 0, $plotBeginPos->z + $plotSize));
			$cuboid = Cuboid::fromSelection($selection);
			//$cuboid = $cuboid->async();
			$cuboid->set($plotBeginPos->world, $plotWorld->bottomBlock->getFullId(), function(float $time, int $changed) use ($plugin) : void {
				$plugin->getLogger()->debug('Set ' . number_format($changed) . ' blocks in ' . number_format($time, 10) . 's');
			});
			$styler->removeSelection(99998);
            foreach($this->getPlotChunks($plot) as $chunkHash => $chunk){
                World::getXZ($chunkHash, $chunkX, $chunkZ);
                $plotBeginPos->world->setChunk($chunkX, $chunkZ, $chunk, false);
            }
			$this->getScheduler()->scheduleDelayedTask(new ClearBorderTask($this, $plot), 1);
			return true;
		}
		$this->getScheduler()->scheduleTask(new ClearPlotTask($this, $plot, $maxBlocksPerTick));
		return true;
	}

	/**
	 * Delete the plot data
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return bool
	 */
	public function disposePlot(Plot $plot) : bool {
		$ev = new MyPlotDisposeEvent($plot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->dataProvider->deletePlot($plot);
	}

	/**
	 * Clear and dispose a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param int $maxBlocksPerTick
	 *
	 * @return bool
	 */
	public function resetPlot(Plot $plot, int $maxBlocksPerTick = 256) : bool {
		$ev = new MyPlotResetEvent($plot);
		$ev->call();
		if($ev->isCancelled())
			return false;
		if($this->disposePlot($plot)) {
			return $this->clearPlot($plot, $maxBlocksPerTick);
		}
		return false;
	}

	/**
	 * Changes the biome of a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 * @param Biome $biome
	 *
	 * @return bool
	 */
	public function setPlotBiome(Plot $plot, Biome $biome) : bool {
		$newPlot = clone $plot;
		$newPlot->biome = str_replace(" ", "_", strtoupper($biome->getName()));
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		$plot = $ev->getPlot();
		$biome = BiomeRegistry::getInstance()->getBiome(defined(BiomeIds::class . "::" . $plot->biome) ? constant(BiomeIds::class . "::" . $plot->biome) : BiomeIds::PLAINS);
		$plotWorld = $this->getLevelSettings($plot->levelName);
		if($plotWorld === null) {
			return false;
		}
		$world = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
		$chunks = $this->getPlotChunks($plot);
		foreach($chunks as $chunkHash => $chunk){
		    World::getXZ($chunkHash, $chunkX, $chunkZ);
			for($x = 0; $x < 16; ++$x){
				for($z = 0; $z < 16; ++$z){
					$chunkPlot = $this->getPlotByPosition(new Position(($chunkX << 4) + $x, $plotWorld->groundHeight, ($chunkZ << 4) + $z, $world));
					if($chunkPlot instanceof Plot and $chunkPlot->isSame($plot)) {
						$chunk->setBiomeId($x, $z, $biome->getId());
					}
				}
			}
			$world->setChunk($chunkX, $chunkZ, $chunk, false);
		}
		return $this->savePlot($plot);
	}

	/**
	 * @param Plot $plot
	 * @param bool $pvp
	 *
	 * @return bool
	 */
	public function setPlotPvp(Plot $plot, bool $pvp) : bool {
		$newPlot = clone $plot;
		$newPlot->pvp = $pvp;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	/**
	 * @param Plot $plot
	 * @param string $player
	 *
	 * @return bool
	 */
	public function addPlotHelper(Plot $plot, string $player) : bool {
		$newPlot = clone $plot;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		if(!$newPlot->addHelper($player))
			$ev->cancel();
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	/**
	 * @param Plot $plot
	 * @param string $player
	 *
	 * @return bool
	 */
	public function removePlotHelper(Plot $plot, string $player) : bool {
		$newPlot = clone $plot;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		if(!$newPlot->removeHelper($player))
			$ev->cancel();
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	/**
	 * @param Plot $plot
	 * @param string $player
	 *
	 * @return bool
	 */
	public function addPlotDenied(Plot $plot, string $player) : bool {
		$newPlot = clone $plot;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		if(!$newPlot->banPlayer($player))
			$ev->cancel();
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	/**
	 * @param Plot $plot
	 * @param string $player
	 *
	 * @return bool
	 */
	public function removePlotDenied(Plot $plot, string $player) : bool {
		$newPlot = clone $plot;
		$ev = new MyPlotSettingEvent($plot, $newPlot);
		if(!$newPlot->unBanPlayer($player)) {
			$ev->cancel();
		}
		$ev->call();
		if($ev->isCancelled()) {
			return false;
		}
		return $this->savePlot($ev->getPlot());
	}

	/**
	 * Returns the PlotLevelSettings of all the loaded worlds
	 *
	 * @api
	 *
	 * @return PlotLevelSettings[]
	 */
	public function getPlotLevels() : array {
		return $this->worlds;
	}

	/**
	 * Returns the Chunks contained in a plot
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return Chunk[]
	 */
	public function getPlotChunks(Plot $plot) : array {
		$plotWorld = $this->getLevelSettings($plot->levelName);
		if($plotWorld === null) {
			return [];
		}
		$world = $this->getServer()->getWorldManager()->getWorldByName($plot->levelName);
		$pos = $this->getPlotPosition($plot);
		$plotSize = $plotWorld->plotSize;
		$xMax = ($pos->x + $plotSize) >> 4;
		$zMax = ($pos->z + $plotSize) >> 4;
		$chunks = [];
		for($X = $pos->x >> 4; $X <= $xMax; $X++){
			for($Z = $pos->z >> 4; $Z <= $zMax; $Z++){
				$chunks[World::chunkHash($X, $Z)] = $world->getOrLoadChunkAtPosition($pos);
			}
		}
		return $chunks;
	}

	/**
	 * Get the maximum number of plots a player can claim
	 *
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return int
	 */
	public function getMaxPlotsOfPlayer(Player $player) : int {
		$levelName = $player->getWorld()->getFolderName();
		$length = strlen($levelName);

		if($player->hasPermission("myplot.claimplots.$levelName.unlimited")) {
			return PHP_INT_MAX;
		}

        $perms = $player->getEffectivePermissions();
        $perms = array_filter($perms, function(string $name) use ($levelName, $length) {
			return (substr($name, 0, 19 + $length) === "myplot.claimplots.$levelName.");
		}, ARRAY_FILTER_USE_KEY);

		if(count($perms) === 0) {
		    return 0;
        }

		krsort($perms, SORT_FLAG_CASE | SORT_NATURAL);

		/**
		 * @var string $name
		 * @var Permission $perm
		 */
		foreach($perms as $name => $perm){
			$maxPlots = substr($name, 19 + $length);
			if(is_numeric($maxPlots)) {
				return (int)$maxPlots;
			}
		}

		return 0;
	}

	/**
	 * Finds the exact center of the plot at ground world
	 *
	 * @api
	 *
	 * @param Plot $plot
	 *
	 * @return Position|null
	 */
	public function getPlotMid(Plot $plot) : ?Position {
		$plotWorld = $this->getLevelSettings($plot->levelName);
		if($plotWorld === null) {
			return null;
		}
		$plotSize = $plotWorld->plotSize;
		$pos = $this->getPlotPosition($plot);
		$pos = new Position($pos->x + ($plotSize / 2), $pos->y + 1, $pos->z + ($plotSize / 2), $pos->getWorld());
		return $pos;
	}

	/**
	 * Teleports the player to the exact center of the plot at nearest open space to the ground world
	 *
	 * @internal
	 *
	 * @param Plot $plot
	 * @param Player $player
	 *
	 * @return bool
	 */
	private function teleportMiddle(Player $player, Plot $plot) : bool {
		$mid = $this->getPlotMid($plot);
		if($mid === null) {
			return false;
		}
		return $player->teleport($mid);
	}

	/* -------------------------- Non-API part -------------------------- */
	protected function onLoad() : void {
		$this->getLogger()->debug(TF::BOLD . "Loading...");
		self::$instance = $this;
		$this->getLogger()->debug(TF::BOLD . "Loading Configs");
		$this->reloadConfig();
		@mkdir($this->getDataFolder() . "worlds");
		$this->getLogger()->debug(TF::BOLD . "Loading MyPlot Generator");
		GeneratorManager::getInstance()->addGenerator(MyPlotGenerator::class, "myplot", true);
		$this->getLogger()->debug(TF::BOLD . "Loading Languages");
		// Loading Languages
		/** @var string $lang */
		$lang = $this->getConfig()->get("Language", Language::FALLBACK_LANGUAGE);
		if($this->getConfig()->get("Custom Messages", false)) {
			if(!file_exists($this->getDataFolder() . "lang.ini")) {
				/** @var string|resource $resource */
				$resource = $this->getResource($lang . ".ini") ?? file_get_contents($this->getFile() . "resources/" . Language::FALLBACK_LANGUAGE . ".ini");
				file_put_contents($this->getDataFolder() . "lang.ini", $resource);
				if(!is_string($resource)) {
					/** @var resource $resource */
					fclose($resource);
				}
				$this->saveResource(Language::FALLBACK_LANGUAGE . ".ini", true);
				$this->getLogger()->debug("Custom Language ini created");
			}
			$this->baseLang = new Language("lang", $this->getDataFolder());
		}else{
			if(file_exists($this->getDataFolder() . "lang.ini")) {
				unlink($this->getDataFolder() . "lang.ini");
				unlink($this->getDataFolder() . Language::FALLBACK_LANGUAGE . ".ini");
				$this->getLogger()->debug("Custom Language ini deleted");
			}
			$this->baseLang = new Language($lang, $this->getFile() . "resources/");
		}
		$this->getLogger()->debug(TF::BOLD . "Loading Data Provider settings");
		// Initialize DataProvider
		/** @var int $cacheSize */
		$cacheSize = $this->getConfig()->get("PlotCacheSize", 256);
		switch(strtolower($this->getConfig()->get("DataProvider", "sqlite3"))){
			case "mysqli":
			case "mysql":
				if(extension_loaded("mysqli")) {
					$settings = $this->getConfig()->get("MySQLSettings");
					$this->dataProvider = new MySQLProvider($this, $cacheSize, $settings);
				}else{
					$this->getLogger()->warning("MySQLi is not installed in your php build! JSON will be used instead.");
					$this->dataProvider = new JSONDataProvider($this, $cacheSize);
				}
				break;
			case "yaml":
				if(extension_loaded("yaml")) {
					$this->dataProvider = new YAMLDataProvider($this, $cacheSize);
				}else{
					$this->getLogger()->warning("YAML is not installed in your php build! JSON will be used instead.");
					$this->dataProvider = new JSONDataProvider($this, $cacheSize);
				}
				break;
			case "sqlite3":
			case "sqlite":
				if(extension_loaded("sqlite3")) {
					$this->dataProvider = new SQLiteDataProvider($this, $cacheSize);
				}else{
					$this->getLogger()->warning("SQLite3 is not installed in your php build! JSON will be used instead.");
					$this->dataProvider = new JSONDataProvider($this, $cacheSize);
				}
				break;
			case "json":
			default:
				$this->dataProvider = new JSONDataProvider($this, $cacheSize);
				break;
		}
		$this->getLogger()->debug(TF::BOLD . "Loading Plot Clearing settings");
		if($this->getConfig()->get("FastClearing", false) and $this->getServer()->getPluginManager()->getplugin("WorldStyler") === null) {
			$this->getConfig()->set("FastClearing", false);
			$this->getLogger()->info(TF::BOLD . "WorldStyler not found. Legacy clearing will be used.");
		}
	}

	protected function onEnable() : void {
		if(!($ess = $this->getServer()->getPluginManager()->getPlugin('NGEssentials')) instanceof NGEssentials) {
			$this->getServer()->getPluginManager()->disablePlugin($this);
			$this->getServer()->shutdown();
			self::$instance = null;
			return;
		}

		if($this->isDisabled()) {
			return;
		}

		$this->ess = $ess;

		foreach(["Creative", "MEGA", "Platinum"] as $world){
			$this->getServer()->getWorldManager()->loadWorld($world, true);
		}

		$this->getLogger()->debug(TF::BOLD . "Loading MyPlot Commands");
		$this->commands = new Commands($this);
		$this->getServer()->getCommandMap()->register("myplot", $this->commands);
		BaseCommand::registerCommands($this);

		$this->getLogger()->debug(TF::BOLD . "Loading economy settings");
		if($this->getConfig()->get("UseEconomy", false) === true) {
			if(($plugin = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI")) !== null) {
				/* @phpstan-ignore-next-line */
				if($plugin instanceof EconomyAPI) {
					/* @phpstan-ignore-next-line */
					$this->economyProvider = new EconomySProvider($plugin);
					$this->getLogger()->debug("Eco set to EconomySProvider");
				}else{
					$this->getLogger()->debug("Eco not instance of EconomyAPI");
				}
			}
			if(!isset($this->economyProvider)) {
				$this->getLogger()->info("No supported economy plugin found!");
				$this->getConfig()->set("UseEconomy", false);
				//$this->getConfig()->save();
			}
		}

		$this->getLogger()->debug(TF::BOLD . "Loading Events");
		$eventListener = new EventListener($this);
		$this->getServer()->getPluginManager()->registerEvents($eventListener, $this);

		$this->getLogger()->debug(TF::BOLD . "Registering Loaded Worlds");
		foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
			$eventListener->onLevelLoad(new WorldLoadEvent($world));
		}

		$this->getLogger()->debug(TF::BOLD . TF::GREEN . "Enabled!");
	}

	public function getEssentials() : NGEssentials {
		return $this->ess;
	}

	public function getCommands() : Commands {
		return $this->commands;
	}

	/**
	 * @param string $worldName
	 * @param PlotLevelSettings $settings
	 *
	 * @return bool
	 */
	public function addLevelSettings(string $worldName, PlotLevelSettings $settings) : bool {
		$this->worlds[$worldName] = $settings;
		return true;
	}

	/**
	 * @param string $worldName
	 *
	 * @return bool
	 */
	public function unloadLevelSettings(string $worldName) : bool {
		if(isset($this->worlds[$worldName])) {
			unset($this->worlds[$worldName]);
			$this->getLogger()->debug("World " . $worldName . " settings unloaded!");
			return true;
		}
		return false;
	}

	protected function onDisable() : void {
		if($this->dataProvider !== null) {
			$this->dataProvider->close();
		}

		$worldManager = $this->getServer()->getWorldManager();
		foreach(["Creative", "MEGA", "Platinum"] as $world){
		    if($worldManager->getWorldByName($world) !== null){
                $worldManager->getWorldByName($world)->save(true);
            }else{
		        $this->getLogger()->error("World: " . $world . " not found!");
            }
		}

		self::$instance = null;
	}
}
