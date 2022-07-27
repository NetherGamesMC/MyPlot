<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Input;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\forms\interfaces\PlotSettingsForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class FillForm extends ComplexMyPlotForm implements PlotSettingsForm
{

    public function __construct()
    {
        $plugin = MyPlot::getInstance();

        parent::__construct(
            null,
            TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("fill.form")]),
            [
                new Input(
                    $plugin->getLanguage()->get("fill.form"),
                    "0",
                    "1:0",
                    function (Player $player, string $input) use ($plugin): void {
                        $player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("fill.name") . ' "' . $input . '"', true);
                    }
                )
            ],
            function (Player $player, ?array $data = []): void {
            }
        );
    }

    public function getName(): string
    {
        return "Fill plot";
    }
}