<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Input;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\forms\interfaces\PlotSettingsForm;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class NameForm extends ComplexMyPlotForm implements PlotSettingsForm
{

    public function __construct(Player $player, Plot $plot)
    {
        $plugin = MyPlot::getInstance();
        $this->setPlot($plot);
        parent::__construct(
            $player,
            TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("name.form")]),
            [
                new Input(
                    $plugin->getLanguage()->get("name.formtitle"),
                    $player->getDisplayName() . "'s Plot",
                    $this->plot->name,
                    function (Player $player, string $data) use ($plugin): void {
                        $player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("name.name") . ' "' . $data . '"', true);
                    }
                )
            ]
        );
    }

    public function getName(): string
    {
        return "Change plot name";
    }
}