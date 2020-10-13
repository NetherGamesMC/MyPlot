<?php
declare(strict_types=1);

namespace MyPlot\forms;

use libforms\elements\Button;
use libforms\FormManager;
use MyPlot\forms\interfaces\DangerZone;
use MyPlot\forms\interfaces\PlotAdminForm;
use MyPlot\forms\interfaces\PlotButtonForm;
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
		$dangerForms = [];
		$adminForms = [];

		foreach($subCommands as $name => $command){
            $form = $command->getForm($player);

			if(!$command->canUse($player) || $form === null) {
				continue;
			}

            /** @var SimpleMyPlotForm|ComplexMyPlotForm|ModalMyPlotForm $form */
            $form->setPlayer($player);
            $form->setPlot($this->plot);

			if($form instanceof PlotSettingsForm){
			    if($form instanceof DangerZone){
			        $dangerForms[$name] = $form;
			        continue;
                }

			    $settingForms[$name] = $form;
			    continue;
            }

            if($form instanceof PlotAdminForm){
                $adminForms[$name] = $form;
                continue;
            }

			$elements[] = new Button($form->getName(), static function(Player $player) use ($form) {
                if($form instanceof PlotButtonForm){
			        $form->onButtonClick($player);
			        return;
                }

			    $form->sendForm();
			});
		}

		// only add settings form if the player is inside a plot and is the plot owner or has admin perms
		if($this->plot !== null && ((strtolower($this->plot->owner) === strtolower($player->getName())) || $player->hasPermission('myplot.admin') || $player->hasPermission('nethergames.admin'))){
		    $elements[] = new Button("Plot Settings", function (Player $player) use ($settingForms, $dangerForms){
                $settings = FormManager::createSimpleForm($player);
                $settings->setTitle("Plot Settings");

                foreach ($settingForms as $name => $form){
                    $button = new Button($form->getName(), function (Player $player) use ($form){
                        $form->sendForm();
                    });

                    $settings->addButton($button);
                }

                $button = new Button("§cDanger Zone", function (Player $player) use ($dangerForms){
                    $dangerZone = FormManager::createSimpleForm($player);
                    $dangerZone->setTitle("Danger Zone");

                    foreach ($dangerForms as $name => $form){
                        $button = new Button($form->getName(), function (Player $player) use ($form){
                            $form->sendForm();
                        });

                        $dangerZone->addButton($button);
                    }

                    $dangerZone->sendForm();
                });

                $settings->addButton($button);
                $settings->sendForm();
            });
        }

        // only add admin form if the player has admin perms
        if($player->hasPermission('myplot.admin') || $player->hasPermission('nethergames.admin')){
            $elements[] = new Button("Admin Settings", function (Player $player) use ($adminForms){
                $settings = FormManager::createSimpleForm($player);
                $settings->setTitle("Admin Settings");

                foreach ($adminForms as $name => $form){
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