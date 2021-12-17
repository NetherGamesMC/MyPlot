<?php

declare(strict_types=1);

namespace MyPlot\command;

use MyPlot\MyPlot;
use NetherGames\NGEssentials\lang\BaseLang;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationKeys;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

abstract class BaseCommand extends Command{

	public static function registerCommands(MyPlot $plugin) : void {
		$plugin->getServer()->getCommandMap()->registerAll("myplot", [
			new ClearEntitiesCommand(),
			new CreativeDimensionCommand(),
			new MegaCreativeCommand(),
			new PlatinumPlotsCommand(),
			new TphereCommand(),
			new TptoCommand(),
			new VanishCommand(),
			new ClearInventoryCommand()
		]);
	}

	public function getPlugin() : MyPlot {
		return MyPlot::getInstance();
	}
}