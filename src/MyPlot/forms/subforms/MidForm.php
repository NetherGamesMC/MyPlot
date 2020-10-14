<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\forms\interfaces\PlotButtonForm;
use MyPlot\forms\interfaces\PlotSettingsForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class MidForm extends ComplexMyPlotForm implements PlotSettingsForm, PlotButtonForm{

	public function __construct(Player $player) {
		$plugin = MyPlot::getInstance();

		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header"),
			[]
		);
	}

	public function getName() : string {
		return "Find middle of plot";
	}

	public function onButtonClick(Player $player) : void {
		$plugin = MyPlot::getInstance();
		$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("middle.name"), true);
	}
}