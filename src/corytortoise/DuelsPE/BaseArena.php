<?php

  /* ____             _     ____  _____
  * |  _ \ _   _  ___| |___|  _ \| ____|
  * | | | | | | |/ _ \ / __| |_) |  _|
  * | |_| | |_| |  __/ \__ \  __/| |___
  * |____/ \__,_|\___|_|___/_|   |_____|
  */

  namespace corytortoise\DuelsPE;

  use pocketmine\level\Location;
  use pocketmine\item\ItemFactory;
  use pocketmine\entity\Effect;
  use pocketmine\entity\EffectInstance;
  use pocketmine\item\enchantment\Enchantment;
  use pocketmine\item\enchantment\EnchantmentInstance;

  use corytortoise\DuelsPE\GameManager;

  class BaseArena {

    private $manager;

    public $spawn1;
    public $spawn2;

    private $players = [];

    private $active = false;

    private $timer = 190;
    private $beforeMatch = 10;
    private $matchTime = 180;

    /**
     *
     * @param GameManager $manager
     * @param Location $spawn1
     * @param Location $spawn2
     */
    public function __construct(GameManager $manager, Location $spawn1, Location $spawn2) {
      $this->manager = $manager;
      $this->spawn1 = $spawn1;
      $this->spawn2 = $spawn2;
      $this->beforeMatch = $manager->plugin->config->get("match-countdown");
      $this->matchTime = $manager->plugin->config->get("time-limit");
      $this->timer = $this->beforeMatch + $this->matchTime;
    }

    public function start() {
      $this->active = true;
    }

    /**
     * Adds players to arena arrays for later.
     * @param array $players
     */
    public function addPlayers(array $players) {
      foreach($players as $p) {
        $player = $this->manager->plugin->getServer()->getPlayerExact($p);
        $this->players[] = $player;
      }
    }

    public function isActive() {
      return $this->active;
    }

    public function tick() {
      $this->timer--;
      if($this->timer - $this->beforeMatch === $this->matchTime) {
        // Prematch countdown is over, so the round should start.
        $this->endPreCountdown();
      }
      if ($this->timer <= 0) {
        // Timer has run out, so game should end.
        $this->stop("you ran out of time!");
      }
      if($this->timer - $this->beforeMatch > $this->matchTime) {
        $seconds = $this->timer - $this->beforeMatch;
        foreach($this->getPlayers() as $p) {
         $p->sendPopup(str_replace("%s", $seconds, Main::getMessage("duel-countdown")));
        }
      }
       elseif($this->timer - $this->beforeMatch < $this->matchTime) {
          foreach($this->getPlayers() as $p) {
            $this->sendGameTime($p);
          }
        }
      }

    // This is used to start a game AFTER the prematch countdown
    public function startGame() {
      $player1 = $this->players[0];
      $player2 = $this->players[1];
      $player1->teleport($this->spawn1);
      $player2->teleport($this->spawn2);
      $this->kitHandler($this->players);
    }

    public function getPlayers() {
      return $this->players;
    }

    public function getOpponent($player) {
      foreach($this->getPlayers() as $p) {
        if($player != $p) {
          return $p;
        }
      }
    }

    public function endPreCountdown() {
      foreach($this->getPlayers() as $p) {
        $opposite = $this->getOpponent($p);
        $p->sendPopup(str_replace("%p", $opposite, Main::getMessage("duel-start")));
      }
      $this->startGame();
    }

    public function sendGameTime($p) {
      $m = floor($this->timer / 60);
      $s = $this->timer % 60;
      if($s < 10) {
        $s = "0" . $s;
      }
      $p->sendPopup(str_replace("%t", $m . ":" . $s, Main::getMessage("duel-timer")));
    }

    //Maybe move this to a KitHandler class later, since kits don't rely on BaseArena. Or, let Arenas have different kits?
    public function kitHandler($players) {
      if(!$this->manager->plugin->getConfig()->get("force-kits")) {
        return;
      } else {
        $kitData = array();
        foreach($this->manager->plugin->getConfig()->get("kit") as $data) {
         $kitData[] = $data;
        }
        foreach($players as $p) {
          $p->getInventory()->clearAll();
          $p->getArmorInventory()->clearAll();
          if($kitData["type"] === "custom") {
            foreach($kitData["items"] as $itemData) {
              $parsedData = explode(":", $itemData);
              $item = ItemFactory::get($parsedData[0], $parsedData[1], $parsedData[2]);
              $enchData = array_slice($itemData, 3);
              //Reference: AdvancedKits by Luca28pet.
              foreach($enchData as $key => $ench) {
                if($key % 2 === 0) {
                  $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment($ench), ($enchData[$key + 1])));
                }
              }
              $p->getInventory()->addItem($item);
            }
              foreach($kitData["armor"] as $type => $data) {
                $parsedData = explode(":", $data);
                $enchData = array_slice($parsedData, 2);
                $item = ItemFactory::get($parsedData[0], $parsedData[1]);
                foreach($enchData as $key => $ench) {
                  if($key % 2 === 0) {
                    $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment($ench), ($enchData[$key  + 1])));
                  }
                }
                switch($type) {
                  case "helmet":
                    $p->getArmorInventory()->setHelmet($item);
                    break;
                  case "chestplate":
                    $p->getArmorInventory()->setChestplate($item);
                    break;
                  case "leggings":
                    $p->getArmorInventory()->setLeggings($item);
                    break;
                  case "boots":
                    $p->getArmorInventory()->setBoots($item);
                    break;
                }
              }
              // I'm beginning to wonder if the kits system will be too heavy...
              foreach($kitData["effects"] as $key => $effectData) {
                $parsedData = explode(":", $effectData);
                $effect = new EffectInstance(Effect::getEffect($parsedData[0]),($parsedData[2]),($parsedData[1]));
                $p->addEffect($effect);
            }
          } else {
            //TODO: Add fallback kit.
          }
        }
      }
    }

    public function playerDeath($player) {
      foreach($this->players as $p) {
        if($p->getName() != $player->getName()) {
          $this->endMatch($p);
          return;
        }
      }
    }

    /**
     * Used when a player or team wins.
     * @param Player $winner
     */
    public function endMatch($winner) {
      if($this->manager->plugin->getConfig()->get("duel-end-type") === "all") {
        $loser = $this->getOpponent($winner);
        if($this->manager->plugin->getConfig()->get("force-kits")) {
          $winner->getInventory->clearAll();
          $winner->getArmorInventory->clearAll();
        }
        $this->manager->plugin->getServer()->broadcastMessage($this->manager->plugin->getPrefix() . str_replace(["%w", "%l"], [$winner->getName(), $loser->getName()], Main::getMessage("duel-end")));
        $this->manager->plugin->callEvent("end", $this);
        $this->restartArena();
        }
    }

    /**
     * This can be used by other plugins to force stop a duel.
     * @param type $cause
     */
    public function stop($cause = "") {
      foreach($this->players as $player) {
        if($player->isOnline()) {
          $player->sendMessage($this->manager->plugin->getPrefix() . "Duel was stopped because " . $cause);
          if($this->manager->plugin->getConfig()->get("force-kits")) {
            $player->getInventory()->clearAll();
            $player->getInventory()->clearAll();
          }
          $player->teleport($player->getSpawn());

        }
      }
      $this->restartArena();
    }

    /**
     * This method clears attributes of BaseArena to prepare it for the next set of players.
     */
    protected function restartArena() {
      $this->beforeMatch = $this->manager->plugin->config->get("match-countdown");
      $this->matchTime = $this->manager->plugin->config->get("time-limit");
      $this->timer = $this->beforeMatch + $this->matchTime;
      $this->players = [];
      $this->active = false;
    }

  }
