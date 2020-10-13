<?php
declare(strict_types=1);

namespace MyPlot\command;

use MyPlot\task\CleanEntitiesTask;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class ClearEntitiesCommand extends BaseCommand{

	public function __construct() {
		parent::__construct('ce');

		//$this->setPermission('nethergames.executive');todo uncomment
		$this->setPermissionMessage('command.reserved.estaff');
		$this->setDescription('Command used for clearing unnecessary entities in worlds');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if($sender instanceof Player) {
			if(!$this->testPermission($sender)) {
				return true;
			}
			$this->getPlugin()->getScheduler()->scheduleDelayedTask(new CleanEntitiesTask($this->getPlugin()), 1);
		}else{
			$sender->sendMessage($this->getPlugin()->getEssentials()->getPrefix() . '§cThat command can only be run in-game.');
		}

		return true;
	}

}