<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\forms\interfaces\PlotButtonForm;
use MyPlot\MyPlot;
use NetherGames\NGEssentials\player\permissions\Permissions;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class AutoForm extends ComplexMyPlotForm implements PlotButtonForm
{

    public function __construct(Player $player)
    {
        $plugin = MyPlot::getInstance();

        parent::__construct(
            $player,
            TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header"),
            []
        );
    }

    public function getName(): string
    {
        return "Find a new plot";
    }

    public function onButtonClick(Player $player): void
    {
        $plugin = MyPlot::getInstance();
        $player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("auto.name"), true);
    }
}