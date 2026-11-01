<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Translations;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What the site knows about items in other languages: its menu items, the languages of
 * articles and categories, and which items are translations of each other (Joomla's
 * associations).
 */
interface Translations
{
	public const MENU_ITEM = 'menuitem';
	public const ARTICLE   = 'article';
	public const CATEGORY  = 'category';

	/**
	 * A site menu item that is not trashed, or null.
	 */
	public function menuItem(int $id): ?MenuItem;

	/**
	 * A menu item in $language, or for all languages, that opens exactly this: the same
	 * option and view, and the same id. One in $language itself is preferred, and of
	 * those a published one.
	 *
	 * @param   string  $opens     "option.view", as MenuItem::opens() gives it.
	 */
	public function menuItemOpening(string $opens, int $id, string $language): ?MenuItem;

	/**
	 * The language of an article or a category ("*" for all), or null when there is none
	 * with that id.
	 *
	 * @param   string  $kind  self::ARTICLE or self::CATEGORY.
	 */
	public function language(string $kind, int $id): ?string;

	/**
	 * The item associated with this one in $language: its translation, not trashed.
	 *
	 * @param   string  $kind  One of the constants above.
	 *
	 * @return  int|null  Its id, or null when there is none.
	 */
	public function translation(string $kind, int $id, string $language): ?int;
}
