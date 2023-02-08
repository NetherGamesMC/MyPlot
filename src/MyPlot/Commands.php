<?php
declare(strict_types=1);

namespace MyPlot;

use MyPlot\forms\MainForm;
use MyPlot\subcommand\AddHelperSubCommand;
use MyPlot\subcommand\ArchiveSubCommand;
use MyPlot\subcommand\AutoSubCommand;
use MyPlot\subcommand\BanPlayerSubCommand;
use MyPlot\subcommand\BiomeSubCommand;
use MyPlot\subcommand\ClaimSubCommand;
use MyPlot\subcommand\ClearSubCommand;
use MyPlot\subcommand\DisposeSubCommand;
use MyPlot\subcommand\FillSubCommand;
use MyPlot\subcommand\GenerateSubCommand;
use MyPlot\subcommand\GiveSubCommand;
use MyPlot\subcommand\HelpSubCommand;
use MyPlot\subcommand\HomesSubCommand;
use MyPlot\subcommand\HomeSubCommand;
use MyPlot\subcommand\InfoSubCommand;
use MyPlot\subcommand\KickSubCommand;
use MyPlot\subcommand\ListSubCommand;
use MyPlot\subcommand\MiddleSubCommand;
use MyPlot\subcommand\NameSubCommand;
use MyPlot\subcommand\PvpSubCommand;
use MyPlot\subcommand\RemoveHelperSubCommand;
use MyPlot\subcommand\ResetSubCommand;
use MyPlot\subcommand\SetOwnerSubCommand;
use MyPlot\subcommand\SubCommand;
use MyPlot\subcommand\TimeSubCommand;
use MyPlot\subcommand\UnBanSubCommand;
use MyPlot\subcommand\WarpSubCommand;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use pocketmine\utils\TextFormat;

class Commands extends Command implements PluginOwned
{
    use PluginOwnedTrait;

    /** @var SubCommand[] $subCommands */
    private array $subCommands = [];
    /** @var SubCommand[] $aliasSubCommands */
    private array $aliasSubCommands = [];

    /**
     * Commands constructor.
     *
     * @param MyPlot $plugin
     */
    public function __construct(MyPlot $plugin)
    {
        parent::__construct($plugin->getLanguage()->get("command.name"), $plugin->getLanguage()->get("command.desc"), $plugin->getLanguage()->get("command.usage"), [$plugin->getLanguage()->get("command.alias")]);
        $this->setPermission("myplot.command");
        $this->owningPlugin = $plugin;

        $this->loadSubCommand(new HelpSubCommand($plugin, "help", $this));
        $this->loadSubCommand(new WarpSubCommand($plugin, "warp"));
        $this->loadSubCommand(new ClaimSubCommand($plugin, "claim"));
        $this->loadSubCommand(new AutoSubCommand($plugin, "auto"));
        $this->loadSubCommand(new TimeSubCommand($plugin, "time"));
        $this->loadSubCommand(new GenerateSubCommand($plugin, "generate"));
        $this->loadSubCommand(new InfoSubCommand($plugin, "info"));
        $this->loadSubCommand(new NameSubCommand($plugin, "name"));
        $this->loadSubCommand(new BiomeSubCommand($plugin, "biome"));
        $this->loadSubCommand(new MiddleSubCommand($plugin, "middle"));
        $this->loadSubCommand(new AddHelperSubCommand($plugin, "addhelper"));
        $this->loadSubCommand(new RemoveHelperSubCommand($plugin, "removehelper"));
        $this->loadSubCommand(new HomeSubCommand($plugin, "home"));
        $this->loadSubCommand(new HomesSubCommand($plugin, "homes"));
        $this->loadSubCommand(new ArchiveSubCommand($plugin, "archive"));
        $this->loadSubCommand(new DisposeSubCommand($plugin, "dispose"));
        $this->loadSubCommand(new GiveSubCommand($plugin, "give"));
        $this->loadSubCommand(new ClearSubCommand($plugin, "clear"));
        $this->loadSubCommand(new ResetSubCommand($plugin, "reset"));
        $this->loadSubCommand(new BanPlayerSubCommand($plugin, "banplayer"));
        $this->loadSubCommand(new UnBanSubCommand($plugin, "unbanplayer"));
        $this->loadSubCommand(new SetOwnerSubCommand($plugin, "setowner"));
        $this->loadSubCommand(new ListSubCommand($plugin, "list"));
        $this->loadSubCommand(new PvpSubCommand($plugin, "pvp"));
        $this->loadSubCommand(new KickSubCommand($plugin, "kick"));
        $this->loadSubCommand(new FillSubCommand($plugin, "fill"));

        $plugin->getLogger()->debug("Commands Registered to MyPlot");
    }

    public function loadSubCommand(SubCommand $command): void
    {
        $this->subCommands[$command->getName()] = $command;
        if ($command->getAlias() != "") {
            $this->aliasSubCommands[$command->getAlias()] = $command;
        }
    }

    /**
     * @return SubCommand[]
     */
    public function getCommands(): array
    {
        return $this->subCommands;
    }

    public function unloadSubCommand(string $name): void
    {
        $subcommand = $this->subCommands[$name] ?? $this->aliasSubCommands[$name] ?? null;
        if ($subcommand !== null) {
            unset($this->subCommands[$subcommand->getName()]);
            unset($this->aliasSubCommands[$subcommand->getAlias()]);
        }
    }

    /**
     * @param CommandSender $sender
     * @param string $alias
     * @param string[] $args
     *
     * @return bool
     */
    public function execute(CommandSender $sender, string $alias, array $args): bool
    {
        /** @var MyPlot $plugin */
        $plugin = $this->getOwningPlugin();
        if ($plugin->isDisabled()) {
            $sender->sendMessage($plugin->getLanguage()->get("plugin.disabled"));
            return true;
        }
        if (!isset($args[0])) {
            $args[0] = "help";
            if ($sender instanceof Player and $plugin->getConfig()->get("UI Forms", true)) {
                $form = new MainForm($sender, $this->subCommands);
                $form->sendForm();
                return true;
            }
        }
        $subCommand = strtolower((string)array_shift($args));
        if (isset($this->subCommands[$subCommand])) {
            $command = $this->subCommands[$subCommand];
        } elseif (isset($this->aliasSubCommands[$subCommand])) {
            $command = $this->aliasSubCommands[$subCommand];
        } else {
            $sender->sendMessage(TextFormat::RED . $plugin->getLanguage()->get("command.unknown"));
            return true;
        }
        if ($command->canUse($sender)) {
            if (!$command->execute($sender, $args)) {
                $usage = $plugin->getLanguage()->translateString("subcommand.usage", [$command->getUsage()]);
                $sender->sendMessage($usage);
            }
        } else {
            $sender->sendMessage(TextFormat::RED . $plugin->getLanguage()->get("command.unknown"));
        }
        return true;
    }

    public function getOwningPlugin(): Plugin
    {
        return MyPlot::getInstance();
    }
}