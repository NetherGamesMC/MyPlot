<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Button;
use MyPlot\forms\SimpleMyPlotForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class HomeForm extends SimpleMyPlotForm{
	public function __construct(Player $player) {
		$plugin = MyPlot::getInstance();

		$plots = $plugin->getPlotsOfPlayer($player->getName(), $player->getWorld()->getFolderName());
		$i = 1;
		$elements = [];
		foreach($plots as $plot){
			$elements[] = new Button(TextFormat::DARK_RED . $i . ") " . $plot->name . " " . (string)$plot, static function(Player $player) use ($plugin, $i) {
				$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("home.name") . ' "' . ($i) . '"', true);
			});
			$i++;
		}
		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("home.form")]),
			"",
			$elements
		);
	}

    public function getName(): string
    {
        return "Home";
    }
}