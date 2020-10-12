<?php
declare(strict_types=1);

namespace MyPlot\forms;

use libforms\elements\Button;
use libforms\FormManager;
use MyPlot\forms\interfaces\ButtonForm;
use MyPlot\forms\interfaces\PlotSettingsForm;
use MyPlot\MyPlot;
use MyPlot\subcommand\SubCommand;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function strtolower;

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
		$settingForms = [];

		foreach($subCommands as $name => $command){
            $form = $command->getForm($player);

			if(!$command->canUse($player) || $form === null) {
				continue;
			}

			if($form instanceof PlotSettingsForm){
			    $settingForms[$name] = $form;
			    continue;
            }

			/** @var SimpleMyPlotForm|ComplexMyPlotForm $form */
            $form->setPlayer($player);
            $form->setPlot($this->plot);

			$elements[] = new Button($form->getName(), static function(Player $player) use ($form) {
                if($form instanceof ButtonForm){
			        $form->onButtonClick($player);
			        return;
                }

			    $form->sendForm();
			});
		}

		// only add settings form if the player is inside a plot and is the plot owner or has admin perms
		if($this->plot !== null && ((strtolower($this->plot->owner) === strtolower($player->getName())) || $player->hasPermission('nethergames.admin'))){
		    $elements[] = new Button("Plot Settings", function (Player $player) use ($settingForms){
                $settings = FormManager::createSimpleForm($player);

                foreach ($settingForms as $name => $form){
                    $form->setPlayer($player);
                    $form->setPlot($this->plot);

                    $button = new Button($form->getName(), function (Player $player) use ($form){
                        $form->sendForm();
                    });

                    $settings->addButton($button);
                }

                $settings->sendForm();
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