<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Dropdown;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class OwnerForm extends ComplexMyPlotForm{
	/** @var string[] $players */
	private $players = [];

	public function __construct() {
		$plugin = MyPlot::getInstance();

		$players = [];
		foreach($plugin->getServer()->getOnlinePlayers() as $player){
			$players[] = $player->getDisplayName();
			$this->players[] = $player->getName();
		}
		parent::__construct(
			null,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("setowner.form")]),
			[
				new Dropdown(
					$plugin->getLanguage()->get("setowner.dropdown"),
					$players,
					-1,
					function(Player $player, int $data) use ($plugin) : void {
						$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("setowner.name") . ' "' . $this->players[$data] . '"', true);
					}
				)
			]
		);
	}
}