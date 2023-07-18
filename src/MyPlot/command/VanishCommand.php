<?php
declare(strict_types=1);

namespace MyPlot\command;

use NetherGames\NGEssentials\player\permissions\Permissions;
use NetherGames\NGEssentials\player\PlayerData;
use NetherGames\NGEssentials\player\Translator;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class VanishCommand extends BaseCommand
{

    public function __construct()
    {
        parent::__construct('vanish');

        $this->setPermission(Permissions::RANK_LEGEND);
        $this->setPermissionMessage('command.vanish.noperm');
        $this->setDescription('Command used for making yourself vanish for Legend players');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if ($sender instanceof Player) {
            if (!$this->getPlugin()->getEssentials()->getPlayerData()->getBool($sender, PlayerData::VANISH)) {
                $this->getPlugin()->getEssentials()->getPlayerData()->setValue($sender, PlayerData::VANISH, true);
                foreach ($sender->getServer()->getOnlinePlayers() as $player) {
                    $player->hidePlayer($sender);
                }
                Translator::sendMessage($sender, "command.vanish.enabled", Translator::TYPE_SUCCESS);
            } else {
                $this->getPlugin()->getEssentials()->getPlayerData()->setValue($sender, PlayerData::VANISH, false);
                foreach ($sender->getServer()->getOnlinePlayers() as $player) {
                    $player->showPlayer($sender);
                }
                Translator::sendMessage($sender, "command.vanish.disabled", Translator::TYPE_SUCCESS);
            }
        } else {
            $sender->sendMessage($this->getPlugin()->getEssentials()->getPrefix() . '§cThat command can only be run in-game.');
        }

        return true;
    }

}