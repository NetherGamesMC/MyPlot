<?php
declare(strict_types=1);

namespace MyPlot\forms;

use libforms\elements\Button;
use MyPlot\MyPlot;
use MyPlot\subcommand\SubCommand;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function ucfirst;

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

			$name = (new \ReflectionClass($command))->getShortName();
			$name = preg_replace('/([a-z])([A-Z])/s', '$1 $2', $name);
			$length = strlen($name) - strlen("Sub Command");
			$name = substr($name, 0, $length);

			$elements[] = new Button(TextFormat::DARK_RED . ucfirst($name), function(Player $player) use ($form) {
				/** @var SimpleMyPlotForm|ComplexMyPlotForm|null $form */
				if($form === null) {
					return;
				}

				$form->setPlayer($player); //don't remove this..
				$form->setPlot($this->plot);
				$form->sendForm();
			});
		}
		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", ["Main"]),
			"",
			$elements
		);
	}
}