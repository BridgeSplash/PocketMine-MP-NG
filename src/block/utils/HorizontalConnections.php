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

use pocketmine\math\Facing;

/**
 * Implemented by blocks which visually connect to their horizontal neighbours, such as fences and glass panes.
 */
interface HorizontalConnections{

	/**
	 * @return int[] facing => facing
	 * @see Facing
	 */
	public function getConnections() : array;

	/**
	 * @see Facing
	 */
	public function isConnected(int $facing) : bool;

	/**
	 * @return $this
	 *
	 * @see Facing
	 */
	public function setConnected(int $facing, bool $value) : self;

	/**
	 * @param int[] $connections
	 *
	 * @return $this
	 *
	 * @see Facing
	 */
	public function setConnections(array $connections) : self;
}
