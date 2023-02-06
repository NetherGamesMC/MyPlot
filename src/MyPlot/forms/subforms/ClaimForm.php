<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\forms\interfaces\PlotButtonForm;
use MyPlot\MyPlot;
use MyPlot\Plot;
use NetherGames\NGEssentials\player\permissions\Permissions;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function count;
use function strtolower;

class ClaimForm extends ComplexMyPlotForm implements PlotButtonForm
{

    public function __construct(Player $player, Plot $plot)
    {
        $plugin = MyPlot::getInstance();
        $this->setPlayer($player);
        $this->setPlot($plot);
        parent::__construct(
            $player,
            TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("claim.form")]),
            []
        );
    }

    public function onButtonClick(Player $player): void
    {
        $plugin = MyPlot::getInstance();
        $plot = $this->plot;

        if ($plot === null) {
            $player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString('notinplot'));
        } else if ($plot->owner !== '') {
            if (strtolower($plot->owner) === strtolower($player->getName())) {
                $player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString('claim.yourplot'));
            } else {
                $player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString('claim.alreadyclaimed', [$plot->owner]));
            }
        } else if ($player->getWorld()->getFolderName() === 'Platinum' && MyPlot::essentialsExists() && !$player->hasPermission(Permissions::RANK_ULTRA)) {
            $player->sendMessage('§cThat action is blocked for you in this world.');
        } else {
            $maxPlots = $plugin->getMaxPlotsOfPlayer($player);
            $plotsOfPlayer = count($plugin->getProvider()->getPlotsByOwner($player->getName(), $player->getWorld()->getFolderName()));
            if ($plotsOfPlayer >= $maxPlots) {
                $player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString('claim.maxplots', [$maxPlots]));
            } else {
                if ($plugin->claimPlot($plot, $player->getName())) {
                    $player->sendMessage($plugin->getLanguage()->translateString("claim.success"));
                } else {
                    $player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("error"));
                }
            }
        }
    }

    public function getName(): string
    {
        $plugin = MyPlot::getInstance();
        $player = $this->getPlayer();
        $plot = $this->plot;

        if ($plot === null) {
            $name = "§6Claim: §cNot standing inside a plot";
        } else if ($plot->owner !== "") {
            if (strtolower($plot->owner) === strtolower($player->getName())) {
                $name = "§6Claim: §bYou own this plot";
            } else {
                $name = "§6Claim: §cPlot already claimed";
            }
        } else {
            $maxPlots = $plugin->getMaxPlotsOfPlayer($player);
            $plotsOfPlayer = count($plugin->getProvider()->getPlotsByOwner($player->getName(), $player->getWorld()->getFolderName()));
            if ($plotsOfPlayer >= $maxPlots) {
                $name = "§6Claim: §cReached your max plots";
            } else {
                $name = "§6Claim: §aAvailable";
            }
        }

        return $name;
    }
}