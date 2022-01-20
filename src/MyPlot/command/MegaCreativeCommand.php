<?php
declare(strict_types=1);

namespace MyPlot\command;

use MyPlot\forms\MainForm;
use NetherGames\NGEssentials\lang\BaseLang;
use NetherGames\NGEssentials\player\permissions\Permissions;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class MegaCreativeCommand extends BaseCommand{
	public function __construct() {
		parent::__construct('megacreative');

		$this->setAliases(['mc']);
		$this->setDescription('Command used for teleporting to Mega Creative');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if($sender instanceof Player) {
			if($sender->hasPermission(Permissions::RANK_VOTER)) {
				$sender->teleport($this->getPlugin()->getServer()->getWorldManager()->getWorldByName('MEGA')->getSafeSpawn());
				$form = new MainForm($sender, $this->getPlugin()->getCommands()->getCommands());
				$form->sendForm();
			}else{
				$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.megac.noperm'));

			}
		}else{
			$sender->sendMessage($this->getPlugin()->getEssentials()->getPrefix() . '§cThat command can only be run in-game.');
		}

		return true;
	}

}