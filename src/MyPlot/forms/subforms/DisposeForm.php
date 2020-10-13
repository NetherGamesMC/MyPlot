<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Button;
use MyPlot\forms\interfaces\DangerZone;
use MyPlot\forms\interfaces\PlotSettingsForm;
use MyPlot\forms\ModalMyPlotForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class DisposeForm extends ModalMyPlotForm implements PlotSettingsForm, DangerZone{

	public function __construct() {
		$plugin = MyPlot::getInstance();

		parent::__construct(
			null,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", ["Dispose"]),
			"Are you sure you want to dispose your plot? This action cannot be undone.",
			new Button("Yes", function(Player $player) use ($plugin) {
				$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("dispose.name") . " confirm", true);
			})
		);
	}

	public function getName() : string {
		return "Dispose";
	}
}