<?php
declare(strict_types=1);
namespace MyPlot\forms;

use MyPlot\Plot;

interface MyPlotForm {
	/**
	 * @param Plot|null $plot
	 *
	 * @return void
	 */
	public function setPlot(?Plot $plot) : void;
}