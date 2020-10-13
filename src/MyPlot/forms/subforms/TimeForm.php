<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Button;
use libforms\elements\ImageButton;
use MyPlot\forms\SimpleMyPlotForm;
use MyPlot\MyPlot;
use pocketmine\network\mcpe\protocol\SetTimePacket;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use function array_search;
use function in_array;

class TimeForm extends SimpleMyPlotForm{

	public function __construct(Player $player) {
		$plugin = MyPlot::getInstance();

        $elements = [
            new Button("Sunrise", function (Player $player){
                $this->setTime($player, World::TIME_SUNRISE);
            }),
            new ImageButton("Day", ImageButton::IMAGE_TYPE_URL, "https://minecraft.gamepedia.com/media/minecraft.gamepedia.com/6/61/Sun.png", function (Player $player){
                $this->setTime($player, World::TIME_DAY);
            }),
            new Button("Sunset", function (Player $player){
                $this->setTime($player, World::TIME_SUNSET);
            }),
            new ImageButton("Night", ImageButton::IMAGE_TYPE_URL, "https://minecraft.gamepedia.com/media/minecraft.gamepedia.com/4/47/Moon.png?version=ff9a299fcaa80c1f3eaf244ea00360b9", function (Player $player){
                $this->setTime($player, World::TIME_NIGHT);
            })
        ];

		parent::__construct(
			$player,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", ["Time Settings"]),
			"",
			$elements
		);
	}

    private function setTime(Player $player, int $time): void
    {
        $plugin = MyPlot::getInstance();

        if (in_array($player->getName(), $plugin->stopTime, true)) {
            $index = array_search($player->getName(), $plugin->stopTime, true);
            unset($plugin->stopTime[$index]);
        }

        $pk = new SetTimePacket();
        $pk->time = $time;
        $player->getNetworkSession()->sendDataPacket($pk);

        $plugin->stopTime[] = $player->getName();
    }

    public function getName(): string
    {
        return "Edit time";
    }
}