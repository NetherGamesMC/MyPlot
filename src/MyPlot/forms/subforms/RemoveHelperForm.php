<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Dropdown;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\forms\interfaces\PlotSettingsForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class RemoveHelperForm extends ComplexMyPlotForm implements PlotSettingsForm{
	public function __construct() {
		$plugin = MyPlot::getInstance();
		parent::__construct(
			null,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("removehelper.form")]),
			[
				new Dropdown(
					$plugin->getLanguage()->get("removehelper.dropdown"),
					empty($this->plot->helpers) ? [TextFormat::DARK_BLUE . $plugin->getLanguage()->get("removehelper.formnohelpers")] : array_map(function(string $text) {
						return TextFormat::DARK_BLUE . $text;
					}, $this->plot->helpers),
					-1,
					function(Player $player, int $data) use ($plugin) : void {
						if(empty($this->plot->helpers)) {
							return;
						}

						$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("removehelper.name") . ' "' . $this->plot->helpers[$data] . '"', true);
					}
				)
			]
		);
	}

	public function getName() : string {
		return "Remove a helper";
	}
}