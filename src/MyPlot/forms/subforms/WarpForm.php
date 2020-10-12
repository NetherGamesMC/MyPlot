<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Input;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\MyPlot;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class WarpForm extends ComplexMyPlotForm{
	/** @var Player $player */
	private $player;

	public function __construct(Player $player) {
		$plugin = MyPlot::getInstance();
		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("warp.form")]),
			[
				new Input($plugin->getLanguage()->get("warp.formxcoord"), "0"),
				new Input($plugin->getLanguage()->get("warp.formzcoord"), "0")
			],
			function(Player $player, ?array $data = []) use ($plugin) : void {
				if(is_numeric($data[0]) and is_numeric($data[1])) {
					$datum = [
						(int)$data[0],
						(int)$data[1],
						$this->player->getWorld()->getFolderName()
					];
				}elseif(empty($data[0]) and empty($data[1])){
				    $this->sendForm();
					throw new FormValidationException("Invalid form data returned");
				}else{
					throw new FormValidationException("Unexpected form data returned");
				}

				$player->getServer()->dispatchCommand($player, $plugin->getLanguage()->get("command.name") . " " . $plugin->getLanguage()->get("warp.name") . " " . ((int)$datum[0]) . ";" . ((int)$datum[1]) . ' "' . ($datum[2]) . '"', true);
			}
		);
	}

    public function getName(): string
    {
        return "Warp";
    }
}