<?php
declare(strict_types=1);

namespace MyPlot\database;

use MyPlot\MyPlot;
use poggit\libasynql\DataConnector;

abstract class BaseDatabase
{
    public function __construct(
        protected MyPlot        $plugin,
        protected DataConnector $connector
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
}