<?php
declare(strict_types=1);

namespace MyPlot\command;

use MyPlot\forms\MainForm;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class PlatinumPlotsCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct('platinumplots');

        $this->setAliases(['pp']);
        $this->setDescription('Command used for teleporting to Platinum Plots');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if ($sender instanceof Player) {
            $sender->teleport($this->getPlugin()->getServer()->getWorldManager()->getWorldByName('Platinum')->getSafeSpawn());
            $form = new MainForm($sender, $this->getPlugin()->getCommands()->getCommands());
            $form->sendForm();
        } else {
            $sender->sendMessage($this->getPlugin()->getEssentials()->getPrefix() . '§cThat command can only be run in-game.');
        }

        return true;
    }

}