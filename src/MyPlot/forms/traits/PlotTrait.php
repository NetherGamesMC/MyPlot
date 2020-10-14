<?php
declare(strict_types=1);

namespace MyPlot\forms\traits;

use MyPlot\forms\interfaces\PlotButtonForm;
use MyPlot\Plot;
use pocketmine\player\Player;

trait PlotTrait{

	public function setPlot(?Plot $plot) : void {
		$this->plot = $plot;
	}

	public function getPlot() : ?Plot {
		return $this->plot;
	}

	public function preHandle(Player $player) : bool {
		return true;
	}

	public function sendForm() : void {
		if(!$this->preHandle($this->getPlayer())) {
			return;
		}

		if($this instanceof PlotButtonForm) {
			$this->onButtonClick($this->getPlayer());
			return;
		}

		parent::sendForm();
	}
}