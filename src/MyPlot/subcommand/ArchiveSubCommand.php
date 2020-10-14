<?php

declare(strict_types=1);

namespace MyPlot\subcommand;

use MyPlot\forms\interfaces\MyPlotForm;
use MyPlot\forms\subforms\ArchiveForm;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class ArchiveSubCommand extends SubCommand{

	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) && $sender->hasPermission('myplot.command.archive');
	}

	public function execute(CommandSender $sender, array $args) : bool {
		$confirm = (count($args) === 1 && $args[0] === $this->translateString('confirm'));
		if(count($args) !== 0 && !$confirm) {
			return false;
		}

		$player = $sender->getServer()->getPlayer($sender->getName());
		$plot = $this->getPlugin()->getPlotByPosition($player->getPosition());
		if($plot === null) {
			$sender->sendMessage(TextFormat::RED . $this->translateString('notinplot'));
			return true;
		}
		if($plot->owner !== $sender->getName()) {
			$sender->sendMessage(TextFormat::RED . $this->translateString('notowner'));
			return true;
		}

		if($confirm) {
			$plot->name = TextFormat::GREEN . $sender->getName() . "'s Archived Plot";
			$plot->owner = 'NetherGamesMC';
			$plot->helpers = [];
			$plot->banned = [];
			if($this->getPlugin()->getProvider()->savePlot($plot)) {
				$sender->sendMessage($this->translateString('archive.success'));
			}else{
				$sender->sendMessage(TextFormat::RED . $this->translateString('error'));
			}
		}else{
			$plotId = TextFormat::GREEN . $plot . TextFormat::WHITE;
			$sender->sendMessage($this->translateString('archive.confirm', [$plotId]));
		}
		return true;
	}


	public function getForm(Player $player) : ?MyPlotForm {
		return new ArchiveForm();
	}
}
