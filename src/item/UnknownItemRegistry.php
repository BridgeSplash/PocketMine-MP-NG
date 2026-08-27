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
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use function explode;
use function implode;
use function str_contains;
use function ucwords;

/**
 * Holds placeholder {@link UnknownItem} types for item types which the client knows about, but the server doesn't
 * implement. These are only created from trusted offline data (the vanilla creative contents), so world data can never
 * cause new types to be allocated.
 */
final class UnknownItemRegistry{
	use SingletonTrait;

	private const MAX_NAME_WORDS = 16;

	/**
	 * @var UnknownItem[]
	 * @phpstan-var array<string, UnknownItem>
	 */
	private array $items = [];

	/**
	 * Registers a placeholder for the given Bedrock item identity, or returns the existing one if it was already
	 * registered.
	 */
	public function register(string $bedrockId, int $meta, ?BlockStateData $blockStateData) : UnknownItem{
		$key = self::key($bedrockId, $meta);
		if(isset($this->items[$key])){
			return $this->items[$key];
		}

		$item = new UnknownItem(
			new ItemIdentifier(ItemTypeIds::newId()),
			self::makeDisplayName($bedrockId),
			$bedrockId,
			$meta,
			$blockStateData
		);
		$this->items[$key] = $item;

		GlobalItemDataHandlers::getSerializer()->map(
			$item,
			fn(UnknownItem $i) => new SavedItemData($i->getBedrockId(), $i->getBedrockMeta(), $i->getBlockStateData())
		);

		return $item;
	}

	public function get(string $bedrockId, int $meta) : ?UnknownItem{
		$item = $this->items[self::key($bedrockId, $meta)] ?? null;
		return $item === null ? null : clone $item;
	}

	/**
	 * @return UnknownItem[]
	 * @phpstan-return list<UnknownItem>
	 */
	public function getAll() : array{
		$result = [];
		foreach($this->items as $item){
			$result[] = clone $item;
		}
		return $result;
	}

	private static function key(string $bedrockId, int $meta) : string{
		return $bedrockId . ":" . $meta;
	}

	/**
	 * Turns a Bedrock identifier such as minecraft:cherry_boat into a human-readable name such as "Cherry Boat".
	 */
	private static function makeDisplayName(string $bedrockId) : string{
		$name = str_contains($bedrockId, ":") ? explode(":", $bedrockId, 2)[1] : $bedrockId;
		return ucwords(implode(" ", explode("_", $name, self::MAX_NAME_WORDS)));
	}
}
