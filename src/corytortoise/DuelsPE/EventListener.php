<?php

  /* ____             _     ____  _____
  * |  _ \ _   _  ___| |___|  _ \| ____|
  * | | | | | | |/ _ \ / __| |_) |  _|
  * | |_| | |_| |  __/ \__ \  __/| |___
  * |____/ \__,_|\___|_|___/_|   |_____|
  */

  namespace corytortoise\DuelsPE;

  use pocketmine\event\Listener;
  use pocketmine\event\block\SignChangeEvent;
  use pocketmine\event\block\BlockBreakEvent;
  use pocketmine\event\player\PlayerInteractEvent;
  use pocketmine\event\player\PlayerDeathEvent;
  use pocketmine\event\player\PlayerQuitEvent;

  //DuelsPE Files
  use corytortoise\DuelsPE\Main;

  class EventListener implements Listener {

    private $plugin;

    public function __construct(Main $plugin) {
      $this->plugin = $plugin;
    }

    public function onSignChange(SignChangeEvent $event) {
      if($this->plugin->checkSignText($event->getLine(0))) {
          $event->setLines($this->plugin->registerSign($event->getBlock()));
      }
    }

    public function onTap(PlayerInteractEvent $event) {
      if(!$this->plugin->config->get("sign-join")) {
        return;
      }
      if($this->plugin->isDuelSign($event->getBlock())) {
        $player = $event->getPlayer();
        if(!$this->plugin->isPlayerInQueue($player)) {
          if(!$this->plugin->isPlayerInGame($player)) {
            $this->plugin->addToQueue($player);
          } else {
            $player->sendMessage($this->plugin->getPrefix() . $this->plugin->getMessage("in-game"));
          }
        } else {
          $player->sendMessage($this->plugin->getPrefix() . $this->plugin->getMessage("in-queue"));
        }
      }
    }

    public function onBreak(BlockBreakEvent $event) {
      if($this->plugin->isDuelSign($event->getBlock())) {
        $this->plugin->removeDuelSign($event->getBlock());
      }
    }

    public function onDeath(PlayerDeathEvent $event) {
      $player = $event->getPlayer();
      if($this->plugin->isPlayerInGame($player)) {
        $this->plugin->manager()->playerDeath($player);
      }
    }

    public function onQuit(PlayerQuitEvent $event) {
      $player = $event->getPlayer();
      if($this->plugin->isPlayerInGame($player)) {
        $this->plugin->manager()->playerDeath($player);
      }
      elseif($this->plugin->isPlayerInQueue($player)) {
        $this->plugin->removeFromQueue($player->getName());
      }
    }
  }
