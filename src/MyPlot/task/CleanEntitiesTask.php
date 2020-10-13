<?php

declare(strict_types=1);

namespace MyPlot\task;

use MyPlot\MyPlot;
use pocketmine\entity\Human;
use pocketmine\scheduler\Task;

class CleanEntitiesTask extends Task
{
    private $plugin;

    public function __construct(MyPlot $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun(): void
    {
        foreach ($this->plugin->getServer()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if (!$entity instanceof Human) {
                    $entity->flagForDespawn();
                }
            }
        }
    }
}