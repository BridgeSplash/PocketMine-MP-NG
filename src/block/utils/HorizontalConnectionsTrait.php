<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\block\utils;

use pocketmine\block\Block;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\math\Axis;
use pocketmine\math\Facing;

trait HorizontalConnectionsTrait{

	/** @var int[] facing => facing */
	protected array $connections = [];

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacingFlags($this->connections);
	}

	/** @return int[] */
	public function getConnections() : array{ return $this->connections; }

	public function isConnected(int $facing) : bool{
		return isset($this->connections[$facing]);
	}

	/**
	 * @param int[] $connections
	 * @return $this
	 */
	public function setConnections(array $connections) : self{
		$uniqueConnections = [];
		foreach($connections as $facing){
			self::validateHorizontalFacing($facing);
			$uniqueConnections[$facing] = $facing;
		}
		$this->connections = $uniqueConnections;
		return $this;
	}

	/** @return $this */
	public function setConnected(int $facing, bool $value) : self{
		self::validateHorizontalFacing($facing);
		if($value){
			$this->connections[$facing] = $facing;
		}else{
			unset($this->connections[$facing]);
		}
		return $this;
	}

	private static function validateHorizontalFacing(int $facing) : void{
		Facing::validate($facing);
		if(Facing::axis($facing) === Axis::Y){
			throw new \InvalidArgumentException("Facing can only be north, east, south or west");
		}
	}

	public function onNearbyBlockChange() : void{
		parent::onNearbyBlockChange();

		if($this->recalculateConnections()){
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	/**
	 * Returns whether any connection was changed.
	 */
	protected function recalculateConnections() : bool{
		$changed = false;

		foreach(Facing::HORIZONTAL as $facing){
			$connected = $this->canConnectTo($this->getSide($facing), $facing);
			if($connected !== isset($this->connections[$facing])){
				$this->setConnected($facing, $connected);
				$changed = true;
			}
		}

		return $changed;
	}

	abstract protected function canConnectTo(Block $block, int $facing) : bool;
}
