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

namespace pocketmine\network\mcpe\convert;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\data\bedrock\block\BlockStateStringValues;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function array_key_first;
use function array_map;
use function count;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use const JSON_THROW_ON_ERROR;

/**
 * Handles translation of network block runtime IDs into blockstate data, and vice versa
 */
final class BlockStateDictionary{
	/**
	 * @var int[][]|int[]
	 * @phpstan-var array<string, array<string, int>|int>
	 */
	private array $stateDataToStateIdLookup = [];

	/**
	 * @var int[][]|null
	 * @phpstan-var array<string, array<int, int>|int>|null
	 */
	private ?array $idMetaToStateIdLookupCache = null;

	/**
	 * @var Tag[]|null
	 * @phpstan-var array<string, Tag>|null
	 */
	private static ?array $clientModelPropertyDefaults = null;

	/**
	 * @param BlockStateDictionaryEntry[] $states
	 *
	 * @phpstan-param list<BlockStateDictionaryEntry> $states
	 */
	public function __construct(
		private array $states
	){
		$table = [];
		foreach($this->states as $stateId => $stateNbt){
			$table[$stateNbt->getStateName()][$stateNbt->getRawStateProperties()] = $stateId;
		}

		//setup fast path for stateless blocks
		foreach(Utils::stringifyKeys($table) as $name => $stateIds){
			if(count($stateIds) === 1){
				$this->stateDataToStateIdLookup[$name] = $stateIds[array_key_first($stateIds)];
			}else{
				$this->stateDataToStateIdLookup[$name] = $stateIds;
			}
		}

		$standardSkull = $this->stateDataToStateIdLookup[BlockTypeNames::SKELETON_SKULL];
		foreach([
			BlockTypeNames::WITHER_SKELETON_SKULL,
			BlockTypeNames::ZOMBIE_HEAD,
			BlockTypeNames::PLAYER_HEAD,
			BlockTypeNames::CREEPER_HEAD,
			BlockTypeNames::DRAGON_HEAD,
			BlockTypeNames::PIGLIN_HEAD
		] as $skull){
			if(!isset($this->stateDataToStateIdLookup[$skull])){
				$this->stateDataToStateIdLookup[$skull] = $standardSkull;
			}
		}
	}

	/**
	 * @return int[][]
	 * @phpstan-return array<string, array<int, int>|int>
	 */
	private function getIdMetaToStateIdLookup() : array{
		if($this->idMetaToStateIdLookupCache === null){
			$table = [];
			//TODO: if we ever allow mutating the dictionary, this would need to be rebuilt on modification

			foreach($this->states as $i => $state){
				$table[$state->getStateName()][$state->getMeta()] = $i;
			}

			$this->idMetaToStateIdLookupCache = [];
			foreach(Utils::stringifyKeys($table) as $name => $metaToStateId){
				//if only one meta value exists
				if(count($metaToStateId) === 1){
					$this->idMetaToStateIdLookupCache[$name] = $metaToStateId[array_key_first($metaToStateId)];
				}else{
					$this->idMetaToStateIdLookupCache[$name] = $metaToStateId;
				}
			}
		}

		return $this->idMetaToStateIdLookupCache;
	}

	public function generateDataFromStateId(int $networkRuntimeId) : ?BlockStateData{
		return ($this->states[$networkRuntimeId] ?? null)?->generateStateData();
	}

	public function generateCurrentDataFromStateId(int $networkRuntimeId) : ?BlockStateData{
		return ($this->states[$networkRuntimeId] ?? null)?->generateCurrentStateData();
	}

	/**
	 * Searches for the appropriate state ID which matches the given blockstate NBT.
	 * Returns null if there were no matches.
	 */
	public function lookupStateIdFromData(BlockStateData $data) : ?int{
		$name = $data->getName();

		$lookup = $this->stateDataToStateIdLookup[$name] ?? null;
		if($lookup === null){
			return null;
		}
		if(is_int($lookup)){
			return $lookup;
		}

		$stateId = $lookup[BlockStateDictionaryEntry::encodeStateProperties($data->getStates())] ?? null;
		if($stateId !== null){
			return $stateId;
		}

		$simplified = self::resetClientModelProperties($data->getStates());
		return $simplified === null ? null : ($lookup[BlockStateDictionaryEntry::encodeStateProperties($simplified)] ?? null);
	}

