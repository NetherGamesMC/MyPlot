<?php
declare(strict_types=1);

namespace MyPlot\forms\interfaces;

use pocketmine\player\Player;

interface ButtonForm{

    public function onButtonClick(Player $player): void;
}