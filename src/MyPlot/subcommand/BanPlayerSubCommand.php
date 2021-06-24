<?php
declare(strict_types=1);

namespace MyPlot\subcommand;

use MyPlot\forms\interfaces\MyPlotForm;
use MyPlot\forms\subforms\BanPlayerForm;
use MyPlot\Plot;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class BanPlayerSubCommand extends SubCommand{
	/**
	 * @param CommandSender $sender
	 *
	 * @return bool
	 */
	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.banplayer") and $sender->hasPermission("nethergames.vip.legend");
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
		if(!$sender->hasPermission('nethergames.vip.legend')) {
			$sender->sendMessage("§cYou don't have permission to ban other players from accessing your plot. Buy the §l§bLEGEND §r§crank at §bngmc.co/store §cto ban them!");
			return true;
		}
		$dplayer = $args[0];
		$plot = $this->getPlugin()->getPlotByPosition($sender->getPosition());
		if($plot === null) {
			$sender->sendMessage(TextFormat::RED . $this->translateString("notinplot"));
			return true;
		}
		if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.banplayer")) {
			$sender->sendMessage(TextFormat::RED . $this->translateString("notowner"));
			return true;
		}
		if($dplayer === "*") {
			if($this->getPlugin()->addPlotDenied($plot, $dplayer)) {
				$sender->sendMessage($this->translateString("banplayer.success1", [$dplayer]));
				foreach($this->getPlugin()->getServer()->getOnlinePlayers() as $player) {
					if($this->getPlugin()->getPlotBB($plot)->isVectorInside($player->getPosition()) and !($player->getName() === $plot->owner) and !$player->hasPermission("myplot.admin.banplayer.bypass") and !$plot->isHelper($player->getName()))
						$this->getPlugin()->teleportPlayerToPlot($player, $plot);
					else {
						$sender->sendMessage($this->translateString("banplayer.cannotban", [$player->getName()]));
						$player->sendMessage($this->translateString("banplayer.attemptedban", [$sender->getName()]));
					}
				}
			}else{
				$sender->sendMessage(TextFormat::RED . $this->translateString("error"));
			}
			return true;
		}
		$dplayer = $this->getPlugin()->getServer()->getPlayerByPrefix($dplayer);
		if(!$dplayer instanceof Player) {
			$sender->sendMessage($this->translateString("banplayer.notaplayer"));
			return true;
		}
		if($dplayer->hasPermission("myplot.admin.banplayer.bypass") or $dplayer->getName() === $plot->owner) {
			$sender->sendMessage($this->translateString("banplayer.cannotban", [$dplayer->getName()]));
			$dplayer->sendMessage($this->translateString("banplayer.attemptedban", [$sender->getName()]));
			return true;
		}
		if($this->getPlugin()->addPlotDenied($plot, $dplayer->getName())) {
			$sender->sendMessage($this->translateString("banplayer.success1", [$dplayer->getName()]));
			$dplayer->sendMessage($this->translateString("banplayer.success2", [$plot->X, $plot->Z, $sender->getName()]));
			if($this->getPlugin()->getPlotBB($plot)->isVectorInside($dplayer->getPosition()))
				$this->getPlugin()->teleportPlayerToPlot($dplayer, $plot);
		}else{
			$sender->sendMessage(TextFormat::RED . $this->translateString("error"));
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
		if($player !== null and ($plot = $this->getPlugin()->getPlotByPosition($player->getPosition())) instanceof Plot)
			return new BanPlayerForm($plot);
		return null;
	}
}