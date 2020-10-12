<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Input;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\MyPlot;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function count;
use function strtolower;

class ClaimForm extends ComplexMyPlotForm{

	/** @var Player $player */
	private $player;

	public function __construct(Player $player) {
		$plugin = MyPlot::getInstance();
		$this->player = $player;
		$plot = $plugin->getPlotByPosition($player->getPosition());
		if($plot === null) {
			$plot = new \stdClass();
			$plot->X = "";
			$plot->Z = "";
		}
		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("claim.form")]),
			[
				new Input($plugin->getLanguage()->get("claim.formxcoord"), "2", (string)$plot->X),
				new Input($plugin->getLanguage()->get("claim.formzcoord"), "2", (string)$plot->Z),
				new Input($plugin->getLanguage()->get("claim.formworld"), "world", $player->getWorld()->getFolderName())
			],
			function(Player $player, array $data) use ($plugin) : void {

				if(is_numeric($data[0]) and is_numeric($data[1])) {
					$data = MyPlot::getInstance()->getProvider()->getPlot(
						empty($data[2]) ? $this->player->getWorld()->getFolderName() : $data[2],
						(int)$data[0],
						(int)$data[1]
					);
				}elseif(empty($data[0]) or empty($data[1])){
					$plot = MyPlot::getInstance()->getPlotByPosition($this->player->getPosition());
					if($plot === null) {
						$this->player->sendForm(new self($this->player));
						throw new FormValidationException("Unexpected form data returned");
					}
					$data = $plot;
				}else{
					throw new FormValidationException("Unexpected form data returned");
				}

				if($data->owner != "") {
					if($data->owner === $player->getName()) {
						$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("claim.yourplot"));
					}else{
						$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("claim.alreadyclaimed", [$data->owner]));
					}
					return;
				}
				$maxPlots = $plugin->getMaxPlotsOfPlayer($player);
				$plotsOfPlayer = 0;
				foreach($plugin->getPlotLevels() as $level => $settings){
					$level = $plugin->getServer()->getWorldManager()->getWorldByName((string)$level);
					if(!$level->isClosed()) {
						$plotsOfPlayer += count($plugin->getPlotsOfPlayer($player->getName(), $level->getFolderName()));
					}
				}
				if($plotsOfPlayer >= $maxPlots) {
					$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("claim.maxplots", [$maxPlots]));
					return;
				}
				$plotLevel = $plugin->getLevelSettings($data->levelName);
				$economy = $plugin->getEconomyProvider();
				if($economy !== null and !$economy->reduceMoney($player, $plotLevel->claimPrice)) {
					$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("claim.nomoney"));
					return;
				}
				if($plugin->claimPlot($data, $player->getName())) {
					$player->sendMessage($plugin->getLanguage()->translateString("claim.success"));
				}else{
					$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("error"));
				}
			}
		);
	}

	public function getName(): string
    {
        $plugin = MyPlot::getInstance();
        $player = $this->player;
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
                $plotLevel = $plugin->getLevelSettings($plot->levelName);
                $economy = $plugin->getEconomyProvider();
                if ($economy !== null && !$economy->reduceMoney($player, $plotLevel->claimPrice)) {
                    $name = "§6Claim: §cNot enough money";
                } else {
                    $name = "§6Claim: §aAvailable";
                }
            }
        }

        return $name;
    }
}