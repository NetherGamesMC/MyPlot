<?php
declare(strict_types=1);

namespace MyPlot\subcommand;

use MyPlot\forms\interfaces\MyPlotForm;
use MyPlot\forms\subforms\TimeForm;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\SetTimePacket;
use pocketmine\player\Player;

class TimeSubCommand extends SubCommand
{
    public function canUse(CommandSender $sender): bool
    {
        return ($sender instanceof Player);
    }

    public function execute(CommandSender $sender, array $args): bool
    {
        if(!$sender instanceof Player){
            return true;
        }
        if ($sender->hasPermission('nethergames.tier.platinum') || $sender->hasPermission('nethergames.vip.legend')) {
            if (count($args) !== 1) {
                return false;
            }
            if ($args[0] === 'day') {
                $value = 0;
            } elseif ($args[0] === 'night') {
                $value = 14000;
            } else {
                $value = $this->getInteger($args[0], 0);
            }
            $this->setTime($sender, $value);
        } else {
            $sender->sendMessage("§cYou don't have permission to change the time for your plot. Buy the §l§bLEGEND §r§crank at §bngmc.co/store §cto change it!");
        }
        return true;
    }

    private function getInteger($value, int $min = 30000000, int $max = -30000000): int
    {
        $i = (int)$value;
        if ($i < $min) {
            $i = $min;
        } elseif ($i > $max) {
            $i = $max;
        }
        return $i;
    }

    private function setTime(Player $player, int $time): void
    {
        if (in_array($player->getName(), $this->getPlugin()->stopTime, true)) {
            $index = array_search($player->getName(), $this->getPlugin()->stopTime, true);
            unset($this->getPlugin()->stopTime[$index]);
        }

        $pk = new SetTimePacket();
        $pk->time = $time;
        $player->getNetworkSession()->sendDataPacket($pk);

        $this->getPlugin()->stopTime[] = $player->getName();
    }

    public function getForm(?Player $player = null): ?MyPlotForm
    {
        if (!$player->hasPermission('nethergames.tier.platinum') || !$player->hasPermission('nethergames.vip.legend')) {
            $player->sendMessage("§cYou don't have permission to change the time for your plot. Buy the §l§bLEGEND §r§crank at §bngmc.co/store §cto change it!");
            return null;
        }

        return new TimeForm($player);
    }
}