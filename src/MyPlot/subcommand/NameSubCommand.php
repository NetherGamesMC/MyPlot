<?php
declare(strict_types=1);
namespace MyPlot\subcommand;

use MyPlot\forms\interfaces\MyPlotForm;
use MyPlot\forms\subforms\NameForm;
use MyPlot\Plot;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class NameSubCommand extends SubCommand
{
	/**
	 * @param CommandSender $sender
	 *
	 * @return bool
	 */
	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.name");
	}

	/**
	 * @param Player $sender
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, array $args) : bool {
		if(empty($args)) {
			return false;
		}
		$plot = $this->getPlugin()->getPlotByPosition($sender->getPosition());
		if($plot === null) {
			$sender->sendMessage(TextFormat::RED . $this->translateString("notinplot"));
			return true;
		}
		if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.name")) {
			$sender->sendMessage(TextFormat::RED . $this->translateString("notowner"));
			return true;
		}
		if($this->getPlugin()->renamePlot($plot, $args[0])) {
			$sender->sendMessage($this->translateString("name.success"));
		}else{
			$sender->sendMessage(TextFormat::RED . $this->translateString("error"));
		}
		return true;
	}

	public function getForm(Player $player) : ?MyPlotForm {
		if($this->getPlugin()->getPlotByPosition($player->getPosition()) instanceof Plot)
			return new NameForm($player);
		return null;
	}
}