<?php
declare(strict_types=1);

namespace MyPlot\forms\interfaces;

use MyPlot\Plot;
use pocketmine\player\Player;

interface MyPlotForm{

    public function getName(): string;

    public function setPlot(?Plot $plot) : void;

    public function getPlot() : ?Plot;

    public function preHandle(Player $player) : bool;
}