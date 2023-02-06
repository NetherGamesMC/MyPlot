<?php
declare(strict_types=1);

namespace MyPlot\database;

use MyPlot\MyPlot;
use poggit\libasynql\libasynql;
use SOFe\AwaitGenerator\Await;

final class MyPlotDatabase extends AwaitDatabase implements QueryIds
{

    private static string $type = self::TYPE_SQLITE;

    public function __construct(MyPlot $plugin)
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

        parent::__construct($plugin, $connector);
    }

    /**
     * @internal
     */
    public function init(): void
    {
        Await::f2c(
            function () {
                // create tables
                yield $this->asyncGeneric(self::INIT_PLOTS_V2);
                yield $this->asyncGeneric(self::INIT_MERGED_PLOTS_V2);

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


}