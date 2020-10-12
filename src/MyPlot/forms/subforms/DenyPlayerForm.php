<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Dropdown;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\Plot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class DenyPlayerForm extends ComplexMyPlotForm{

	/** @var string[] $players */
	private $players = [];

	public function __construct(Plot $plot) {
		$plugin = $this->plugin;
		$players = [];
		if(!in_array("*", $plot->denied)) {
			$players = ["*"];
			$this->players = ["*"];
		}
		foreach($plugin->getServer()->getOnlinePlayers() as $player){
			$players[] = $player->getDisplayName();
			$this->players[] = $player->getName();
		}
		parent::__construct(
			null,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("denyplayer.form")]),
			[
				new Dropdown(
					$plugin->getLanguage()->get("denyplayer.dropdown"),
					array_map(function(string $text) {
						return TextFormat::DARK_BLUE . $text;
					}, $players),
					-1,
					function(Player $player, int $data) use ($plugin) {
						$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("denyplayer.name") . ' "' . $this->players[$data] . '"', true);
					}
				)
			]
		);
	}

    public function getName(): string
    {
        return "Ban a Player";
    }
}