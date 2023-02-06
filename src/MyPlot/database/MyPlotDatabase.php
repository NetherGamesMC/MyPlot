<?php
declare(strict_types=1);

namespace MyPlot\database;

use Exception;
use Generator;
use MyPlot\MyPlot;
use MyPlot\Plot;
use MyPlot\utils\Utils;
use pocketmine\math\Facing;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use SOFe\AwaitGenerator\Await;
use function count;
use function max;
use function strcmp;
use function usort;

final class MyPlotDatabase extends AwaitDatabase
{
    private static string $type = self::TYPE_SQLITE;

    public function __construct(MyPlot $plugin, int $cacheSize = 0)
    {
        $config = $plugin->getConfig();

        self::$type = (string)$config->getNested("database.type", "sqlite");

        $connector = libasynql::create(
            $plugin,
            $config->get("database"),
            [
                // "mysql" => "stmts/mysql.sql",
                "sqlite" => "sqlite.sql"
            ]
        );

        parent::__construct($plugin, $connector, $cacheSize);
    }

    /**
     * @internal
     */
    public function init(): void
    {
        Await::f2c(
            function () {
                // create tables
                yield $this->asyncGeneric(QueryIds::INIT_PLOTS_V2);
                yield $this->asyncGeneric(QueryIds::INIT_MERGED_PLOTS_V2);

                if (self::$type === self::TYPE_SQLITE) {
                    // plotsV2
                    $res = yield $this->asyncRawSelect("SELECT count(name) FROM sqlite_Master WHERE type='table' AND name='plots';");
                    // copy records over to the new table
                    if ((int)$res[0]["count(name)"] > 0) {
                        yield $this->asyncRawInsert("INSERT OR IGNORE INTO plotsV2 (level, X, Z, name, owner, helpers, denied, biome, pvp, price) SELECT level, X, Z, name, owner, helpers, denied, biome, pvp, price FROM plots;");
                    }

                    // mergedPlotsV2
                    $res = yield $this->asyncRawSelect("SELECT count(name) FROM sqlite_Master WHERE type='table' AND name='mergedPlots';");
                    // copy records over to the new table
                    if ((int)$res[0]["count(name)"] > 0) {
                        yield $this->asyncRawInsert("INSERT OR IGNORE INTO mergedPlotsV2 (level, originX, originZ, mergedX, mergedZ) SELECT r1.level, r1.X, r1.Z, r2.X, r2.Z FROM plots r1, mergedPlots JOIN plots r2 ON r1.id = mergedPlots.originId AND r2.id = mergedPlots.mergedId;");
                    }
                }
            }
        );

        $this->connector->waitAll();
        $this->plugin->getLogger()->debug("SQLite database initialized");
    }

    public function postInit(): void
    {
        /*
        $plot = $this->plugin->getProvider()->getPlot("p1", 1, 1);

        Await::f2c(
            function () {
                $this->getPlot("p1", 1, 1, yield);
                $plot = yield Await::ONCE;

            }
        );
        */
    }

    /**
     * @param callable|null $resolve - function(bool $success): void {}
     */
    public function savePlot(Plot $plot, callable $resolve = null): void
    {
        $helpers = implode(",", $plot->helpers);
        $denied = implode(",", $plot->banned);

        $this->connector->executeInsert(QueryIds::SAVE_PLOT, [
            "level" => $plot->levelName,
            "X" => $plot->X,
            "Z" => $plot->Z,
            "name" => $plot->name,
            "owner" => $plot->owner,
            "helpers" => $helpers,
            "denied" => $denied,
            "biome" => $plot->biome,
            "pvp" => $plot->pvp
        ], function () use ($plot, $resolve): void {
            $this->cachePlot($plot);
            if ($resolve !== null) $resolve(true);
        }, function () use ($resolve): void {
            if ($resolve !== null) $resolve(false);
        });
    }

