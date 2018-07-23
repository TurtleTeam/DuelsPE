<?php

  /* ____             _     ____  _____ 
  * |  _ \ _   _  ___| |___|  _ \| ____|
  * | | | | | | |/ _ \ / __| |_) |  _|  
  * | |_| | |_| |  __/ \__ \  __/| |___ 
  * |____/ \__,_|\___|_|___/_|   |_____|
  */

namespace corytortoise\DuelsPE\events;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\event\Cancellable;
use pocketmine\Player;

use corytortoise\DuelsPE\Main;
use corytortoise\DuelsPE\BaseArena;

class GameEndEvent extends PluginEvent {
	/** @var Main */
	private $plugin;
	/** @var Player */
	private $winner;
	/** @var Player */
	private $loser;
	/** @var BaseArena */
	private $arena;

       /**	
 	* @param Main           $plugin
	 * @param Player	$winner
	 * @param Player	$looser
	 * @param BaseArena	        $arena
	 */
	public function __construct(Main $plugin, Player $winner, Player $loser, BaseArena $arena) {
		parent::__construct($plugin);
		$this->plugin = $plugin;
		$this->winner = $winner;
		$this->loser = $loser;
		$this->arena = $arena;
	}

	/**
	 * @return Player
	 **/
	public function getWinner() {
		return $this->winner;
	}

	/**
	 * @return Player
	 **/
	public function getLoser() {
		return $this->loser;
	}

	/**
	 * @return BaseArena
	 **/
	public function getArena() {
		return $this->arena;
	}
}
