<?php
declare(strict_types=1);

namespace MyPlot\command;

use pocketmine\player\Player;
use NetherGames\NGEssentials\lang\BaseLang;
use NetherGames\NGEssentials\player\PlayerData;
use pocketmine\command\CommandSender;

class VanishCommand extends BaseCommand
{

    public function __construct()
    {
        parent::__construct('vanish');

        //$this->setPermission('nethergames.vip.legend');todo uncomment
        $this->setPermissionMessage('command.vanish.noperm');
        $this->setDescription('Command used for making yourself vanish for Legend players');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if ($sender instanceof Player) {
            if (!$this->testPermission($sender)) {
                return true;
            }

            if (!$this->getPlugin()->getEssentials()->getPlayerData()->getBool($sender, PlayerData::VANISH)) {
                $this->getPlugin()->getEssentials()->getPlayerData()->setValue($sender, PlayerData::VANISH, true);
                foreach ($sender->getServer()->getOnlinePlayers() as $player) {
                    $player->hidePlayer($sender);
                }
                $sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.vanish.enabled'));
            } else {
                $this->getPlugin()->getEssentials()->getPlayerData()->setValue($sender, PlayerData::VANISH, false);
                foreach ($sender->getServer()->getOnlinePlayers() as $player) {
                    $player->showPlayer($sender);
                }
                $sender->sendMessage(BaseLang::translateStringPlayer($sender, 'command.vanish.disabled'));
            }
        } else {
            $sender->sendMessage($this->getPlugin()->getEssentials()->getPrefix() . '§cThat command can only be run in-game.');
        }

        return true;
    }

}