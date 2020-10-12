<?php
declare(strict_types=1);

namespace MyPlot\forms;

use libforms\elements\Button;
use MyPlot\subcommand\SubCommand;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class MainForm extends SimpleMyPlotForm{

	/**
	 * @param SubCommand[] $subCommands
	 */
	public function __construct(Player $player, array $subCommands) {
		$this->plot = $this->plugin->getPlotByPosition($player->getPosition());

		$elements = [];
		foreach($subCommands as $name => $command){
			if(!$command->canUse($player) or ($form = $command->getForm($player)) === null) {
				continue;
			}

			/** @var SimpleMyPlotForm|ComplexMyPlotForm $form */
            $form->setPlayer($player);
            $form->setPlot($this->plot);

			$elements[] = new Button($form->getName(), static function(Player $player) use ($form) {
				$form->sendForm();
			});
		}
		parent::__construct(
			$player,
			TextFormat::BLACK . $this->plugin->getLanguage()->translateString("form.header", [$this->getName()]),
			"",
			$elements
		);
	}

	public function getName(): string
    {
        return "Plots";
    }
}