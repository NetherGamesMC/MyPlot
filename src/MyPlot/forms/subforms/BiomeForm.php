<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Button;
use MyPlot\forms\interfaces\PlotSettingsForm;
use MyPlot\forms\SimpleMyPlotForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class BiomeForm extends SimpleMyPlotForm implements PlotSettingsForm{

	/**
	 * BiomeForm constructor.
	 *
	 * @param string[] $biomes
	 */
	public function __construct(array $biomes) {
		$plugin = MyPlot::getInstance();

		$elements = [];
		foreach($biomes as $biomeName){
			$elements[] = new Button(
				TextFormat::DARK_RED . ucfirst(strtolower(str_replace("_", " ", $biomeName))),
				static function(Player $player) use ($plugin, $biomeName) {
					$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("biome.name") . ' "' . $biomeName . '"', true);
				}
			); // TODO: add images
		}

		parent::__construct(
			null,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("biome.form")]),
			"",
			$elements
		);
	}

	public function getName() : string {
		return "Change plot biome";
	}
}