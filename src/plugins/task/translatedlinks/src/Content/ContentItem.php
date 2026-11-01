<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Content;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Something with HTML that can hold links: an article, a category or a custom module in
 * a language of its own, with its text fields by name.
 */
final class ContentItem
{
	public const ARTICLE  = 'article';
	public const CATEGORY = 'category';
	public const MODULE   = 'module';

	/**
	 * @param   string                 $kind        One of the constants above.
	 * @param   string                 $language    Its language code, for example "nl-NL".
	 * @param   array<string, string>  $fields      The HTML fields, by column name, in a fixed order.
	 * @param   int                    $checkedOut  The user who has it open in an editor; 0 for nobody.
	 */
	public function __construct(
		public readonly string $kind,
		public readonly int $id,
		public readonly string $title,
		public readonly string $language,
		public readonly array $fields,
		public readonly int $checkedOut
	) {
	}
}
