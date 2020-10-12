<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Input;
use MyPlot\forms\ComplexMyPlotForm;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class WarpForm extends ComplexMyPlotForm{
	/** @var Player $player */
	private $player;

	public function __construct(Player $player) {
		$plugin = $this->plugin;

		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("warp.form")]),
			[
				new Input($plugin->getLanguage()->get("warp.formxcoord"), "2"),
				new Input($plugin->getLanguage()->get("warp.formzcoord"), "-4"),
				new Input($plugin->getLanguage()->get("warp.formworld"), "world", $player->getWorld()->getFolderName())
			],
			function(Player $player, array $data) use ($plugin) : void {
				if(is_numeric($data[0]) and is_numeric($data[1])) {
					$datum = [
						(int)$data[0],
						(int)$data[1],
						empty($data[2]) ? $this->player->getWorld()->getFolderName() : $data[2]
					];
				}elseif(empty($data[0]) and empty($data[1])){
					$this->player->sendForm(new self($this->player));
					throw new FormValidationException("Invalid form data returned");
				}else{
					throw new FormValidationException("Unexpected form data returned");
				}

				$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("warp.name") . " " . ((int)$datum[0]) . ";" . ((int)$datum[1]) . ' "' . ($datum[2]) . '"', true);
			}
		);
	}
}