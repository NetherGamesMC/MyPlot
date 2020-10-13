<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Dropdown;
use libforms\elements\Input;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\MyPlot;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function count;
use function ksort;

class WarpForm extends ComplexMyPlotForm{

	public function __construct(Player $player) {
		$plugin = MyPlot::getInstance();
		$elements = [];
		$plotNames = [];
		$plots = $plugin->getProvider()->getPlotsByOwner($player->getName(), $player->getWorld()->getFolderName());
		ksort($plots);
		if(!empty($plots)) {
			for($i = 0, $iMax = count($plots); $i < $iMax; $i++){
				$plotNames[$i] = 'Plot #' . ($i + 1) . ' (' . $plots[$i]->X . ';' . $plots[$i]->Z . ')';
			}
		}
		ksort($plotNames);

		$elements[] = new Dropdown("Select your plots:", $plotNames, -1, static function(Player $player, int $data) use ($plots, $plugin): void {
			$plot = $plots[$data];
			$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("warp.name") . " " . ($plot->X) . ";" . ($plot->Z) . ' "' . ($player->getWorld()->getFolderName()) . '"', true);
		});

		$elements[] = new Input($plugin->getLanguage()->get("warp.formxcoord"), "0");
		$elements[] = new Input($plugin->getLanguage()->get("warp.formzcoord"), "0");

		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("warp.form")]),
			$elements,
			function(Player $player, ?array $data = []) use ($plugin) : void {
				if(!isset($data[1]) and !isset($data[2])) {
					$player->sendMessage(TextFormat::RED . "Please verify that both the input boxes are filled.");
					return;
				}

				$datum = [
					(int)$data[1],
					(int)$data[2],
					$player->getWorld()->getFolderName()
				];

				$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("warp.name") . " " . ((int)$datum[0]) . ";" . ((int)$datum[1]) . ' "' . ($datum[2]) . '"', true);
			}
		);
	}

	public function getName() : string {
		return "Teleport";
	}
}