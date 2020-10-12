<?php
declare(strict_types=1);

namespace MyPlot\forms\subforms;

use libforms\elements\Input;
use libforms\elements\Slider;
use libforms\elements\Toggle;
use MyPlot\forms\ComplexMyPlotForm;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\block\BlockLegacyIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class GenerateForm extends ComplexMyPlotForm{

	public function __construct() {
		$plugin = MyPlot::getInstance();

		$elements = [
			"world" => new Input($plugin->getLanguage()->get("generate.formworld"), "plots"),
			"generator" => new Input($plugin->getLanguage()->get("generate.formgenerator"), "", "myplot")
		];

		foreach($plugin->getConfig()->get("DefaultWorld", []) as $key => $value){
			if(is_numeric($value)) {
				if($value > 0) {
					$slider = new Slider($key, 1, 4 * (int)$value);
					$slider->setStep(1);
					$slider->setDefault((int)$value);
					$elements[$key] = $slider;
				}else{
					$slider = new Slider($key, 1, 1000, null);
					$slider->setStep(1);
					$slider->setDefault(1);
					$elements[$key] = $slider;
				}
			}elseif(is_bool($value)){
				$elements[$key] = new Toggle($key, $value);
			}elseif(is_string($value)){
				$elements[$key] = new Input($key, "", $value);
			}
		}

		$elements["teleport"] = new Toggle($plugin->getLanguage()->get("generate.formteleport"));

		parent::__construct(
			null,
			TextFormat::BLACK . $plugin->getLanguage()->translateString("form.header", [$plugin->getLanguage()->get("generate.form")]),
			$elements,
			function(Player $player, array $data) use ($plugin) {
				$world = array_shift($data);
				if($player->getServer()->getWorldManager()->isWorldGenerated($world)) {
					$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("generate.exists", [$world]));
					return;
				}

				$teleport = array_pop($data);
				$data = array_map(
					function($val) {
						if(!is_string($val)) {
							return $val;
						}

						if(strpos($val, ':') !== false) {
							$pieces = explode(':', $val);
							if(defined(BlockLegacyIds::class . "::" . strtoupper(str_replace(' ', '_', $pieces[0])))) {
								return constant(BlockLegacyIds::class . "::" . strtoupper(str_replace(' ', '_', $val))) . ':' . ($pieces[1] ?? 0);
							}

							return $val;
						}elseif(is_numeric($val)){
							return $val . ':0';
						}elseif(defined(BlockLegacyIds::class . "::" . strtoupper(str_replace(' ', '_', $val)))){
							return constant(BlockLegacyIds::class . "::" . strtoupper(str_replace(' ', '_', $val))) . ':0';
						}
						return $val;
					},
					$data
				);

				if($plugin->generateLevel($world, array_shift($data), $data)) {
					if($teleport) {
						$plugin->teleportPlayerToPlot($player, new Plot($world, 0, 0));
					}

					$player->sendMessage($plugin->getLanguage()->translateString("generate.success", [$world]));
				}else{
					$player->sendMessage(TextFormat::RED . $plugin->getLanguage()->translateString("generate.error"));
				}
			}
		);
	}

    public function getName(): string
    {
        return "Generate";
    }
}