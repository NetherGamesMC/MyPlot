<?php
declare(strict_types=1);

namespace MyPlot;

use MyPlot\events\MyPlotBlockEvent;
use MyPlot\events\MyPlotBorderChangeEvent;
use MyPlot\events\MyPlotPlayerEnterPlotEvent;
use MyPlot\events\MyPlotPlayerLeavePlotEvent;
use MyPlot\events\MyPlotPvpEvent;
use pocketmine\block\Block;
use pocketmine\block\Sapling;
use pocketmine\block\utils\TreeType;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\item\Food;
use pocketmine\network\mcpe\protocol\SetTimePacket;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use function explode;
use function in_array;
use function strtolower;

class EventListener implements Listener{
	/** @var MyPlot $plugin */
	private $plugin;

	/**
	 * EventListener constructor.
	 *
	 * @param MyPlot $plugin
	 */
	public function __construct(MyPlot $plugin) {
		$this->plugin = $plugin;
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param WorldLoadEvent $event
	 */
	public function onLevelLoad(WorldLoadEvent $event) : void {
		if(file_exists($this->plugin->getDataFolder() . "worlds" . DIRECTORY_SEPARATOR . $event->getWorld()->getFolderName() . ".yml")) {
			$this->plugin->getLogger()->debug("MyPlot level " . $event->getWorld()->getFolderName() . " loaded!");
			$settings = $event->getWorld()->getProvider()->getWorldData()->getGeneratorOptions();
			if(!isset($settings["preset"]) or empty($settings["preset"])) {
				return;
			}
			$settings = json_decode($settings["preset"], true);
			if($settings === false) {
				return;
			}
			$levelName = $event->getWorld()->getFolderName();
			$default = array_filter($this->plugin->getConfig()->get("DefaultWorld", []), function($key) {
				return !in_array($key, ["PlotSize", "GroundHeight", "RoadWidth", "RoadBlock", "WallBlock", "PlotFloorBlock", "PlotFillBlock", "BottomBlock"]);
			}, ARRAY_FILTER_USE_KEY);
			$config = new Config($this->plugin->getDataFolder() . "worlds" . DIRECTORY_SEPARATOR . $levelName . ".yml", Config::YAML, $default);
			foreach(array_keys($default) as $key){
				$settings[$key] = $config->get((string)$key);
			}
			$this->plugin->addLevelSettings($levelName, new PlotLevelSettings($levelName, $settings));
		}
	}

	/**
	 * @ignoreCancelled false
	 * @priority MONITOR
	 *
	 * @param WorldUnloadEvent $event
	 */
	public function onLevelUnload(WorldUnloadEvent $event) : void {
		if($event->isCancelled()) {
			return;
		}
		$levelName = $event->getWorld()->getFolderName();
		if($this->plugin->unloadLevelSettings($levelName)) {
			$this->plugin->getLogger()->debug("Level " . $event->getWorld()->getFolderName() . " unloaded!");
		}
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param BlockPlaceEvent $event
	 */
	public function onBlockPlace(BlockPlaceEvent $event) : void {
		$this->onEventOnBlock($event);
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param BlockBreakEvent $event
	 */
	public function onBlockBreak(BlockBreakEvent $event) : void {
		$this->onEventOnBlock($event);
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param PlayerInteractEvent $event
	 */
	public function onPlayerInteract(PlayerInteractEvent $event) : void {
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK and $event->getItem() instanceof Food)
			return;
		$this->onEventOnBlock($event);
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param SignChangeEvent $event
	 */
	public function onSignChange(SignChangeEvent $event) : void {
		$this->onEventOnBlock($event);
	}

	/**
	 * @param BlockPlaceEvent|BlockBreakEvent|PlayerInteractEvent|SignChangeEvent $event
	 */
	private function onEventOnBlock($event) : void {
		if(!$event->getBlock()->getPos()->isValid())
			return;
		$levelName = $event->getBlock()->getPos()->getWorld()->getFolderName();
		if(!$this->plugin->isLevelLoaded($levelName)) {
			return;
		}
		$plot = $this->plugin->getPlotByPosition($event->getBlock()->getPos());
		if($plot !== null) {
			if(!$event instanceof SignChangeEvent && in_array($event->getItem()->getId(), $this->plugin->bannedItems, true)) {
				$event->cancel();
				return;
			}
			$ev = new MyPlotBlockEvent($plot, $event->getBlock(), $event->getPlayer(), $event);
			if($event->isCancelled()) {
				$ev->cancel();
			}else{
				$ev->uncancel();
			}
			$ev->call();
			if($ev->isCancelled()) {
				$event->cancel();
			}else{
				$event->uncancel();
			}
			$username = $event->getPlayer()->getName();
			if($plot->owner == $username or $plot->isHelper($username) or $plot->isHelper("*") or $event->getPlayer()->hasPermission("myplot.admin.build.plot")) {
				if(!($event instanceof PlayerInteractEvent and $event->getBlock() instanceof Sapling))
					return;
				/*
				 * Prevent growing a tree near the edge of a plot
				 * so the leaves won't go outside the plot
				 */

				/** @var Sapling $block */
				$block = $event->getBlock();
				$maxLengthLeaves = ($block->getIdInfo()->getVariant() == TreeType::SPRUCE()) ? 3 : 2;
				$beginPos = $this->plugin->getPlotPosition($plot);
				$endPos = clone $beginPos;
				$beginPos->x += $maxLengthLeaves;
				$beginPos->z += $maxLengthLeaves;
				$plotSize = $this->plugin->getLevelSettings($levelName)->plotSize;
				$endPos->x += $plotSize - $maxLengthLeaves;
				$endPos->z += $plotSize - $maxLengthLeaves;
				if($block->getPos()->x >= $beginPos->x and $block->getPos()->z >= $beginPos->z and $block->getPos()->x < $endPos->x and $block->getPos()->z < $endPos->z) {
					return;
				}
			}
		}elseif($event->getPlayer()->hasPermission("myplot.admin.build.road"))
			return;
		elseif($this->plugin->isPositionBorderingPlot($event->getBlock()->getPos()) and $this->plugin->getLevelSettings($levelName)->editBorderBlocks){
			$plot = $this->plugin->getPlotBorderingPosition($event->getBlock()->getPos());
			if($plot instanceof Plot) {
				$ev = new MyPlotBorderChangeEvent($plot, $event->getBlock(), $event->getPlayer(), $event);
				if($event->isCancelled()) {
					$ev->cancel();
				}else{
					$ev->uncancel();
				}
				$ev->call();
				if($ev->isCancelled()) {
					$event->cancel();
				}else{
					$event->uncancel();
				}
				$username = $event->getPlayer()->getName();
				if($plot->owner == $username or $plot->isHelper($username) or $plot->isHelper("*") or $event->getPlayer()->hasPermission("myplot.admin.build.plot"))
					if(!($event instanceof PlayerInteractEvent and $event->getBlock() instanceof Sapling))
						return;
			}
		}
		$event->cancel();
		$this->plugin->getLogger()->debug("Block placement/break/interaction of {$event->getBlock()->getName()} was cancelled at " . $event->getBlock()->getPos()->__toString());
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param EntityExplodeEvent $event
	 */
	public function onExplosion(EntityExplodeEvent $event) : void {
		if($event->isCancelled()) {
			return;
		}
		$levelName = $event->getEntity()->getWorld()->getFolderName();
		if(!$this->plugin->isLevelLoaded($levelName))
			return;
		$plot = $this->plugin->getPlotByPosition($event->getPosition());
		if($plot === null) {
			$event->cancel();
			return;
		}
		$beginPos = $this->plugin->getPlotPosition($plot);
		$endPos = clone $beginPos;
		$plotSize = $this->plugin->getLevelSettings($levelName)->plotSize;
		$endPos->x += $plotSize;
		$endPos->z += $plotSize;
		$blocks = array_filter($event->getBlockList(), function(Block $block) use ($beginPos, $endPos) {
			if($block->getPos()->x >= $beginPos->x and $block->getPos()->z >= $beginPos->z and $block->getPos()->x < $endPos->x and $block->getPos()->z < $endPos->z) {
				return true;
			}
			return false;
		});
		$event->setBlockList($blocks);
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param EntityMotionEvent $event
	 */
	public function onEntityMotion(EntityMotionEvent $event) : void {
		if($event->isCancelled()) {
			return;
		}
		$level = $event->getEntity()->getWorld();
		if(!$level instanceof World)
			return;
		$levelName = $level->getFolderName();
		if(!$this->plugin->isLevelLoaded($levelName))
			return;
		$settings = $this->plugin->getLevelSettings($levelName);
		if($settings->restrictEntityMovement and !($event->getEntity() instanceof Player)) {
			$event->cancel();
			$this->plugin->getLogger()->debug("Cancelled entity motion on " . $levelName);
		}
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param BlockSpreadEvent $event
	 */
	public function onBlockSpread(BlockSpreadEvent $event) : void {
		if($event->isCancelled()) {
			return;
		}
		$levelName = $event->getBlock()->getPos()->getWorld()->getFolderName();
		if(!$this->plugin->isLevelLoaded($levelName))
			return;
		$settings = $this->plugin->getLevelSettings($levelName);
		if(!$settings->updatePlotLiquids and ($this->plugin->getPlotByPosition($event->getBlock()->getPos()) instanceof Plot or $this->plugin->getPlotByPosition($event->getSource()->getPos()) instanceof Plot or $this->plugin->isPositionBorderingPlot($event->getBlock()->getPos()) or $this->plugin->isPositionBorderingPlot($event->getSource()->getPos()))) {
			$event->cancel();
			$this->plugin->getLogger()->debug("Cancelled block spread of {$event->getBlock()->getName()} on " . $levelName);
		}
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param PlayerMoveEvent $event
	 */
	public function onPlayerMove(PlayerMoveEvent $event) : void {
		$this->onEventOnMove($event->getPlayer(), $event);
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param EntityTeleportEvent $event
	 */
	public function onPlayerTeleport(EntityTeleportEvent $event) : void {
		$player = $event->getEntity();
		if($player instanceof Player) {
			$this->onEventOnMove($player, $event);

			if(!$player->hasPermission('nethergames.voter') && $event->getTo()->getWorld()->getFolderName() === 'MEGA' && $event->getFrom()->getWorld() === $this->plugin->getServer()->getWorldManager()->getDefaultWorld()) {
				$player->sendMessage('§cYou must vote to access Mega Creative.');
				$event->cancel();
				return;
			}
		}
	}

	/**
	 * @param PlayerMoveEvent|EntityTeleportEvent $event
	 */
	private function onEventOnMove(Player $player, $event) : void {
		$levelName = $player->getWorld()->getFolderName();
		if(!$this->plugin->isLevelLoaded($levelName))
			return;
		$plot = $this->plugin->getPlotByPosition($event->getTo());
		$plotFrom = $this->plugin->getPlotByPosition($event->getFrom());
		if($plot !== null and ($plotFrom === null or !$plot->isSame($plotFrom))) {
			if(strpos((string)$plot, "-0")) {
				return;
			}
			if($event instanceof EntityTeleportEvent) {
				$player = $event->getEntity();
				if(!$player instanceof Player) {
					return;
				}
			}else{
				$player = $event->getPlayer();
			}
			$ev = new MyPlotPlayerEnterPlotEvent($plot, $player);
			if($event->isCancelled()) {
				$ev->cancel();
			}else{
				$ev->uncancel();
			}
			$username = $player->getName();
			if($plot->owner !== $username and ($plot->isBanned($username) or $plot->isBanned("*")) and !$player->hasPermission("myplot.admin.banplayer.bypass")) {
				$ev->cancel();
			}
			$ev->call();
			if($ev->isCancelled()) {
				$event->cancel();
			}else{
				$event->uncancel();
			}
			if($event->isCancelled()) {
				return;
			}
			if(!$this->plugin->getConfig()->get("ShowPlotPopup", true))
				return;
			$popup = $this->plugin->getLanguage()->translateString("popup", [TextFormat::GREEN . $plot]);
			if(!empty($plot->owner)) {
				$owner = TextFormat::GREEN . $plot->owner;
				$ownerPopup = $this->plugin->getLanguage()->translateString("popup.owner", [$owner]);
			}else{
				$ownerPopup = $this->plugin->getLanguage()->translateString("popup.available", [$this->plugin->getLevelSettings($levelName)->claimPrice]);
			}
			$paddingSize = (int)floor((strlen($popup) - strlen($ownerPopup)) / 2);
			$paddingPopup = str_repeat(" ", max(0, -$paddingSize));
			$paddingOwnerPopup = str_repeat(" ", max(0, $paddingSize));
			$popup = TextFormat::WHITE . $paddingPopup . $popup . "\n" . TextFormat::WHITE . $paddingOwnerPopup . $ownerPopup;
			$ev->getPlayer()->sendTip($popup);
		}elseif($plotFrom !== null and ($plot === null or !$plot->isSame($plotFrom))){
			if(strpos((string)$plotFrom, "-0")) {
				return;
			}
			$ev = new MyPlotPlayerLeavePlotEvent($plotFrom, $player);
			if($event->isCancelled()) {
				$ev->cancel();
			}else{
				$ev->uncancel();
			}
			$ev->call();
			if($ev->isCancelled()) {
				$event->cancel();
			}else{
				$event->uncancel();
			}
		}elseif($plotFrom !== null and $plot !== null and ($plot->isBanned($player->getName()) or $plot->isBanned("*")) and $plot->owner !== $player->getName() and !$player->hasPermission("myplot.admin.banplayer.bypass")){
			$this->plugin->teleportPlayerToPlot($player, $plot, false);
		}
	}

	/**
	 * @ignoreCancelled false
	 * @priority LOWEST
	 *
	 * @param EntityDamageByEntityEvent $event
	 */
	public function onEntityDamage(EntityDamageByEntityEvent $event) : void {
		$damaged = $event->getEntity();
		$damager = $event->getDamager();
		if($damaged instanceof Player and $damager instanceof Player and !$event->isCancelled()) {
			$levelName = $damaged->getWorld()->getFolderName();
			if(!$this->plugin->isLevelLoaded($levelName)) {
				return;
			}
			$settings = $this->plugin->getLevelSettings($levelName);
			$plot = $this->plugin->getPlotByPosition($damaged->getPosition());
			if($plot !== null) {
				$ev = new MyPlotPvpEvent($plot, $damager, $damaged, $event);
				if(!$plot->pvp and !$damager->hasPermission("myplot.admin.pvp.bypass")) {
					$ev->cancel();
					$this->plugin->getLogger()->debug("Cancelled pvp event in plot " . $plot->X . ";" . $plot->Z . " on level '" . $levelName . "'");
				}
				$ev->call();
				if($ev->isCancelled()) {
					$event->cancel();
				}else{
					$event->uncancel();
				}
				if($event->isCancelled()) {
					$ev->getAttacker()->sendMessage(TextFormat::RED . $this->plugin->getLanguage()->translateString("pvp.disabled")); // generic message- we dont know if by config or plot
				}
				return;
			}
			if($damager->hasPermission("myplot.admin.pvp.bypass")) {
				return;
			}
			if($settings->restrictPVP) {
				$event->cancel();
				$damager->sendMessage(TextFormat::RED . $this->plugin->getLanguage()->translateString("pvp.world"));
				$this->plugin->getLogger()->debug("Cancelled pvp event on " . $levelName);
			}
		}
	}

	public function onPlayerChat(PlayerChatEvent $event) : void {
		if(!$this->plugin->getConfig()->get('PlotChat', true)) {
			return;
		}
		$levelName = $event->getPlayer()->getWorld()->getFolderName();
		if(!$this->plugin->isLevelLoaded($levelName)) {
			return;
		}
		$recipients = $event->getRecipients();
		$plot = $this->plugin->getPlotByPosition($event->getPlayer()->getPosition());
		if($plot !== null) {
			foreach($recipients as $key => $recipient){
				if($recipient instanceof Player) {
					if(($this->plugin->getPlotByPosition($recipient->getPosition()) === null) || ($this->plugin->getPlotByPosition($recipient->getPosition()) !== $plot)) {
						unset($recipients[$key]);
					}
				}
			}
			$event->setRecipients($recipients);
		}else{
			foreach($recipients as $key => $recipient){
				if(($recipient instanceof Player) && $this->plugin->getPlotByPosition($recipient->getPosition()) !== null) {
					unset($recipients[$key]);
				}
			}
			$event->setRecipients($recipients);
		}
	}

	/**
	 * @param PlayerDropItemEvent $event
	 *
	 * @priority LOW
	 *
	 * @ignoreCancelled
	 */
	public function onPlayerDropItem(PlayerDropItemEvent $event) : void {
		$event->cancel();
	}

	/**
	 * @param PlayerCommandPreprocessEvent $event
	 *
	 * @priority LOW
	 *
	 * @ignoreCancelled
	 */
	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event) : void {
		$player = $event->getPlayer();
		$command = explode(' ', strtolower($event->getMessage()));

		if(($command[0] === '/p' || $command[0] === '/plot') && ($player->getWorld()->getFolderName() === $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getFolderName())) {
			$player->sendMessage('§cThat command is blocked in this world.');
			$event->cancel();
		}
	}

	public function onDataPacketSendEvent(DataPacketSendEvent $event) : void {
		foreach($event->getPackets() as $packet){
			if(!$packet instanceof SetTimePacket) {
				continue;
			}

			foreach($event->getTargets() as $target){
				if(in_array($target->getPlayer()->getName(), $this->plugin->stopTime, true)) {
					$event->cancel();
				}
			}
		}
	}

	public function onEat(PlayerItemConsumeEvent $event) : void {
		$item = $event->getItem()->getId();
		if(in_array($item, $this->plugin->bannedItems, true)) {
			$event->cancel();
		}
	}
}