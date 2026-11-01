<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Link;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What LinkTranslator made of one link.
 *
 * A link can be partly repointed and partly left: the menu item has a translation but
 * the article in the same link has not. Then it has both a new href and problems.
 */
final class Outcome
{
	/**
	 * @param   string        $newHref   The repointed link, or '' when it stays as it is.
	 * @param   list<string>  $changes   What was repointed, for the log.
	 * @param   list<string>  $problems  What points at another language and could not be repointed.
	 */
	public function __construct(
		public readonly string $newHref = '',
		public readonly array $changes = [],
		public readonly array $problems = []
	) {
	}

	public function changed(): bool
	{
		return $this->newHref !== '';
	}
}
