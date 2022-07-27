<?php
declare(strict_types=1);

namespace MyPlot\subcommand;

use MyPlot\forms\interfaces\MyPlotForm;
use MyPlot\forms\subforms\AutoForm;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function strtolower;

class AutoSubCommand extends SubCommand
{
	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.auto");
	}

	/**
	 * @param Player $sender
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, array $args) : bool {
        $levelName = $sender->getWorld()->getFolderName();
        if(!$this->plugin->isLevelLoaded($levelName)) {
            $sender->sendMessage(TextFormat::RED . $this->translateString("auto.notplotworld"));
            return true;
        }
		if(($plot = $this->getPlugin()->getNextFreePlot($levelName)) !== null) {
			$this->getPlugin()->teleportPlayerToPlot($sender, $plot, true, function() use ($sender, $plot, $args) : void {
				$sender->sendMessage($this->translateString("auto.success", [$plot->X, $plot->Z]));
				$cmd = new ClaimSubCommand($this->plugin, "claim");
				if(isset($args[0]) and strtolower($args[0]) == "true" and $cmd->canUse($sender)) {
					$cmd->execute($sender, isset($args[1]) ? [$args[1]] : []);
				}
			}, function() use ($sender) : void {
				$sender->sendMessage(TextFormat::RED . $this->translateString("error"));
			});
		}else{
			$sender->sendMessage(TextFormat::RED . $this->translateString("auto.noplots"));
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
		return $player !== null ?  new AutoForm($player) : null;
	}
}