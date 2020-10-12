<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\forms\interfaces\ButtonForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class AutoForm extends ComplexMyPlotForm implements ButtonForm {

	/** @var Player $player */
	private $player;

	public function __construct(Player $player) {
		$plugin = MyPlot::getInstance();
		$this->player = $player;

		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header"),
			[]
		);
	}

	public function getName(): string
    {
        return "Find a new plot";
    }
    
    public function onButtonClick(Player $player): void
    {
        $plugin = MyPlot::getInstance();

        if ($player->getWorld()->getFolderName() === 'Platinum' && (!$player->hasPermission('nethergames.vip.ultra'))) {
            $player->sendMessage('§cThat action is blocked for you in this world.');
            return;
        }

        $player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("auto.name"), true);
    }
}