<?php

  /* ____             _     ____  _____
  * |  _ \ _   _  ___| |___|  _ \| ____|
  * | | | | | | |/ _ \ / __| |_) |  _|
  * | |_| | |_| |  __/ \__ \  __/| |___
  * |____/ \__,_|\___|_|___/_|   |_____|
  */

  namespace corytortoise\DuelsPE;

  use corytortoise\DuelsPE\Main;
  use corytortoise\DuelsPE\Arena;

  class GameManager {

    public $arenas = [];
    private $plugin;

    public function __construct(Main $plugin) {
      $this->plugin = $plugin;
    }

    public function loadArena($data) {
      if($this->plugin->getServer()->isLevelLoaded($data["level"]) == false) {
        $this->plugin->getServer()->loadLevel($this->plugin->getServer()->getLevelByName($data["level"]));
      }

      //TODO: Fix this.
      $spawn1 = new Location($data[0][0], $data[0][1], $data[0][2], $data[0][3], $data[0][4], $data["level"]);
      $spawn2 = new Location($data[1][0], $data[1][1], $data[1][2], $data[1][3], $data[1][4], $data["level"]);
      $arena = new Arena($this, $spawn1, $spawn2);
      array_push($this->arenas, $arena);
    }

    /**
     * Starts an Arena, beginning with pre-match countdown.
     * @param Player[] $players
     */
    public function startArena(array $players) {
      $arena = $this->chooseRandomArena();
      if($arena !== null) {
        $arena->addPlayers($players);
        $arena->start();
      }
    }

    public function chooseRandomArena() {
      $freeArenas = $this->getFreeArenas();
      if(empty($freeArenas)) {
        return null;
      } else {
        return array_rand($freeArenas);
      }
    }

    /**
     * This will be used later to hopefully prevent side effects of disabling the plugin mid-match
     */
    public function shutDown() {

    }

    /**
    * Gets the arena of a Player.
    * TODO: Use Player ID instead of Username for player management
    * @param Player $player
    * @return Arena $arena
    */
    public function getPlayerArena($player) {
      foreach($this->arenas as $arena) {
        foreach($arena->getPlayers() as $p) {
          if($p == $player->getName()) {
            return $arena;
          }
        }
      }
    }

    /**
     * This function tells the plugin that a player has died.
     * @param Player $loser
     */
    public function playerDeath(Player $loser) {
      $this->getPlayerArena($loser)->removePlayer($loser);
    }

    /**
     * Returns an array of all loaded arenas
     * @return Arena[] $arenas
     */
    public function getArenas() {
      return $this->arenas;
    }

    public function getActiveArenas() {
      $activeArenas = [];
      $i = false;
      foreach($this->getArenas() as $arena) {
        if($arena->isActive()) {
          array_push($activeArenas, $arena);
          $i = true;
        }
      }
      if($i) {
        return $activeArenas;
      } else {
        return 0;
      }
    }

    public function getFreeArenas() {
      $freeArenas = [];
      $i = false;
      foreach($this->getArenas() as $arena) {
        if(!$arena->isActive()) {
          array_push($freeArenas, $arena);
          $i = true;
        }
      }
      if($i) {
        return $freeArenas;
      } else {
        return 0;
      }
    }

    /**
     * Returns the amount of players in a match
     * @return int $count
     */
    public function getActivePlayers() {
      $count = 0;
      foreach($this->arenas as $arena) {
        foreach($arena->getPlayers() as $p) {
          $count++;
        }
      }
      return $count;
    }

  }