    /**
     * @param callable|null $resolve - function(bool $success): void {}
     */
    public function deletePlot(Plot $plot, callable $resolve = null): void
    {
        Await::f2c(function () use ($plot, $resolve): Generator {
            $args = [];

            if ($plot->isMerged()) {
                $this->getMergeOrigin($plot, yield);
                /** @var Plot $plot */
                $plot = yield Await::ONCE;
                $settings = $this->plugin->getLevelSettings($plot->levelName);
                $args["pvp"] = !$settings->restrictPVP;
                $stmt = QueryIds::DISPOSE_MERGED_PLOT;
            } else {
                $stmt = QueryIds::REMOVE_PLOT;
            }

            $args["level"] = $plot->levelName;
            $args["X"] = $plot->X;
            $args["Z"] = $plot->Z;

            $result = yield $this->asyncChange($stmt, $args);
            if ($result instanceof SqlError) {
                if ($resolve !== null) $resolve(false);
                return;
            }

            $this->cachePlot($plot);
            if ($resolve !== null) $resolve($result > 0);
        });
    }

    /**
     * @param callable $resolve - function(Plot $plot): void {}
     */
    public function getMergeOrigin(Plot $plot, callable $resolve): void
    {
        $this->connector->executeSelect(QueryIds::GET_MERGE_ORIGIN, ["level" => $plot->levelName, "mergedX" => $plot->X, "mergedZ" => $plot->Z],
            function (array $rows) use ($plot, $resolve): void {
                if (count($rows) === 0) {
                    $resolve($plot);
                    return;
                }

                $val = $rows[0];
                $helpers = explode(",", (string)$val["helpers"]);
                $denied = explode(",", (string)$val["denied"]);
                $pvp = is_numeric($val["pvp"]) ? (bool)$val["pvp"] : null;
                $plot = new Plot((string)$val["level"], (int)$val["X"], (int)$val["Z"], (string)$val["name"], (string)$val["owner"], $helpers, $denied, (string)$val["biome"], $pvp);
                $resolve($plot);
            }
        );
    }

    /**
     * @param callable $resolve - function(Plot $plot): void {}
     */
    public function getPlot(string $levelName, int $X, int $Z, callable $resolve): void
    {
        if (($plot = $this->getPlotFromCache($levelName, $X, $Z)) !== null) {
            $resolve($plot);
            return;
        }

        $this->connector->executeSelect(QueryIds::GET_PLOT, ["level" => $levelName, "X" => $X, "Z" => $Z],
            function (array $rows) use ($levelName, $X, $Z, $resolve): void {
                if (count($rows) === 0) {
                    $plot = new Plot($levelName, $X, $Z);
                    $this->cachePlot($plot);
                    $resolve($plot);
                    return;
                }

                $val = $rows[0];

                if ($val["helpers"] === null or $val["helpers"] === "") $helpers = [];
                else $helpers = explode(",", (string)$val["helpers"]);

                if ($val["denied"] === null or $val["denied"] === "") $denied = [];
                else $denied = explode(",", (string)$val["denied"]);

                $pvp = is_numeric($val["pvp"]) ? (bool)$val["pvp"] : null;

                $plot = new Plot($levelName, $X, $Z, (string)$val["name"], (string)$val["owner"], $helpers, $denied, (string)$val["biome"], $pvp);
                $this->cachePlot($plot);

                $resolve($plot);
            }
        );
    }

    /**
     * @param callable $resolve - function(array<Plot> $plots): void {}
     */
    public function getPlotsByOwner(string $owner, ?string $levelName, callable $resolve): void
    {
        $stmt = $levelName === "" || $levelName === null ? QueryIds::GET_PLOTS_BY_OWNER : QueryIds::GET_PLOTS_BY_OWNER_AND_LEVEL;
        $args = $levelName === "" || $levelName === null ? ["owner" => $owner] : ["owner" => $owner, "level" => $levelName];

        $this->connector->executeSelect($stmt, $args,
            function (array $rows) use ($owner, $levelName, $resolve): void {
                /** @var Plot[] $plots */
                $plots = [];

                foreach ($rows as $val) {
                    $helpers = explode(",", (string)$val["helpers"]);
                    $denied = explode(",", (string)$val["denied"]);
                    $pvp = is_numeric($val["pvp"]) ? (bool)$val["pvp"] : null;

                    if (!$this->plugin->isLevelLoaded((string)$val["level"])) {
                        $this->plugin->getLogger()->error("World: '" . (string)$val["level"] . "' is not a MyPlot world or is not loaded");
                        continue;
                    }

                    $plots[] = new Plot((string)$val["level"], (int)$val["X"], (int)$val["Z"], (string)$val["name"], (string)$val["owner"], $helpers, $denied, (string)$val["biome"], $pvp);
                }

                // Remove unloaded plots
                $plots = array_filter($plots, function (Plot $plot): bool {
                    return $this->plugin->isLevelLoaded($plot->levelName);
                });

                // Sort plots by level
                usort($plots, function (Plot $plot1, Plot $plot2): int {
                    return strcmp($plot1->levelName, $plot2->levelName);
                });

                $resolve($plots);
            }
        );
    }

