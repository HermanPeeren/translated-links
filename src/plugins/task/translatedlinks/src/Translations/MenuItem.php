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
 * A site menu item that is not trashed, as far as finding its equivalent in another
 * language is concerned.
 */
final class MenuItem
{
	public const ALIAS = 'alias';

	/**
	 * @param   string                $language     A language code ("en-GB"), or "*" for all languages.
	 * @param   string                $type         "component", "alias", "url", "heading", ...
	 * @param   array<string, mixed>  $query        The query of its link: option, view, id and so on.
	 * @param   int                   $aliasTarget  For an alias menu item, the menu item it stands for; else 0.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $language,
		public readonly bool $published,
		public readonly string $type,
		public readonly array $query,
		public readonly int $aliasTarget = 0
	) {
	}

	public function forAllLanguages(): bool
	{
		return $this->language === '*' || $this->language === '';
	}

	/**
	 * What the menu item opens, as "option.view" ("com_content.article"), or '' for a
	 * menu item that opens no component view.
	 */
	public function opens(): string
	{
		$option = $this->query['option'] ?? '';
		$view   = $this->query['view'] ?? '';

		return \is_string($option) && \is_string($view) && $option !== '' && $view !== '' ? $option . '.' . $view : '';
	}

	/**
	 * The id of the item it opens (the article of an article menu item), or 0.
	 */
	public function opensId(): int
	{
		$id = $this->query['id'] ?? 0;

		return \is_scalar($id) ? (int) $id : 0;
	}
}
