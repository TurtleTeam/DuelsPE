<?php

  /* ____             _     ____  _____
  * |  _ \ _   _  ___| |___|  _ \| ____|
  * | | | | | | |/ _ \ / __| |_) |  _|
  * | |_| | |_| |  __/ \__ \  __/| |___
  * |____/ \__,_|\___|_|___/_|   |_____|
  */

  namespace corytortoise\DuelsPE\tasks;

  use pocketmine\scheduler\Task;
  use corytortoise\DuelsPE\Main;

  class GameTimer implements Task {

  private $plugin;

    public function __construct(Main $plugin) {
      $this->plugin = $plugin;
    }

    public function onRun($currentTick) : void {
      foreach($this->plugin->manager->arenas as $arena) {
        if($arena->isActive()) {
        $arena->tick();
        }
      }
    }


  }
