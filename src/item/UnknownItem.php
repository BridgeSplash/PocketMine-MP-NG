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

namespace pocketmine\item;

use pocketmine\data\bedrock\block\BlockStateData;

/**
 * Represents an item type which the server doesn't implement, but which the client knows about.
 *
 * Items of this type have no behaviour of any kind - they exist purely so that unimplemented types can still be
 * obtained in creative mode and via commands. They retain the Bedrock identity they were created from, so the client
 * renders them the same way it would render the real item.
 */
final class UnknownItem extends Item{

	public function __construct(
		ItemIdentifier $identifier,
		string $name,
		private string $bedrockId,
		private int $bedrockMeta,
		private ?BlockStateData $blockStateData
	){
		parent::__construct($identifier, $name);
	}

	public function getBedrockId() : string{ return $this->bedrockId; }

	public function getBedrockMeta() : int{ return $this->bedrockMeta; }

	public function getBlockStateData() : ?BlockStateData{ return $this->blockStateData; }
}
