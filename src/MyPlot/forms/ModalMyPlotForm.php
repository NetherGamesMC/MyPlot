<?php
declare(strict_types=1);

namespace MyPlot\forms;

use libforms\elements\Button;
use libforms\ModalForm;
use MyPlot\forms\interfaces\MyPlotForm;
use MyPlot\forms\traits\PlotTrait;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\player\Player;

abstract class ModalMyPlotForm extends ModalForm implements MyPlotForm {

    use PlotTrait;

	/** @var Plot|null $plot */
	protected $plot;

	public function __construct(?Player $player, string $title, string $text, Button $yesButton, ?Button $noButton = null) {
		parent::__construct($player);

		$this->setTitle($title);
		$this->setContent($text);

		$this->setButton1($yesButton);

		if($noButton instanceof Button){
		    $this->setButton2($noButton);
        }else{
		    $this->setButton2(new Button("No", function (Player $player){
                $player->getServer()->dispatchCommand($player, MyPlot::getInstance()->getLanguage()->get("command.name"), true);
            }));
        }
	}
}