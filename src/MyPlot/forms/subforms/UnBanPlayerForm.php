<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Dropdown;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\forms\interfaces\PlotSettingsForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class UnBanPlayerForm extends ComplexMyPlotForm implements PlotSettingsForm{
	public function __construct() {
		$plugin = MyPlot::getInstance();
		parent::__construct(
			null,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("unbanplayer.form")]),
			[
				new Dropdown(
					$plugin->getLanguage()->get("unbanplayer.dropdown"),
					empty($this->plot->denied) ? [TextFormat::DARK_BLUE . $plugin->getLanguage()->get("unbanplayer.formnodenied")] : array_map(function(string $text) {
						return TextFormat::DARK_BLUE . $text;
					}, $this->plot->denied),
					-1,
					function(Player $player, int $data) use ($plugin) : void {
						if(empty($this->plot->denied)) {
							return;
						}

						$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("unbanplayer.name") . ' "' . $this->plot->denied[$data] . '"', true);
					}
				)
			]
		);
	}

	public function getName() : string {
		return "Unban a player";
	}
}