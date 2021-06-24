<?php
declare(strict_types=1);
namespace MyPlot\subcommand;

use MyPlot\forms\interfaces\MyPlotForm;
use MyPlot\forms\subforms\UnBanPlayerForm;
use MyPlot\Plot;
use pocketmine\command\CommandSender;
use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class UnBanSubCommand extends SubCommand
{
	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.unbanplayer");
	}

	/**
	 * @param Player $sender
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, array $args) : bool {
		if(count($args) === 0) {
			return false;
		}
		$dplayerName = $args[0];
		$plot = $this->getPlugin()->getPlotByPosition($sender->getPosition());
		if($plot === null) {
			$sender->sendMessage(TextFormat::RED . $this->translateString("notinplot"));
			return true;
		}
		if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.unbanplayer")) {
			$sender->sendMessage(TextFormat::RED . $this->translateString("notowner"));
			return true;
		}
		$dplayer = $this->getPlugin()->getServer()->getPlayerByPrefix($dplayerName);
		if($dplayer === null)
			$dplayer = new OfflinePlayer($dplayerName, Server::getInstance()->getOfflinePlayerData($dplayerName));
		if($this->getPlugin()->removePlotDenied($plot, $dplayer->getName())) {
			$sender->sendMessage($this->translateString("unbanplayer.success1", [$dplayer->getName()]));
			if($dplayer instanceof Player) {
				$dplayer->sendMessage($this->translateString("unbanplayer.success2", [$plot->X, $plot->Z, $sender->getName()]));
			}
		}else{
			$sender->sendMessage(TextFormat::RED . $this->translateString("error"));
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
		if($player !== null and $this->getPlugin()->getPlotByPosition($player->getPosition()) instanceof Plot)
			return new UnBanPlayerForm();
		return null;
	}
}