    /**
     * @param callable $resolve - function(Plot $plot): void {}
     */
    public function getNextFreePlot(string $levelName, int $limitXZ, callable $resolve): void
    {
        Await::f2c(function () use ($levelName, $limitXZ, $resolve): Generator {
            for ($i = 0; $limitXZ <= 0 or $i < $limitXZ; $i++) {
                $result = yield $this->asyncSelect(QueryIds::GET_EXISTING_XZ, ["level" => $levelName, "number" => $i]);
                $plots = [];

                foreach ($result as $val) {
                    $plots[$val["X"]][$val["Z"]] = true;
                }

                if (count($plots) === max(1, 8 * $i)) {
                    continue;
                }

                if (($ret = Utils::findEmptyPlotSquared(0, $i, $plots)) !== null) {
                    [$X, $Z] = $ret;
                    $plot = new Plot($levelName, $X, $Z);
                    $this->cachePlot($plot);
                    $resolve($plot);
                    return;
                }

                for ($a = 1; $a < $i; $a++) {
                    if (($ret = Utils::findEmptyPlotSquared($a, $i, $plots)) !== null) {
                        [$X, $Z] = $ret;
                        $plot = new Plot($levelName, $X, $Z);
                        $this->cachePlot($plot);
                        $resolve($plot);
                        return;
                    }
                }

                if (($ret = Utils::findEmptyPlotSquared($i, $i, $plots)) !== null) {
                    [$X, $Z] = $ret;
                    $plot = new Plot($levelName, $X, $Z);
                    $this->cachePlot($plot);
                    $resolve($plot);
                    return;
                }

                $resolve(null);
            }
        });
    }

    /**
     * @param callable $resolve - function(bool $success): void {}
     */
    public function mergePlots(callable $resolve, Plot $base, Plot ...$plots): void
    {
        Await::f2c(
            function () use ($base, $plots, $resolve): Generator {
                try {
                    foreach ($plots as $plot) {
                        yield $this->asyncInsert(QueryIds::MERGE_PLOT, [
                            "level" => $base->levelName,
                            "originX" => $base->X,
                            "originZ" => $base->Z,
                            "mergedX" => $plot->X,
                            "mergedZ" => $plot->Z
                        ]);
                    }
                    $resolve(true);
                } catch (Exception $ex) {
                    $this->plugin->getLogger()->logException($ex);
                    $resolve(false);
                }
            }
        );
    }

    /**
     * @param callable $resolve - function(array<Plot> $plots): void {}
     */
    public function getMergedPlots(Plot $plot, bool $adjacent, callable $resolve): void
    {
        Await::f2c(function () use ($plot, $adjacent, $resolve): Generator {
            $this->getMergeOrigin($plot, yield);
            /** @var Plot $origin */
            $origin = yield Await::ONCE;

            $plots = [$origin];
            $rows = yield $this->asyncSelect(QueryIds::GET_MERGED_PLOTS, ["level" => $origin->levelName, "originX" => $origin->X, "originZ" => $origin->Z]);

            foreach ($rows as $val) {
                $helpers = explode(",", (string)$val["helpers"]);
                $denied = explode(",", (string)$val["denied"]);
                $pvp = is_numeric($val["pvp"]) ? (bool)$val["pvp"] : null;
                $plots[] = new Plot((string)$val["level"], (int)$val["X"], (int)$val["Z"], (string)$val["name"], (string)$val["owner"], $helpers, $denied, (string)$val["biome"], $pvp);
            }

            if ($adjacent) {
                $plots = array_filter($plots, function (Plot $val) use ($plot): bool {
                    for ($i = Facing::NORTH; $i <= Facing::EAST; ++$i) {
                        if ($plot->getSide($i)->isSame($val)) {
                            return true;
                        }
                    }
                    return false;
                });
            }

            $resolve($plots);
        });
    }
}