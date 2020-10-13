<?php
declare(strict_types=1);

namespace MyPlot\forms;

use libforms\SimpleForm;
use MyPlot\forms\interfaces\MyPlotForm;
use MyPlot\forms\traits\PlotTrait;
use MyPlot\Plot;
use pocketmine\player\Player;

abstract class SimpleMyPlotForm extends SimpleForm implements MyPlotForm {

    use PlotTrait;

	/** @var Plot|null $plot */
	protected $plot;

	public function __construct(?Player $player, string $title, string $text, array $options) {
		parent::__construct($player);

		$this->setTitle($title);
		$this->setContent($text);

		foreach($options as $option){
			$this->addButton($option);
		}
	}
}