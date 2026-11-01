<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Store;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A repair that is under way for one task, across the scheduler's runs of it: where it
 * is (the kind of content, and the last id done) and what it did so far.
 */
final class Run
{
	public function __construct(
		public readonly int $taskId,
		public readonly bool $dryRun,
		public string $kind,
		public int $afterId = 0,
		public int $itemsChanged = 0,
		public int $linksRepointed = 0,
		public int $linksLeft = 0,
		public int $itemsSkipped = 0
	) {
	}
}
