<?php
declare(strict_types=1);

namespace MyPlot\command;

use MyPlot\forms\MainForm;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class CreativeDimensionCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct('creativedimension');

        $this->setAliases(['cd']);
        $this->setDescription('Command used for teleporting to Creative Dimension');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if ($sender instanceof Player) {
            $sender->teleport($this->getPlugin()->getServer()->getWorldManager()->getWorldByName('Creative')->getSafeSpawn());
            $form = new MainForm($sender, $this->getPlugin()->getCommands()->getCommands());
            $form->sendForm();
        } else {
            $sender->sendMessage($this->getPlugin()->getEssentials()->getPrefix() . '§cThat command can only be run in-game.');
        }

        return true;
    }
}