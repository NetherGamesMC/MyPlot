<?php
declare(strict_types=1);

namespace MyPlot\forms;

use libforms\CustomForm;
use MyPlot\forms\interfaces\MyPlotForm;
use MyPlot\forms\traits\PlotTrait;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\player\Player;

abstract class ComplexMyPlotForm extends CustomForm implements MyPlotForm{

	use PlotTrait;

	/** @var Plot|null $plot */
	protected $plot;

	public function __construct(?Player $player, string $title, array $elements, ?\Closure $onSubmit = null) {
		parent::__construct($player, $onSubmit ?? static function(Player $player) : void {
				$player->getServer()->dispatchCommand($player, MyPlot::getInstance()->getLanguage()->get("command.name"), true);
			}
		);

		$this->setTitle($title);

		foreach($elements as $element){
			$this->addElement($element);
		}
	}
}