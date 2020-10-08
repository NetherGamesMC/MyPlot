<?php
declare(strict_types=1);
namespace MyPlot\forms;

use libforms\elements\Button;
use MyPlot\MyPlot;
use MyPlot\subcommand\SubCommand;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function ucfirst;
use function var_dump;

class MainForm extends SimpleMyPlotForm {

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
		foreach($subCommands as $name => $command) {
			if(!$command->canUse($player) or $command->getForm($player) === null){
                continue;
            }

			$name = (new \ReflectionClass($command))->getShortName();
			$name = preg_replace('/([a-z])([A-Z])/s','$1 $2', $name);
			$length = strlen($name) - strlen("Sub Command");
			$name = substr($name, 0, $length);
			var_dump($name);
			$elements[] = new Button(TextFormat::DARK_RED . ucfirst($name), static function(Player $player) use ($command){
			    $form = $command->getForm($player);

			    if(!$form instanceof SimpleMyPlotForm /*|| !$form instanceof ComplexMyPlotForm*/){
			        return;
                }

			    $form->setPlayer($player); //just added safety..
                $form->setPlot($this->plot);
                $form->sendForm();
            });
		}
		parent::__construct(
		    $player,
			TextFormat::BLACK.$plugin->getLanguage()->translateString("form.header", ["Main"]),
			"",
			$elements
		);
	}
}