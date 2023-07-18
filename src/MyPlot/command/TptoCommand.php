<?php
declare(strict_types=1);

namespace MyPlot\command;

use NetherGames\NGEssentials\player\permissions\Permissions;
use NetherGames\NGEssentials\player\Translator;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use function count;

class TptoCommand extends BaseCommand
{
    /** @var array */
    private $requests = [];

    public function __construct()
    {
        parent::__construct('tpto');

        $this->setPermission(Permissions::DEFAULT_COMMAND_PERMISSION);
        $this->setDescription('Command used for sending and accepting teleport requests');
        $this->setUsage('§cUsage: /tpto <accept {player} | decline {player} | {player}>');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if ($sender instanceof Player) {
            if (count($args) === 0) {
                throw new InvalidCommandSyntaxException();
            }

            if ($args[0] === 'a' || $args[0] === 'accept') {
                if (isset($args[1])) {
                    if (($player = $this->getPlugin()->getServer()->getPlayerExact($args[1])) instanceof Player) {
                        if (isset($this->requests[$sender->getName()][$player->getName()])) {
                            $player->teleport($sender->getPosition());
                            Translator::sendMessage($sender, "command.tpto.accepted.receiver", Translator::TYPE_SUCCESS, ...["sender" => $player->getName()]);
                            Translator::sendMessage($sender, "command.tpto.accepted.sender", Translator::TYPE_SUCCESS, ...["receiver" => $sender->getName()]);
                            unset($this->requests[$sender->getName()][$player->getName()]);
                        } else {
                            Translator::sendMessage($sender, "command.tpto.norequest", Translator::TYPE_ERROR);
                        }
                    } else {
                        Translator::sendMessage($sender, "player.offline", Translator::TYPE_ERROR);
                    }
                } else {
                    Translator::sendMessage($sender, "command.tp.specify", Translator::TYPE_ERROR);
                }
            } elseif ($args[0] === 'd' || $args[0] === 'decline') {
                if (isset($args[1])) {
                    if (($player = $this->getPlugin()->getServer()->getPlayerExact($args[1])) instanceof Player) {
                        if (isset($this->requests[$sender->getName()][$player->getName()])) {
                            Translator::sendMessage($sender, "command.tpto.declined.receiver", Translator::TYPE_INFO, ...["sender" => $player->getName()]);
                            Translator::sendMessage($player, "command.tpto.declined.sender", Translator::TYPE_INFO, ...["receiver" => $sender->getName()]);
                            unset($this->requests[$sender->getName()][$player->getName()]);
                        } else {
                            Translator::sendMessage($sender, "command.tpto.norequest", Translator::TYPE_ERROR);
                        }
                    } else {
                        Translator::sendMessage($sender, "player.offline", Translator::TYPE_ERROR);
                    }
                } else {
                    Translator::sendMessage($sender, "command.tp.specify", Translator::TYPE_ERROR);
                }
            } elseif (($player = $this->getPlugin()->getServer()->getPlayerExact($args[0])) instanceof Player) {
                if ($sender->hasPermission(Permissions::RANK_EMERALD)) {
                    $this->requests[$player->getName()][$sender->getName()] = $sender->getName();
                    Translator::sendMessage($sender, "command.tpto.send", Translator::TYPE_SUCCESS, ...["receiver" => $player->getName()]);
                    Translator::sendMessage($player, "command.tpto.receive", Translator::TYPE_INFO, ...["sender" => $sender->getName()]);
                } else {
                    Translator::sendMessage($sender, "command.tpto.noperm", Translator::TYPE_ERROR);
                }
            } else {
                throw new InvalidCommandSyntaxException();
            }
        } else {
            $sender->sendMessage($this->getPlugin()->getEssentials()->getPrefix() . '§cThat command can only be run in-game.');
        }

        return true;
    }

}