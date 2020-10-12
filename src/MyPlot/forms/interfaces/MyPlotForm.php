<?php
declare(strict_types=1);

namespace MyPlot\forms\interfaces;

use MyPlot\Plot;

interface MyPlotForm{

    public function getName(): string;

    public function setPlot(?Plot $plot) : void;

    public function getPlot() : ?Plot;
}