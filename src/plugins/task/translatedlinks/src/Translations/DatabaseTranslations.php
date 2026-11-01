<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Translations;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Translations on `#__menu`, `#__content`, `#__categories` and `#__associations`.
 *
 * The site menu items are read once, on first use; languages and association groups
 * per item, each once. A run of the task reads every menu item it meets many times.
 */
final class DatabaseTranslations implements Translations
{
	private const TRASHED = -2;

	/**
	 * Per kind: the table, its state column and its associations context.
	 *
	 * @var  array<string, array{0: string, 1: string, 2: string}>
	 */
	private const KINDS = [
		self::MENU_ITEM => ['#__menu', 'published', 'com_menus.item'],
		self::ARTICLE   => ['#__content', 'state', 'com_content.item'],
		self::CATEGORY  => ['#__categories', 'published', 'com_categories.item'],
	];

	/**
	 * @var  array<int, MenuItem>|null
	 */
	private ?array $menuItems = null;

	/**
	 * Menu items by what they open: "option.view.id".
	 *
	 * @var  array<string, list<MenuItem>>
	 */
	private array $byTarget = [];

	/**
	 * @var  array<string, string|null>  By "kind.id".
	 */
	private array $languages = [];

	/**
	 * @var  array<string, array<string, int>>  By "kind.id": the ids of its group, by language.
	 */
	private array $groups = [];

	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	public function menuItem(int $id): ?MenuItem
	{
		return $this->menuItems()[$id] ?? null;
	}

	public function menuItemOpening(string $opens, int $id, string $language): ?MenuItem
	{
		$this->menuItems();

		$best = null;
		$rank = 0;

		foreach ($this->byTarget[$opens . '.' . $id] ?? [] as $item) {
			$itemRank = ($item->language === $language ? 2 : ($item->forAllLanguages() ? 0 : -1)) + ($item->published ? 1 : 0);

			if ($itemRank >= 0 && ($best === null || $itemRank > $rank)) {
				$best = $item;
				$rank = $itemRank;
			}
		}

		return $best;
	}

	public function language(string $kind, int $id): ?string
	{
		$key = $kind . '.' . $id;

		if (\array_key_exists($key, $this->languages)) {
			return $this->languages[$key];
		}

		[$table] = $this->kind($kind);

		$query = $this->db->getQuery(true)
			->select($this->db->quoteName('language'))
			->from($this->db->quoteName($table))
			->where($this->db->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		$language = $this->db->setQuery($query)->loadResult();

		return $this->languages[$key] = $language === null ? null : (string) $language;
	}

	public function translation(string $kind, int $id, string $language): ?int
	{
		$key = $kind . '.' . $id;

		if (!isset($this->groups[$key])) {
			$this->groups[$key] = $this->group($kind, $id);
		}

		return $this->groups[$key][$language] ?? null;
	}

	/**
	 * The ids of the item's association group that are not trashed, by language.
	 *
	 * @return  array<string, int>
	 */
	private function group(string $kind, int $id): array
	{
		[$table, $state, $context] = $this->kind($kind);

		$trashed      = self::TRASHED;
		$otherContext = $context;

		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['item.language', 'item.id']))
			->from($this->db->quoteName('#__associations', 'own'))
			->join(
				'INNER',
				$this->db->quoteName('#__associations', 'other'),
				$this->db->quoteName('other.key') . ' = ' . $this->db->quoteName('own.key')
			)
			->join('INNER', $this->db->quoteName($table, 'item'), $this->db->quoteName('item.id') . ' = ' . $this->db->quoteName('other.id'))
			->where($this->db->quoteName('own.context') . ' = :context')
			->where($this->db->quoteName('other.context') . ' = :otherContext')
			->where($this->db->quoteName('own.id') . ' = :id')
			->where($this->db->quoteName('item.' . $state) . ' <> :trashed')
			->bind(':context', $context)
			->bind(':otherContext', $otherContext)
			->bind(':id', $id, ParameterType::INTEGER)
			->bind(':trashed', $trashed, ParameterType::INTEGER);

		$group = [];

		foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
			$group[(string) $row->language] = (int) $row->id;
		}

		return $group;
	}

	/**
	 * @return  array{0: string, 1: string, 2: string}  The table, its state column and its associations context.
	 *
	 * @throws  \InvalidArgumentException  For a kind that is not one of the constants.
	 */
	private function kind(string $kind): array
	{
		return self::KINDS[$kind] ?? throw new \InvalidArgumentException('No such kind of item: ' . $kind);
	}

	/**
	 * @return  array<int, MenuItem>
	 */
	private function menuItems(): array
	{
		if ($this->menuItems !== null) {
			return $this->menuItems;
		}

		$client  = 0;
		$root    = 1;
		$trashed = self::TRASHED;

		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'title', 'language', 'published', 'type', 'link', 'params']))
			->from($this->db->quoteName('#__menu'))
			->where($this->db->quoteName('client_id') . ' = :client')
			->where($this->db->quoteName('id') . ' > :root')
			->where($this->db->quoteName('published') . ' <> :trashed')
			->order($this->db->quoteName('id'))
			->bind(':client', $client, ParameterType::INTEGER)
			->bind(':root', $root, ParameterType::INTEGER)
			->bind(':trashed', $trashed, ParameterType::INTEGER);

		$this->menuItems = [];

		foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
			$type = (string) $row->type;
			$link = [];
			parse_str((string) parse_url(html_entity_decode((string) $row->link), PHP_URL_QUERY), $link);

			$params      = json_decode((string) $row->params, true);
			$aliasTarget = $type === MenuItem::ALIAS && \is_array($params) && is_numeric($params['aliasoptions'] ?? null)
				? (int) $params['aliasoptions']
				: 0;

			/** @var array<string, mixed> $link */
			$item = new MenuItem(
				(int) $row->id,
				(string) $row->title,
				(string) $row->language,
				(int) $row->published === 1,
				$type,
				$link,
				$aliasTarget
			);

			$this->menuItems[$item->id] = $item;

			if ($type === 'component' && $item->opens() !== '' && $item->opensId() > 0) {
				$this->byTarget[$item->opens() . '.' . $item->opensId()][] = $item;
			}
		}

		return $this->menuItems;
	}
}
