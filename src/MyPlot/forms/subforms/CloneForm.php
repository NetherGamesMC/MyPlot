<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Input;
use libforms\elements\Label;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\MyPlot;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class CloneForm extends ComplexMyPlotForm{

	/** @var Player $player */
	private $player;

	public function __construct(Player $player) {
		$plugin = $this->plugin;
		$plot = $plugin->getPlotByPosition($player->getPosition());
		if($plot === null) {
			$plot = new \stdClass();
			$plot->X = "";
			$plot->Z = "";
		}
		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("clone.form")]),
			[
				new Label($plugin->getLanguage()->get("clone.formlabel1")),
				new Input($plugin->getLanguage()->get("clone.formxcoord"), "2", (string)$plot->X),
				new Input($plugin->getLanguage()->get("clone.formzcoord"), "-4", (string)$plot->Z),
				new Input($plugin->getLanguage()->get("clone.formworld"), "world", $player->getWorld()->getFolderName()),
				new Label($plugin->getLanguage()->get("clone.formlabel2")),
				new Input($plugin->getLanguage()->get("clone.formxcoord"), "2"),
				new Input($plugin->getLanguage()->get("clone.formzcoord"), "-4"),
				new Input($plugin->getLanguage()->get("clone.formworld"), "world", $player->getWorld()->getFolderName())
			],
			function(Player $player, array $data) use ($plugin) : void {
				if(is_numeric($data[1]) and is_numeric($data[2]) and is_numeric($data[5]) and is_numeric($data[6])) {
					$originPlot = MyPlot::getInstance()->getProvider()->getPlot(empty($data[3]) ? $this->player->getWorld()->getFolderName() : $data[3], (int)$data[1], (int)$data[2]);
					$clonedPlot = MyPlot::getInstance()->getProvider()->getPlot(empty($data[7]) ? $this->player->getWorld()->getFolderName() : $data[7], (int)$data[5], (int)$data[6]);
				}else
					throw new FormValidationException("Unexpected form data returned");

				if($originPlot->owner !== $player->getName() and !$player->hasPermission("myplot.admin.clone")) {
					$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("notowner"));
					return;
				}
				if($clonedPlot->owner !== $player->getName() and !$player->hasPermission("myplot.admin.clone")) {
					$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("notowner"));
					return;
				}
				$plotLevel = $plugin->getLevelSettings($originPlot->levelName);
				$economy = $plugin->getEconomyProvider();
				if($economy !== null and !$economy->reduceMoney($player, $plotLevel->clonePrice)) {
					$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("clone.nomoney"));
					return;
				}
				if($plugin->clonePlot($originPlot, $clonedPlot)) {
					$player->sendMessage($plugin->getLanguage()->translateString("clone.success", [$clonedPlot->__toString(), $originPlot->__toString()]));
				}else{
					$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("error"));
				}
			}
		);
	}
}