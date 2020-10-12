<?php
declare(strict_types=1);

namespace MyPlot\forms;

use libforms\elements\Button;
use MyPlot\forms\interfaces\ButtonForm;
use MyPlot\MyPlot;
use MyPlot\subcommand\SubCommand;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class MainForm extends SimpleMyPlotForm{

	/**
	 * MainForm constructor.
	 *
	 * @param Player $player
	 * @param SubCommand[] $subCommands
	 *
	 * @throws \ReflectionException
	 */
	public function __construct(Player $player, array $subCommands) {
		$plugin = MyPlot::getInstance();

		$this->plot = $plugin->getPlotByPosition($player->getPosition());

		$elements = [];
		foreach($subCommands as $name => $command){
			if(!$command->canUse($player) or ($form = $command->getForm($player)) === null) {
				continue;
			}

			/** @var SimpleMyPlotForm|ComplexMyPlotForm $form */
            $form->setPlayer($player);
            $form->setPlot($this->plot);

			$elements[] = new Button($form->getName(), static function(Player $player) use ($form) {
			    if($form instanceof ButtonForm){
			        $form->onButtonClick();
			        return;
                }
			});
		}
		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$this->getName()]),
			"",
			$elements
		);
	}

	public function getName(): string
    {
        return "Plots";
    }
}