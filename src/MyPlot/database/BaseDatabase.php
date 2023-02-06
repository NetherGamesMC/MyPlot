<?php
declare(strict_types=1);

namespace MyPlot\database;

use MyPlot\MyPlot;
use MyPlot\Plot;
use poggit\libasynql\DataConnector;
use function array_merge;
use function array_shift;
use function count;

abstract class BaseDatabase
{
    // public const TYPE_MYSQL = "mysql";
    public const TYPE_SQLITE = "sqlite";

    /** @var Plot[] $cache */
    private array $cache = [];

    public function __construct(
        protected MyPlot        $plugin,
        protected DataConnector $connector,
        private int             $cacheSize = 0
    )
    {}

    public function getPlugin(): MyPlot
    {
        return $this->plugin;
    }

    public function getConnector(): DataConnector
    {
        return $this->connector;
    }

    public abstract function init(): void;

    public abstract function postInit(): void;

    public function close(): void
    {
        $this->connector->close();
        $this->connector->waitAll();
        $this->plugin->getLogger()->debug("Database closed");
    }

    protected final function cachePlot(Plot $plot): void
    {
        if ($this->cacheSize > 0) {
            $key = $plot->levelName . ';' . $plot->X . ';' . $plot->Z;
            if (isset($this->cache[$key])) {
                unset($this->cache[$key]);
            } elseif ($this->cacheSize <= count($this->cache)) {
                array_shift($this->cache);
            }
            $this->cache = array_merge([$key => clone $plot], $this->cache);
            $this->plugin->getLogger()->debug("Plot $plot->X;$plot->Z has been cached");
        }
    }

    protected final function getPlotFromCache(string $levelName, int $X, int $Z): ?Plot
    {
        if ($this->cacheSize > 0) {
            $key = $levelName . ';' . $X . ';' . $Z;
            if (isset($this->cache[$key])) {
                $this->plugin->getLogger()->debug("Plot {$X};{$Z} was loaded from the cache");
                return $this->cache[$key];
            }
        }
        return null;
    }
}