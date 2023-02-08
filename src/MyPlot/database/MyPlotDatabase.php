<?php
declare(strict_types=1);

namespace MyPlot\database;

use Generator;
use MyPlot\MyPlot;
use MyPlot\Plot;
use MyPlot\utils\Utils;
use poggit\libasynql\libasynql;
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
                yield from $this->asyncGeneric(QueryIds::INIT_PLOTS_V2);
                yield from $this->asyncGeneric(QueryIds::INIT_MERGED_PLOTS_V2);

                if (self::$type === self::TYPE_SQLITE) {
                    // plotsV2
                    $oldTableExists = false;
                    $res = yield from $this->asyncRawSelect("SELECT count(name) FROM sqlite_Master WHERE type='table' AND name='plots';");
                    // copy records over to the new table
                    if ((int)$res[0]["count(name)"] > 0) {
                        yield $this->asyncRawInsert("INSERT OR IGNORE INTO plotsV2 (level, X, Z, name, owner, helpers, denied, biome, pvp, price) SELECT level, X, Z, name, owner, helpers, denied, biome, pvp, price FROM plots;");
                        $oldTableExists = true;
                    }

                    // mergedPlotsV2
                    $res = yield from $this->asyncRawSelect("SELECT count(name) FROM sqlite_Master WHERE type='table' AND name='mergedPlots';");
                    // copy records over to the new table
                    if ((int)$res[0]["count(name)"] > 0) {
                        yield from $this->asyncRawInsert("INSERT OR IGNORE INTO mergedPlotsV2 (level, originX, originZ, mergedX, mergedZ) SELECT r1.level, r1.X, r1.Z, r2.X, r2.Z FROM plots r1, mergedPlots JOIN plots r2 ON r1.id = mergedPlots.originId AND r2.id = mergedPlots.mergedId;");
                        yield from $this->asyncRawChange("DROP TABLE mergedPlots;");
                    }

                    if ($oldTableExists) {
                        yield from $this->asyncRawChange("DROP TABLE plots;");
                        $this->plugin->getLogger()->debug("Old tables removed");
                    }
                }
            }
        );

        $this->connector->waitAll();
        $this->plugin->getLogger()->debug("SQLite database initialized");
    }

    /**
     * @internal
     */
    public function postInit(): void
    {
        /*
        $plot = $this->plugin->getProvider()->getNextFreePlot("p1", 0);
        var_dump($plot);

        Await::f2c(
            function () {
                $plot = yield from $this->getNextFreePlot("p1", 0);
                var_dump($plot);
            }
        );
        */
    }

    /**
     * @return Generator<bool>
     */
    public function savePlot(Plot $plot): Generator
    {
        $helpers = implode(",", $plot->helpers);
        $denied = implode(",", $plot->banned);

        [, $affectedRows] = yield from $this->asyncInsert(QueryIds::SAVE_PLOT, [
            "level" => $plot->levelName,
            "X" => $plot->X,
            "Z" => $plot->Z,
            "name" => $plot->name,
            "owner" => $plot->owner,
            "helpers" => $helpers,
            "denied" => $denied,
            "biome" => $plot->biome,
            "pvp" => $plot->pvp
        ]);

        if ($affectedRows < 1) {
            return false;
        }

        $this->cachePlot($plot);
        return true;
    }

    /**
     * @return Generator<bool>
     */
    public function deletePlot(Plot $plot): Generator
    {
        $affectedRows = yield from $this->asyncChange(QueryIds::REMOVE_PLOT, ["level" => $plot->levelName, "X" => $plot->X, "Z" => $plot->Z]);

        if ($affectedRows < 1) {
            return false;
        }

        $this->cachePlot($plot);
        return true;
    }

    /**
     * @return Generator<Plot>
     */
    public function getPlot(string $levelName, int $X, int $Z): Generator
    {
        if (($plot = $this->getPlotFromCache($levelName, $X, $Z)) !== null) {
            return $plot;
        }

        $rows = yield from $this->asyncSelect(QueryIds::GET_PLOT, ["level" => $levelName, "X" => $X, "Z" => $Z]);

        if (count($rows) === 0) {
            $plot = new Plot($levelName, $X, $Z);
            $this->cachePlot($plot);
            return $plot;
        }

        $val = $rows[0];


        $helpers = ($val["helpers"] === null or $val["helpers"] === "") ? [] : explode(",", (string)$val["helpers"]);
        $denied = ($val["denied"] === null or $val["denied"] === "") ? [] : explode(",", (string)$val["denied"]);
        $pvp = is_numeric($val["pvp"]) ? (bool)$val["pvp"] : null;

        $plot = new Plot($levelName, $X, $Z, (string)$val["name"], (string)$val["owner"], $helpers, $denied, (string)$val["biome"], $pvp);
        $this->cachePlot($plot);

        return $plot;
    }

    /**
     * @return Generator<array<Plot>>
     */
    public function getPlotsByOwner(string $owner, ?string $levelName = ""): Generator
    {
        $stmt = $levelName === "" || $levelName === null ? QueryIds::GET_PLOTS_BY_OWNER : QueryIds::GET_PLOTS_BY_OWNER_AND_LEVEL;
        $args = $levelName === "" || $levelName === null ? ["owner" => $owner] : ["owner" => $owner, "level" => $levelName];

        $rows = yield from $this->asyncSelect($stmt, $args);

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

        return $plots;
    }

    /**
     * @return Generator<Plot|null>
     */
    public function getNextFreePlot(string $levelName, int $limitXZ = 0): Generator
    {
        for ($i = 0; $limitXZ <= 0 or $i < $limitXZ; $i++) {
            $result = yield from $this->asyncSelect(QueryIds::GET_EXISTING_XZ, ["level" => $levelName, "number" => $i]);
            $plots = [];

            foreach ($result as $val) {
                $plots[$val["X"]][$val["Z"]] = true;
            }

            if (count($plots) === max(1, 8 * $i)) {
                continue;
            }


            for ($a = 0; $a <= $i; $a++) {
                if (($ret = Utils::findEmptyPlotSquared($a, $i, $plots)) !== null) {
                    [$X, $Z] = $ret;
                    $plot = new Plot($levelName, $X, $Z);
                    $this->cachePlot($plot);
                    return $plot;
                }

            }
        }

        return null;
    }
}