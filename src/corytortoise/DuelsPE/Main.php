<?php

  /* ____             _     ____  _____
  * |  _ \ _   _  ___| |___|  _ \| ____|
  * | | | | | | |/ _ \ / __| |_) |  _|
  * | |_| | |_| |  __/ \__ \  __/| |___
  * |____/ \__,_|\___|_|___/_|   |_____|
  */

  namespace corytortoise\DuelsPE;

  use pocketmine\plugin\PluginBase;
  use pocketmine\Player;
  use pocketmine\utils\Config;
  use pocketmine\utils\TextFormat;
  use pocketmine\tile\Sign;

  //Plugin Files

  use corytortoise\DuelsPE\EventListener;
  use corytortoise\DuelsPE\tasks\GameTimer;
  use corytortoise\DuelsPE\commands\DuelCommand;

    class Main extends PluginBase {

    /*** Config File ***/
    private $config;

    /*** Messages/Language File ***/
    private $messages;

    /*** Data File ***/
    private $data;

    /*** Kit Option ***/
    private $option;

    /*** GameManager ***/
    public $manager;

    /*** How often to refresh signs ***/
    public $signDelay;

    /*** Players in Queue ***/
    public $queue = [];

    /*
    *
    * Startup and Initialization
    *
    */

    public function onEnable() {
      $this->saveDefaultConfig();
      if(!is_dir($this->getDataFolder())) {
        mkdir($this->getDataFolder());
      }
      $this->data = new Config($this->getDataFolder() . "data.yml", Config::YAML,array("arenas" => array(), "signs" => array()));
      $this->config = $this->getConfig();
      $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
      $this->saveResource("messages.yml");
      $this->messages = new Config($this->getDataFolder() . "messages.yml");
      $this->manager = new GameManager($this);
      $this->loadArenas();
      $this->loadSigns();
      $this->signDelay = $this->config->get("sign-refresh");
      $timer = new GameTimer($this);
      $this->getScheduler()->scheduleRepeatingTask($timer, 20);
      $this->loadKit();
      $this->registerCommand();
      $this->getLogger()->notice($this->getPrefix() . TextFormat::YELLOW . "Loading arenas and signs...");
      $this->getLogger()->notice($this->getPrefix() . TextFormat::GREEN . "Loaded " . count(array_keys($this->manager->arenas)) . " Arenas!");
    }

    public function getPrefix() {
      $prefix = $this->config->get("prefix");
      $finalPrefix = str_replace("&", "§", $prefix);
      return $finalPrefix . " ";
    }

    public function registerCommand() {
        $this->getServer()->getCommandMap()->registerCommand("duel", new DuelCommand("duel", $this));
    }

    private function loadArenas() {
      $this->getLogger()->notice($this->getPrefix() . TextFormat::YELLOW . "Loading arenas...");
      foreach($this->data->get("arenas") as $arena) {
        $this->manager->loadArena($arena);
      }
    }

    public function registerArena(BaseArena $arena) {
      $pos1 = $arena->spawn1;
      $pos2 = $arena->spawn2;
      $data =
      [
        "Name" => $arena->name,
        "level" => $pos1->getLevel(),
        "spawn1" =>
        [
          "x" => $pos1->getX(),
          "y" => $pos1->getY(),
          "z" => $pos1->getZ(),
          "yaw" => $pos1->getYaw(),
          "pitch" => $pos1->getPitch()
        ],

        "spawn2" =>
        [
          "x" => $pos2->getX(),
          "y" => $pos2->getY(),
          "z" => $pos2->getZ(),
          "yaw" => $pos2->getYaw(),
          "pitch" => $pos2->getPitch()
        ],
        "options" => $arena->options
      ];
      $this->data->set("arenas", array_push($this->data->get("arenas"), $data));
      $this->data->save();
    }

    private function loadSigns() {
      foreach($this->data->get("signs") as $sign) {
        if($this->getServer()->isLevelLoaded($sign[3]) !== true) {
          $this->getServer()->loadLevel($sign[3]);
        }
        $level = $this->getServer()->getLevelByName($sign[3]);
        $tile = $level->getTile(new Vector3($sign[0], $sign[1], $sign[2]));
        if($tile instanceof Sign) {
          $this->updateSign($tile);
        } else {
          $this->getServer()->getLogger()->warning($this->getPrefix() . "Sign tile not found at: " . $sign[0] . ", " . $sign[1] . ", " . $sign[2] . " in Level " . $sign[3]);
        }
      }
    }

    public function onDisable() {
      $this->manager->shutDown();
      $this->config->save();
      $this->data->save();
    }


    /**
     *
     * @param type $kit
     */
    private function loadKit($kit) {
      if(!is_array($kit)) {
        $this->getLogger()->warning("Kit not configured properly. Using default...");
        $this->option = "default";
      } else {
        $this->option = "custom";
      }
    }

    /**
     *
     * @param string $message
     * @return string $finalMessage
     */
    public static function getMessage(string $message = "") {
      $msg = $this->messages->get($message);
      if($msg != null) {
      $finalMessage = str_replace("&", TextFormat::ESCAPE, $msg);
      return $finalMessage;
      } else {
        return $this->getPrefix() . "Uh oh, no message found!";
      }
    }

    //////////////////////////////////////////////
    //
    // API Methods
    //
    //////////////////////////////////////////////


    public function CheckSignText(string $line) {
      if(!strpos($this->data->get("signs"), strtolower($line)) === false) {
        return true;
      }
      return false;
    }

    public function isDuelSign(Block $block) {
      $compare = implode(":", [$block->getX(), $block->getY(), $block->getZ(), $block->getLevel()->getName()]);
      foreach($this->data->get("signs") as $data) {
        if($compare === $data) {
          return true;
        }
      }
      return false;
    }

    public function registerDuelSign(Block $block) {
      $this->data->set("signs", implode(":", [$block->getX(), $block->getY(), $block->getZ(), $block->getLevel()->getName()]));
      $this->data->save();
      return [$this->getPrefix(), TextFormat::WHITE . count($this->manager->getFreeArenas()) . "/" . $this->getArenaCount() . " Free Arenas", TextFormat::WHITE . "1vs1", TextFormat::WHITE . "Tap to Join"];
    }

    public function updateSign(Sign $sign) {
      $sign->setLine(1, TextFormat::WHITE . count($this->manager->getFreeArenas()) . '/' . $this->getArenaCount() . ' Free Arenas');
    }

    public function removeDuelSign(Block $block) {
      $this->data->unset("signs", implode(":", [$block->getX(), $block->getY(), $block->getZ(), $block->getLevel()->getName()]));
    }

    /**
     *
     * @param Player $player
     * @return boolean
     */
    public function isPlayerInGame(Player $player) {
      if($this->manager->isPlayerInGame($player)) {
        return true;
      } else {
        return false;
      }
    }

    /*
    *
    * Queue Management
    *
    */

    /**
     * Adds a specific pocketmine\Player to the queue.
     * @param Player $player
     */
    public function addToQueue(Player $player) {
      $player->sendMessage($this->getPrefix() . $this->getMessage("queue-join"));
      array_push($this->queue, $player->getName());
    }

    public function checkQueue() {
      if(count($this->queue) > 2) {
        //TODO: Rework this for teams.
        if($this->isArenaAvailable()) {
          $this->manager->startArena(array_shift($this->queue), array_shift($this->queue));
        }
      }
    }

    /**
     * Removes a Player from queue by their NAME, not the Player class.
     * @param string $player
     */
    public function removeFromQueue($player) {
      unset($this->queue[$player]);
    }

    public function isArenaAvailable() {
      if($this->getArenaCount() - $this->getActiveArenaCount() >= 1) {
        return true;
      } else {
        return false;
      }
    }

    /*
    *
    * Match Management
    *
    */

    /**
     *
     * @param \corytortoise\DuelsPE\BaseArena $arena
     */
    public function endMatch(BaseArena $arena) {
        $arena->stop();
    }

    /**
    * This function will return true if a Player is in the defined arena.
    * It will return false if they are not. Leave $arena null to return the arena they are in.
    * @var Player $player
    * @var BaseArena $arena
    */
    public function getMatchFromPlayer($player, $arena = null) {
      if($arena == null) {
        if($this->manager->getPlayerArena($player) !== null) {
          return $this->manager->getPlayerArena($player);
        } else {
          return false;
        }
      } else {
        if($this->manager->isPlayerInArena($player, $arena) == true) {
          return true;
        } else {
          return false;
        }
      }
    }

    /**
     *
     * @param type $name
     * @return type
     */
    public function getArenaByName($name = "null") {
      return $this->manager->getArenaByName($name);
    }

    /**
     *
     * @return int Arenas
     */
    public function getArenaCount() {
      return count($this->manager->arenas);
    }

    /* Returns number of active arenas */
    public function getActiveArenaCount() {
      return count($this->manager->getActiveArenas());
    }


  }


