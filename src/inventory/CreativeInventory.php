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

namespace pocketmine\inventory;

use pocketmine\crafting\CraftingManagerFromDataHelper;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\inventory\json\CreativeGroupData;
use pocketmine\item\Item;
use pocketmine\lang\Translatable;
use pocketmine\utils\DestructorCallbackTrait;
use pocketmine\utils\ObjectSet;
use pocketmine\utils\SingletonTrait;
use Symfony\Component\Filesystem\Path;
use function array_map;
use function str_starts_with;

final class CreativeInventory{
	use SingletonTrait;
	use DestructorCallbackTrait;

	/**
	 * The vanilla creative contents include Education Edition items, which vanilla only shows when Education features
	 * are enabled. Since those features aren't supported, they'd only be clutter in the creative menu.
	 *
	 * Prefixes cover the chemistry elements and compounds, plus the hardened glass family.
	 */
	private const EDUCATION_ONLY_ID_PREFIXES = [
		"minecraft:compound",
		"minecraft:element_",
		"minecraft:hard_",
	];

	private const EDUCATION_ONLY_IDS = [
		"minecraft:balloon" => true,
		"minecraft:bleach" => true,
		"minecraft:chemical_heat" => true,
		"minecraft:colored_torch_blue" => true,
		"minecraft:colored_torch_green" => true,
		"minecraft:colored_torch_purple" => true,
		"minecraft:colored_torch_red" => true,
		"minecraft:glow_stick" => true,
		"minecraft:ice_bomb" => true,
		"minecraft:lab_table" => true,
		"minecraft:material_reducer" => true,
		"minecraft:medicine" => true,
		"minecraft:rapid_fertilizer" => true,
		"minecraft:sparkler" => true,
		"minecraft:super_fertilizer" => true,
		"minecraft:underwater_tnt" => true,
		"minecraft:underwater_torch" => true,
	];

	/**
	 * Groups listed here are ignored, so their contents are shown as individual entries instead of collapsing into a
	 * single expandable icon. Spawn eggs are only grouped because the vanilla data says so - most of them aren't
	 * implemented, and hiding the implemented ones behind a category isn't useful.
	 */
	private const UNGROUPED_GROUP_NAMES = [
		"itemGroup.name.mobEgg" => true,
	];

	/**
	 * @var CreativeInventoryEntry[]
	 * @phpstan-var array<int, CreativeInventoryEntry>
	 */
	private array $creative = [];

	/** @phpstan-var ObjectSet<\Closure() : void> */
	private ObjectSet $contentChangedCallbacks;

	private function __construct(){
		$this->contentChangedCallbacks = new ObjectSet();

		foreach([
			"construction" => CreativeCategory::CONSTRUCTION,
			"nature" => CreativeCategory::NATURE,
			"equipment" => CreativeCategory::EQUIPMENT,
			"items" => CreativeCategory::ITEMS,
		] as $categoryId => $categoryEnum){
			$groups = CraftingManagerFromDataHelper::loadJsonArrayOfObjectsFile(
				Path::join(BedrockDataFiles::CREATIVE, $categoryId . ".json"),
				CreativeGroupData::class
			);

			foreach($groups as $groupData){
				$icon = isset(self::UNGROUPED_GROUP_NAMES[$groupData->group_name]) || $groupData->group_icon === null ?
					null :
					CraftingManagerFromDataHelper::deserializeItemStack($groupData->group_icon);

				$group = $icon === null ? null : new CreativeGroup(
					new Translatable($groupData->group_name),
					$icon
				);

				foreach($groupData->items as $itemStack){
					if(self::isEducationOnly($itemStack->name)){
						continue;
					}
					$item = CraftingManagerFromDataHelper::deserializeCreativeItemStack($itemStack);
					if($item !== null){
						$this->add($item, $categoryEnum, $group);
					}
				}
			}
		}
	}

	private static function isEducationOnly(string $bedrockId) : bool{
		if(isset(self::EDUCATION_ONLY_IDS[$bedrockId])){
			return true;
		}
		foreach(self::EDUCATION_ONLY_ID_PREFIXES as $prefix){
			if(str_starts_with($bedrockId, $prefix)){
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes all previously added items from the creative menu.
	 * Note: Players who are already online when this is called will not see this change.
	 */
	public function clear() : void{
		$this->creative = [];
		$this->onContentChange();
	}

	/**
	 * @return Item[]
	 * @phpstan-return array<int, Item>
	 */
	public function getAll() : array{
		return array_map(fn(CreativeInventoryEntry $entry) => $entry->getItem(), $this->creative);
	}

	/**
	 * @return CreativeInventoryEntry[]
	 * @phpstan-return array<int, CreativeInventoryEntry>
	 */
	public function getAllEntries() : array{
		return $this->creative;
	}

	public function getItem(int $index) : ?Item{
		return $this->getEntry($index)?->getItem();
	}

	public function getEntry(int $index) : ?CreativeInventoryEntry{
		return $this->creative[$index] ?? null;
	}

	public function getItemIndex(Item $item) : int{
		foreach($this->creative as $i => $d){
			if($d->matchesItem($item)){
				return $i;
			}
		}

		return -1;
	}

	/**
	 * Adds an item to the creative menu.
	 * Note: Players who are already online when this is called will not see this change.
	 */
	public function add(Item $item, CreativeCategory $category = CreativeCategory::ITEMS, ?CreativeGroup $group = null) : void{
		$this->creative[] = new CreativeInventoryEntry($item, $category, $group);
		$this->onContentChange();
	}

	/**
	 * Removes an item from the creative menu.
	 * Note: Players who are already online when this is called will not see this change.
	 */
	public function remove(Item $item) : void{
		$index = $this->getItemIndex($item);
		if($index !== -1){
			unset($this->creative[$index]);
			$this->onContentChange();
		}
	}

	public function contains(Item $item) : bool{
		return $this->getItemIndex($item) !== -1;
	}

	/** @phpstan-return ObjectSet<\Closure() : void> */
	public function getContentChangedCallbacks() : ObjectSet{
		return $this->contentChangedCallbacks;
	}

	private function onContentChange() : void{
		foreach($this->contentChangedCallbacks as $callback){
			$callback();
		}
	}
}