	/**
	 * @return Tag[]
	 * @phpstan-return array<string, Tag>
	 */
	private static function getClientModelPropertyDefaults() : array{
		return self::$clientModelPropertyDefaults ??= [
			BlockStateNames::MC_CORNER => new StringTag(BlockStateStringValues::MC_CORNER_NONE),
			BlockStateNames::MC_CONNECTION_NORTH => new ByteTag(0),
			BlockStateNames::MC_CONNECTION_SOUTH => new ByteTag(0),
			BlockStateNames::MC_CONNECTION_WEST => new ByteTag(0),
			BlockStateNames::MC_CONNECTION_EAST => new ByteTag(0),
		];
	}

	/**
	 * Resets properties which only influence the client-side model of a block to their default values. Older versions
	 * don't know about some of these values (e.g. stair corners and fence connections were added in 1.26.50), so this
	 * allows such blocks to keep working on older protocols instead of turning into unknown blocks.
	 *
	 * Returns null if the given states didn't contain any of these properties.
	 *
	 * @param Tag[] $states
	 * @phpstan-param array<string, Tag> $states
	 *
	 * @return Tag[]|null
	 * @phpstan-return array<string, Tag>|null
	 */
	private static function resetClientModelProperties(array $states) : ?array{
		$changed = false;
		foreach(Utils::stringifyKeys(self::getClientModelPropertyDefaults()) as $name => $default){
			$tag = $states[$name] ?? null;
			if($tag === null || $tag->equals($default)){
				continue;
			}
			$states[$name] = $default;
			$changed = true;
		}

		return $changed ? $states : null;
	}

	/**
	 * Returns the blockstate meta value associated with the given blockstate runtime ID.
	 * This is used for serializing crafting recipe inputs.
	 */
	public function getMetaFromStateId(int $networkRuntimeId) : ?int{
		return ($this->states[$networkRuntimeId] ?? null)?->getMeta();
	}

	/**
	 * Returns the blockstate data associated with the given block ID and meta value.
	 * This is used for deserializing crafting recipe inputs.
	 */
	public function lookupStateIdFromIdMeta(string $id, int $meta) : ?int{
		$metas = $this->getIdMetaToStateIdLookup()[$id] ?? null;
		return match(true){
			$metas === null => null,
			is_int($metas) => $metas,
			is_array($metas) => $metas[$meta] ?? null
		};
	}

	/**
	 * Returns an array mapping runtime ID => blockstate data.
	 * @return BlockStateDictionaryEntry[]
	 * @phpstan-return array<int, BlockStateDictionaryEntry>
	 */
	public function getStates() : array{ return $this->states; }

	/**
	 * @return BlockStateData[]
	 * @phpstan-return list<BlockStateData>
	 *
	 * @throws NbtDataException
	 */
	public static function loadPaletteFromString(string $blockPaletteContents) : array{
		return array_map(
			fn(TreeRoot $root) => BlockStateData::fromNbt($root->mustGetCompoundTag()),
			(new NetworkNbtSerializer())->readMultiple($blockPaletteContents)
		);
	}

	public static function loadFromString(string $blockPaletteContents, string $metaMapContents) : self{
		$upgrader = GlobalBlockStateHandlers::getUpgrader()->getBlockStateUpgrader();
		$metaMap = json_decode($metaMapContents, flags: JSON_THROW_ON_ERROR);
		if(!is_array($metaMap)){
			throw new \InvalidArgumentException("Invalid metaMap, expected array for root type, got " . get_debug_type($metaMap));
		}

		$entries = [];

		$uniqueNames = [];

		//this hack allows the internal cache index to use interned strings which are already available in the
		//core code anyway, saving around 40 KB of memory
		foreach((new \ReflectionClass(BlockTypeNames::class))->getConstants() as $value){
			if(is_string($value)){
				$uniqueNames[$value] = $value;
			}
		}

		foreach(self::loadPaletteFromString($blockPaletteContents) as $i => $state){
			$meta = $metaMap[$i] ?? null;
			if($meta === null){
				throw new \InvalidArgumentException("Missing associated meta value for state $i (" . $state->toNbt() . ")");
			}
			if(!is_int($meta)){
				throw new \InvalidArgumentException("Invalid metaMap offset $i, expected int, got " . get_debug_type($meta));
			}
			$newState = $upgrader->upgrade($state);
			$uniqueName = $uniqueNames[$newState->getName()] ??= $newState->getName();
			$entries[$i] = new BlockStateDictionaryEntry($uniqueName, $newState->getStates(), $meta, $newState->equals($state) ? null : $state);
		}

		return new self($entries);
	}
}
