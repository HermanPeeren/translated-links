<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Link;

use Yepr\Plugin\Task\TranslatedLinks\Translations\MenuItem;
use Yepr\Plugin\Task\TranslatedLinks\Translations\Translations;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Points a link in an item of one language at the pages of that language.
 *
 * A translated article keeps the links of its source: `index.php?Itemid=123` opens the
 * source language's menu item, so a reader of the translation lands on a page in the
 * source language. This finds the equivalent in the item's language:
 *
 * - for the menu item (Itemid): the menu item associated with it in that language;
 *   failing that, for an alias menu item, the equivalent of the menu item it stands for;
 *   failing that, a menu item in that language that opens the translation of the
 *   article or category the menu item opens. So a menu item that nobody associated
 *   with its source is found too.
 * - for an article or category in a com_content link (id, catid): its translation.
 *
 * A link that already points at the right language, or at an item for all languages,
 * stays as it is. So does a link whose equivalent does not exist (yet): that is
 * reported, to be run again once the menu items are made.
 */
final class LinkTranslator
{
	/**
	 * How many alias menu items in a row are followed.
	 */
	private const MAX_ALIASES = 3;

	/**
	 * The ids in a com_content link that open an article or a category, per view.
	 *
	 * @var  array<string, array<string, string>>
	 */
	private const CONTENT_IDS = [
		'article'    => ['id' => Translations::ARTICLE, 'catid' => Translations::CATEGORY],
		'category'   => ['id' => Translations::CATEGORY],
		'categories' => ['id' => Translations::CATEGORY],
	];

	public function __construct(private readonly Translations $translations)
	{
	}

	/**
	 * @param   string  $href      The link, decoded, as LinkExtractor gives it.
	 * @param   string  $language  The language of the item the link is in.
	 */
	public function translate(string $href, string $language): Outcome
	{
		$link = JoomlaLink::parse($href);

		if ($link === null) {
			return new Outcome();
		}

		$new      = $link;
		$changes  = [];
		$problems = [];
		$itemId   = $link->id('Itemid');

		if ($itemId > 0) {
			$menuItem = $this->translations->menuItem($itemId);

			if ($menuItem === null) {
				$problems[] = \sprintf('menu item %d does not exist or is trashed', $itemId);
			} elseif ($menuItem->language !== $language) {
				$equivalent = $this->equivalent($menuItem, $language, 0);

				if ($equivalent !== null) {
					$new       = $new->with('Itemid', (string) $equivalent->id);
					$changes[] = \sprintf(
						'menu item %d "%s" (%s) -> %d "%s" (%s)%s',
						$menuItem->id,
						$menuItem->title,
						$menuItem->language,
						$equivalent->id,
						$equivalent->title,
						$equivalent->language,
						$equivalent->published ? '' : ', not published yet'
					);
				} elseif (!$menuItem->forAllLanguages() || $this->opensOtherLanguage($menuItem, $language)) {
					$problems[] = \sprintf(
						'menu item %d "%s" (%s) has no equivalent in %s yet',
						$menuItem->id,
						$menuItem->title,
						$menuItem->language,
						$language
					);
				}
			}
		}

		if ($link->get('option') === 'com_content') {
			foreach (self::CONTENT_IDS[$link->get('view')] ?? [] as $parameter => $kind) {
				$id = $link->id($parameter);

				if ($id <= 0) {
					continue;
				}

				[$translated, $problem] = $this->translateItem($kind, $id, $language);

				if ($translated !== null) {
					$new       = $new->with($parameter, (string) $translated);
					$changes[] = \sprintf('%s %d -> %d', $kind, $id, $translated);
				} elseif ($problem !== null) {
					$problems[] = $problem;
				}
			}
		}

		$new = $this->withLanguage($new, $language, $changes !== []);

		if ($new->get('lang') !== $link->get('lang')) {
			$changes[] = \sprintf('lang %s -> %s', $link->get('lang') === '' ? '(none)' : $link->get('lang'), $new->get('lang'));
		}

		if ($new->href() === $link->href()) {
			return new Outcome('', [], $problems);
		}

		return new Outcome($new->href(), $changes, $problems);
	}

	/**
	 * The menu item in $language that stands for $menuItem, or null.
	 */
	private function equivalent(MenuItem $menuItem, string $language, int $aliases): ?MenuItem
	{
		$associated = $this->translations->translation(Translations::MENU_ITEM, $menuItem->id, $language);

		if ($associated !== null && ($found = $this->translations->menuItem($associated)) !== null) {
			return $found;
		}

		if ($menuItem->type === MenuItem::ALIAS && $menuItem->aliasTarget > 0 && $aliases < self::MAX_ALIASES) {
			$target = $this->translations->menuItem($menuItem->aliasTarget);

			if ($target !== null) {
				return $target->language === $language ? $target : $this->equivalent($target, $language, $aliases + 1);
			}
		}

		$kind = $this->kindOpenedBy($menuItem);

		if ($kind === null) {
			return null;
		}

		[$translated] = $this->translateItem($kind, $menuItem->opensId(), $language);

		return $translated === null ? null : $this->translations->menuItemOpening($menuItem->opens(), $translated, $language);
	}

	/**
	 * Whether a menu item for all languages opens an article or category in another
	 * language than $language: then its link is wrong in a $language item too.
	 */
	private function opensOtherLanguage(MenuItem $menuItem, string $language): bool
	{
		$kind = $this->kindOpenedBy($menuItem);

		if ($kind === null) {
			return false;
		}

		$opened = $this->translations->language($kind, $menuItem->opensId());

		return $opened !== null && $opened !== '*' && $opened !== $language;
	}

	/**
	 * Translations::ARTICLE or ::CATEGORY for a menu item that opens one, else null.
	 */
	private function kindOpenedBy(MenuItem $menuItem): ?string
	{
		if ($menuItem->opensId() <= 0) {
			return null;
		}

		return match ($menuItem->opens()) {
			'com_content.article'                           => Translations::ARTICLE,
			'com_content.category', 'com_content.categories' => Translations::CATEGORY,
			default                                         => null,
		};
	}

	/**
	 * The translation in $language of an article or category, where it is in another one.
	 *
	 * @return  array{0: int|null, 1: string|null}  The translation's id, or null with why
	 *          not: null when the item is right as it is.
	 */
	private function translateItem(string $kind, int $id, string $language): array
	{
		$itemLanguage = $this->translations->language($kind, $id);

		if ($itemLanguage === null) {
			return [null, \sprintf('%s %d does not exist', $kind, $id)];
		}

		if ($itemLanguage === $language || $itemLanguage === '*') {
			return [null, null];
		}

		$translated = $this->translations->translation($kind, $id, $language);

		return $translated !== null
			? [$translated, null]
			: [null, \sprintf('%s %d (%s) has no translation in %s', $kind, $id, $itemLanguage, $language)];
	}

	/**
	 * The link's lang parameter set to $language where it should be: where it has one
	 * and points at a page in $language, or, for a repointed link, where its menu item is
	 * in $language (the editor's Menu button adds one for such a menu item).
	 */
	private function withLanguage(JoomlaLink $link, string $language, bool $repointed): JoomlaLink
	{
		$menuItem = $this->translations->menuItem($link->id('Itemid'));
		$inTarget = $menuItem !== null && $menuItem->language === $language;
		$current  = $link->get('lang');

		if ($current !== '' && $current !== $language && ($repointed || $inTarget)) {
			return $link->with('lang', $language);
		}

		if ($current === '' && $repointed && $inTarget) {
			return $link->with('lang', $language);
		}

		return $link;
	}
}
