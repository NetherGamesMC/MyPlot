<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Dropdown;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\forms\interfaces\PlotSettingsForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class KickForm extends ComplexMyPlotForm implements PlotSettingsForm{
	/** @var string[] $players */
	private $players = [];

	public function __construct() {
		$plugin = MyPlot::getInstance();
		$players = [];
		foreach($plugin->getServer()->getOnlinePlayers() as $player){
			if(isset($this->plot) and !$plugin->getPlotByPosition($player->getPosition())->isSame($this->plot)) {
				continue;
			}
			$players[] = $player->getDisplayName();
			$this->players[] = $player->getName();
		}
		parent::__construct(
			null,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("kick.form")]),
			[
				new Dropdown(
					$plugin->getLanguage()->get("kick.dropdown"),
					$players,
					-1,
					function(Player $player, int $data) use ($plugin) : void {
						$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("kick.name") . ' "' . $this->players[$data] . '"', true);
					}
				)
			],
		);
	}

    public function getName(): string
    {
        return "Kick a player";
    }
}