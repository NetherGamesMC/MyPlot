<?php
declare(strict_types=1);
namespace MyPlot\task;

use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\world\Position;
use pocketmine\world\World;

class CornerCorrectionTask extends Task{

	protected MyPlot $plugin;
	protected Plot $start;
	protected ?World $world;
	protected int $height;
	protected Block $plotWallBlock;
	protected int $maxBlocksPerTick;
	/** @var Position|Vector3|null $plotBeginPos */
	protected ?Vector3 $plotBeginPos;
	protected int $xMax;
	protected int $zMax;
	protected int $direction;
	protected Block $roadBlock;
	protected Block $groundBlock;
	protected Block $bottomBlock;
	protected Vector3 $pos;

	public function __construct(MyPlot $plugin, Plot $start, Plot $end, int $cornerDirection, int $maxBlocksPerTick = 256) {
		$this->plugin = $plugin;
		$this->start = $start;
		$this->plotBeginPos = $plugin->getPlotPosition($start, false);
		$this->world = $this->plotBeginPos->getWorld();
		$this->maxBlocksPerTick = $maxBlocksPerTick;

		$plotLevel = $plugin->getLevelSettings($start->levelName);
		$plotSize = $plotLevel->plotSize;
		$roadWidth = $plotLevel->roadWidth;
		$this->height = $plotLevel->groundHeight;
		$this->plotWallBlock = $plotLevel->wallBlock;
		$this->roadBlock = $plotLevel->plotFloorBlock;
		$this->groundBlock = $plotLevel->plotFillBlock;
		$this->bottomBlock = $plotLevel->bottomBlock;

		if(($start->Z - $end->Z) === 1) { // North Z-
			if($cornerDirection === Facing::EAST) {
				$this->plotBeginPos = $this->plotBeginPos->subtract(0, 0, $roadWidth);
				$this->plotBeginPos = $this->plotBeginPos->add($plotSize, 0, 0);
			}elseif($cornerDirection === Facing::WEST) {
				$this->plotBeginPos = $this->plotBeginPos->subtract($roadWidth, 0, $roadWidth);
			}
		}elseif(($start->X - $end->X) === -1) { // East X+
			if($cornerDirection === Facing::NORTH) {
				$this->plotBeginPos = $this->plotBeginPos->add($plotSize, 0, 0);
				$this->plotBeginPos = $this->plotBeginPos->subtract(0, 0, $roadWidth);
			}elseif($cornerDirection === Facing::SOUTH) {
				$this->plotBeginPos = $this->plotBeginPos->add($plotSize, 0, $plotSize);
			}
		}elseif(($start->Z - $end->Z) === -1) { // South Z+
			if($cornerDirection === Facing::EAST) {
				$this->plotBeginPos = $this->plotBeginPos->add($plotSize, 0, $plotSize);
				$this->plotBeginPos = $this->plotBeginPos->add($plotSize, 0, 0);
			}elseif($cornerDirection === Facing::WEST) {
				$this->plotBeginPos = $this->plotBeginPos->add(0, 0, $plotSize);
				$this->plotBeginPos = $this->plotBeginPos->subtract($roadWidth, 0, 0);
			}
		}elseif(($start->X - $end->X) === 1) { // West X-
			if($cornerDirection === Facing::NORTH) {
				$this->plotBeginPos = $this->plotBeginPos->subtract($roadWidth, 0, $roadWidth);
			}elseif($cornerDirection === Facing::SOUTH) {
				$this->plotBeginPos = $this->plotBeginPos->add(0, 0, $plotSize);
				$this->plotBeginPos = $this->plotBeginPos->subtract($roadWidth, 0, 0);
			}
		}
		$this->xMax = (int) ($this->plotBeginPos->x + $roadWidth);
		$this->zMax = (int) ($this->plotBeginPos->z + $roadWidth);
		$this->pos = new Vector3($this->plotBeginPos->x, 0, $this->plotBeginPos->z);
		$plugin->getLogger()->debug("Corner Correction Task started between plots {$start->X};{$start->Z} and {$end->X};{$end->Z}");
	}

	public function onRun() : void {
		foreach($this->world->getEntities() as $entity) {
			if($entity->getLocation()->x > $this->position->x - 1 and $entity->getLocation()->x < $this->xMax + 1) {
				if($entity->getLocation()->z > $this->position->z - 1 and $entity->getLocation()->z < $this->zMax + 1) {
					if(!$entity instanceof Player) {
						$entity->flagForDespawn();
					}else{
						$this->plugin->teleportPlayerToPlot($entity, $this->start);
					}
				}
			}
		}
		$blocks = 0;
		while($this->position->x < $this->xMax) {
			while($this->position->z < $this->zMax) {
				while($this->position->y < $this->world->getMaxY()) {
					if($this->position->y === 0)
						$block = $this->bottomBlock;
					elseif($this->position->y < $this->height)
						$block = $this->groundBlock;
					elseif($this->position->y === $this->height)
						$block = $this->roadBlock;
					else
						$block = VanillaBlocks::AIR();

					$this->world->setBlock($this->pos, $block, false);
					$this->position->y++;

					$blocks++;
					if($blocks >= $this->maxBlocksPerTick) {
						$this->setHandler(null);
						$this->plugin->getScheduler()->scheduleDelayedTask($this, 1);
						return;
					}
				}
				$this->position->y = 0;
				$this->position->z++;
			}
			$this->position->z = $this->plotBeginPos->z;
			$this->position->x++;
		}

		$this->plugin->getLogger()->debug("Corner Correction Task completed");
	}
}