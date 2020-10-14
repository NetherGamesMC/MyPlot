<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Dropdown;
use libforms\elements\Label;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\MyPlot;
use MyPlot\subcommand\BiomeSubCommand;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class InfoForm extends ComplexMyPlotForm{
	public function __construct(Player $player) {
		$plugin = MyPlot::getInstance();

		if(!isset($this->plot)) {
			$this->plot = $plugin->getPlotByPosition($player->getPosition());
		}

		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("info.form")]),
			[
				new Label($plugin->getLanguage()->translateString("info.formcoords", [(string)$this->plot])),
				new Label($plugin->getLanguage()->translateString("info.formowner", [TextFormat::BOLD . $this->plot->owner])),
				new Label($plugin->getLanguage()->translateString("info.formpname", [TextFormat::BOLD . $this->plot->name])),
				new Dropdown(
					$plugin->getLanguage()->get("info.formhelpers"),
					empty($this->plot->helpers) ? [TextFormat::DARK_BLUE . $plugin->getLanguage()->get("info.formnohelpers")] : array_map(function(string $text) {
						return TextFormat::DARK_BLUE . $text;
					}, $this->plot->helpers)
				),
				new Dropdown(
					$plugin->getLanguage()->get("info.formdenied"),
					empty($this->plot->banned) ? [TextFormat::DARK_BLUE . $plugin->getLanguage()->get("info.formnodenied")] : array_map(function(string $text) {
						return TextFormat::DARK_BLUE . $text;
					}, $this->plot->banned)
				),
				new Dropdown(
					$plugin->getLanguage()->get("info.formbiome"),
					array_map(function(string $text) {
						return TextFormat::DARK_BLUE . ucfirst(strtolower(str_replace("_", " ", $text)));
					}, array_keys(BiomeSubCommand::BIOMES)),
					(int)array_search($this->plot->biome, array_keys(BiomeSubCommand::BIOMES))
				),
				new Label($plugin->getLanguage()->translateString("info.formpvp", [$this->plot->pvp ? "Enabled" : "Disabled"]))
			]
		);
	}

	public function getName() : string {
		return "Plot Info";
	}
}