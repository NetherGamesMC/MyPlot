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
	/** @var string */
	private $permissionMessage;

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

	/**
	 * @param CommandSender $target
	 *
	 * @return bool
	 */
	public function testPermission(CommandSender $target, ?string $permission = null) : bool {
		if($this->testPermissionSilent($target, $permission)) {
			return true;
		}

		if($this->permissionMessage === null) {
			$target->sendMessage($target->getServer()->getLanguage()->translateString(TextFormat::RED . KnownTranslationKeys::COMMANDS_GENERIC_PERMISSION));
		}elseif($this->permissionMessage !== ''){
			if($target instanceof Player) {
				$target->sendMessage(BaseLang::translateStringPlayer($target, $this->permissionMessage));
			}else{
				$target->sendMessage($target->getServer()->getLanguage()->translateString(TextFormat::RED . KnownTranslationKeys::COMMANDS_GENERIC_PERMISSION));
			}
		}

		return false;
	}

	public function getPlugin() : MyPlot {
		return MyPlot::getInstance();
	}
}