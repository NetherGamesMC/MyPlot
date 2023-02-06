<?php
declare(strict_types=1);

namespace MyPlot;

use Exception;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

class PlotLevelSettings
{
    public string $name;
    public Block $roadBlock;
    public Block $bottomBlock;
    public Block $plotFillBlock;
    public Block $plotFloorBlock;
    public Block $wallBlock;
    public int $roadWidth = 7;
    public int $plotSize = 32;
    public int $groundHeight = 64;
    public int $claimPrice = 0;
    public bool $restrictEntityMovement = true;
    public bool $restrictPVP = false;
    public bool $updatePlotLiquids = false;
    public bool $allowOutsidePlotSpread = false;
    public bool $displayDoneNametags = false;
    public bool $editBorderBlocks = true;

    /**
     * PlotLevelSettings constructor.
     *
     * @param string $name
     * @param mixed[] $settings
     */
    public function __construct(string $name, array $settings = [])
    {
        $this->name = $name;
        if (count($settings) > 0) {
            $this->roadBlock = self::parseBlock($settings, "RoadBlock", VanillaBlocks::OAK_PLANKS());
            $this->wallBlock = self::parseBlock($settings, "WallBlock", VanillaBlocks::SMOOTH_STONE_SLAB());
            $this->plotFloorBlock = self::parseBlock($settings, "PlotFloorBlock", VanillaBlocks::GRASS());
            $this->plotFillBlock = self::parseBlock($settings, "PlotFillBlock", VanillaBlocks::DIRT());
            $this->bottomBlock = self::parseBlock($settings, "BottomBlock", VanillaBlocks::BEDROCK());
            $this->roadWidth = self::parseNumber($settings, "RoadWidth", 7);
            $this->plotSize = self::parseNumber($settings, "PlotSize", 32);
            $this->groundHeight = self::parseNumber($settings, "GroundHeight", 64);
            $this->restrictEntityMovement = self::parseBool($settings, "RestrictEntityMovement", true);
            $this->restrictPVP = self::parseBool($settings, "RestrictPVP", false);
            $this->updatePlotLiquids = self::parseBool($settings, "UpdatePlotLiquids", false);
            $this->allowOutsidePlotSpread = self::parseBool($settings, "AllowOutsidePlotSpread", false);
            $this->editBorderBlocks = self::parseBool($settings, "EditBorderBlocks", true);
        }
    }

    /**
     * @param string[] $array
     * @param string|int $key
     * @param Block $default
     *
     * @return Block
     */
    public static function parseBlock(array $array, string|int $key, Block $default): Block
    {
        try {
            if (isset($array[$key])) {
                $id = $array[$key];
                if (is_numeric($id)) {
                    $blockState = GlobalBlockStateHandlers::getUpgrader()->upgradeIntIdMeta((int)$id, 0);
                    $blockStateId = GlobalBlockStateHandlers::getDeserializer()->deserialize($blockState);
                    $block = BlockFactory::getInstance()->fromStateId($blockStateId);
                } else {
                    $split = explode(":", $id);
                    if (count($split) === 2 and is_numeric($split[0]) and is_numeric($split[1])) {
                        $blockState = GlobalBlockStateHandlers::getUpgrader()->upgradeIntIdMeta((int)$split[0], (int)$split[1]);
                        $blockStateId = GlobalBlockStateHandlers::getDeserializer()->deserialize($blockState);
                        $block = BlockFactory::getInstance()->fromStateId($blockStateId);
                    } else {
                        $block = $default;
                    }
                }
            } else {
                $block = $default;
            }
        } catch (Exception) {
            $block = $default;
        }

        return $block;
    }

    /**
     * @param string[] $array
     * @param string|int $key
     * @param int $default
     *
     * @return int
     */
    public static function parseNumber(array $array, string|int $key, int $default): int
    {
        if (isset($array[$key]) and is_numeric($array[$key])) {
            return (int)$array[$key];
        } else {
            return $default;
        }
    }

    /**
     * @param mixed[] $array
     * @param string|int $key
     * @param bool $default
     *
     * @return bool
     */
    public static function parseBool(array $array, string|int $key, bool $default): bool
    {
        if (isset($array[$key]) and is_bool($array[$key])) {
            return $array[$key];
        } else {
            return $default;
        }
    }
}