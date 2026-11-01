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
 * Where an unfinished repair is, per task, so the next run continues there.
 */
interface RunStore
{
	public function find(int $taskId): ?Run;

	public function save(Run $run): void;

	public function delete(int $taskId): void;
}
