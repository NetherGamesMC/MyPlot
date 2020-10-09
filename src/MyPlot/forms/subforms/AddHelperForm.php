<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Dropdown;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class AddHelperForm extends ComplexMyPlotForm{
	/** @var string[] $players */
	private $players = [];

	public function __construct(Plot $plot) {
		$plugin = MyPlot::getInstance();
		$players = [];
		if(!in_array("*", $plot->helpers)) {
			$players = ["*"];
			$this->players = ["*"];
		}
		foreach($plugin->getServer()->getOnlinePlayers() as $player){
			$players[] = $player->getDisplayName();
			$this->players[] = $player->getName();
		}
		parent::__construct(
			null,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("addhelper.form")]),
			[
				new Dropdown(
					$plugin->getLanguage()->get("addhelper.dropdown"),
					array_map(
						function(string $text) {
							return TextFormat::DARK_BLUE . $text;
						}, $players
					),
					-1,
					function(Player $player, int $data) use ($plugin) {
						$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("addhelper.name") . ' "' . $this->players[$data] . '"', true);
					}
				)
			]
		);
	}
}