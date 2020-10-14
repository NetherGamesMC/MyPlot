<?php
declare(strict_types=1);

namespace MyPlot\command;

use NetherGames\NGEssentials\lang\BaseLang;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class ClearInventoryCommand extends BaseCommand{

	public function __construct() {
		parent::__construct('clearinventory');

		$this->setAliases(['ci']);
		//$this->setPermission('nethergames.vip.legend');todo uncomment
		$this->setPermissionMessage('command.ci.noperm');
		$this->setDescription('Command used for clearing your inventory for Legend players');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if($sender instanceof Player) {
			if(!$this->testPermission($sender)) {
				return true;
			}

			$sender->getInventory()->clearAll();
			$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.ci.completed'));
		}else{
			$sender->sendMessage($this->getPlugin()->getEssentials()->getPrefix() . '§cThat command can only be run in-game.');
		}

		return true;
	}

}