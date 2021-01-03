<?php
declare(strict_types=1);

namespace MyPlot\command;

use NetherGames\NGEssentials\lang\BaseLang;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use function count;

class TptoCommand extends BaseCommand{
	/** @var array */
	private $requests = [];

	public function __construct() {
		parent::__construct('tpto');

		$this->setDescription('Command used for sending and accepting teleport requests');
		$this->setUsage('§cUsage: /tpto <accept {player} | decline {player} | {player}>');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if($sender instanceof Player) {
			if(count($args) === 0) {
				throw new InvalidCommandSyntaxException();
			}

			if($args[0] === 'a' || $args[0] === 'accept') {
				if(isset($args[1])) {
					if(($player = $this->getPlugin()->getServer()->getPlayerExact($args[1])) instanceof Player) {
						if(isset($this->requests[$sender->getName()][$player->getName()])) {
							$player->teleport($sender->getPosition());
							$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.tpto.accepted.receiver', array($player->getName())));
							$player->sendMessage(BaseLang::translateStringPlayer($player, 'command.tpto.accepted.sender', array($sender->getName())));
							unset($this->requests[$sender->getName()][$player->getName()]);
						}else{
							$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.tpto.norequest'));
						}
					}else{
						$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'player.offline'));
					}
				}else{
					$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.tp.specify'));
				}
			}elseif($args[0] === 'd' || $args[0] === 'decline'){
				if(isset($args[1])) {
					if(($player = $this->getPlugin()->getServer()->getPlayerExact($args[1])) instanceof Player) {
						if(isset($this->requests[$sender->getName()][$player->getName()])) {
							$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.tpto.declined.receiver', array($player->getName())));
							$player->sendMessage(BaseLang::translateStringPlayer($sender, 'command.tpto.declined.sender', array($sender->getName())));
							unset($this->requests[$sender->getName()][$player->getName()]);
						}else{
							$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.tpto.norequest'));
						}
					}else{
						$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'player.offline'));
					}
				}else{
					$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.tp.specify'));
				}
			}elseif(($player = $this->getPlugin()->getServer()->getPlayerExact($args[0])) instanceof Player){
				if($sender->hasPermission('nethergames.vip.emerald')) {
					$this->requests[$player->getName()][$sender->getName()] = $sender->getName();
					$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.tpto.send', array($player->getName())));
					$player->sendMessage(BaseLang::translateStringPlayer($player, 'command.tpto.receive', array($sender->getName())));
				}else{
					$sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.tpto.noperm'));
				}
			}else{
				throw new InvalidCommandSyntaxException();
			}
		}else{
			$sender->sendMessage($this->getPlugin()->getEssentials()->getPrefix() . '§cThat command can only be run in-game.');
		}

		return true;
	}

}