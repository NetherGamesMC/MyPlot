<?php

declare(strict_types=1);

namespace MyPlot\command;

use MyPlot\MyPlot;
use NetherGames\NGEssentials\lang\BaseLang;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;

abstract class BaseCommand extends Command
{
    /** @var string */
    private $permissionMessage;

    public static function registerCommands(MyPlot $plugin): void
    {
        $plugin->getServer()->getCommandMap()->registerAll("myplot", [
            new ClearEntitiesCommand(),
            new CreativeDimensionCommand(),
            new MegaCreativeCommand(),
            new PlatinumPlotsCommand(),
            new TphereCommand(),
            new TptoCommand(),
            new VanishCommand()
        ]);
    }

    /**
     * @param CommandSender $target
     *
     * @return bool
     */
    public function testPermission(CommandSender $target): bool
    {
        if ($this->testPermissionSilent($target)) {
            return true;
        }

        if ($this->permissionMessage === null) {
            $target->sendMessage($target->getServer()->getLanguage()->translateString(TextFormat::RED . '%commands.generic.permission'));
        } elseif ($this->permissionMessage !== '') {
            if ($target instanceof Player) {
                $target->sendMessage(BaseLang::translateStringPlayer($target, $this->permissionMessage));
            } else {
                $target->sendMessage($target->getServer()->getLanguage()->translateString(TextFormat::RED . '%commands.generic.permission'));
            }
        }

        return false;
    }

    public function getPlugin() : MyPlot {
        return MyPlot::getInstance();
    }
}