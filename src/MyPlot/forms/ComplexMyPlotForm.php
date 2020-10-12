<?php
declare(strict_types=1);

namespace MyPlot\forms;

use libforms\CustomForm;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\player\Player;

abstract class ComplexMyPlotForm extends CustomForm implements MyPlotForm
{
	/** @var Plot|null $plot */
	protected $plot;
    /** @var MyPlot */
    protected $plugin;

	public function __construct(?Player $player, string $title, array $elements, ?\Closure $onSubmit = null) {
	    $this->plugin = MyPlot::getInstance();

		parent::__construct($player, $onSubmit ?? function(Player $player) : void {
				$player->getServer()->dispatchCommand($player, $this->plugin->getLanguage()->get("command.name"), true);
			}
		);

		$this->setTitle($title);

		foreach($elements as $element){
			$this->addElement($element);
		}
	}

	public function setPlot(?Plot $plot) : void {
		$this->plot = $plot;
	}

	public function getPlot() : ?Plot {
		return $this->plot;
	}
}