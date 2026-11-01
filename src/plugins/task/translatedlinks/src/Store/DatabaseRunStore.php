<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Store;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * RunStore on `#__translatedlinks_runs`: one row per task with a repair under way.
 */
final class DatabaseRunStore implements RunStore
{
	private const RUNS = '#__translatedlinks_runs';

	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	public function find(int $taskId): ?Run
	{
		$query = $this->db->getQuery(true)
			->select('*')
			->from($this->db->quoteName(self::RUNS))
			->where($this->db->quoteName('task_id') . ' = :taskId')
			->bind(':taskId', $taskId, ParameterType::INTEGER);

		$row = $this->db->setQuery($query)->loadObject();

		if ($row === null) {
			return null;
		}

		return new Run(
			(int) $row->task_id,
			(bool) $row->dry_run,
			(string) $row->kind,
			(int) $row->after_id,
			(int) $row->items_changed,
			(int) $row->links_repointed,
			(int) $row->links_left,
			(int) $row->items_skipped
		);
	}

	public function save(Run $run): void
	{
		$this->delete($run->taskId);

		$taskId    = $run->taskId;
		$kind      = $run->kind;
		$afterId   = $run->afterId;
		$dryRun    = (int) $run->dryRun;
		$changed   = $run->itemsChanged;
		$repointed = $run->linksRepointed;
		$left      = $run->linksLeft;
		$skipped   = $run->itemsSkipped;
		$modified  = gmdate('Y-m-d H:i:s');

		$query = $this->db->getQuery(true)
			->insert($this->db->quoteName(self::RUNS))
			->columns($this->db->quoteName([
				'task_id', 'kind', 'after_id', 'dry_run', 'items_changed', 'links_repointed', 'links_left', 'items_skipped', 'modified',
			]))
			->values(':taskId, :kind, :afterId, :dryRun, :changed, :repointed, :left, :skipped, :modified')
			->bind(':taskId', $taskId, ParameterType::INTEGER)
			->bind(':kind', $kind)
			->bind(':afterId', $afterId, ParameterType::INTEGER)
			->bind(':dryRun', $dryRun, ParameterType::INTEGER)
			->bind(':changed', $changed, ParameterType::INTEGER)
			->bind(':repointed', $repointed, ParameterType::INTEGER)
			->bind(':left', $left, ParameterType::INTEGER)
			->bind(':skipped', $skipped, ParameterType::INTEGER)
			->bind(':modified', $modified);

		$this->db->setQuery($query)->execute();
	}

	public function delete(int $taskId): void
	{
		$query = $this->db->getQuery(true)
			->delete($this->db->quoteName(self::RUNS))
			->where($this->db->quoteName('task_id') . ' = :taskId')
			->bind(':taskId', $taskId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}
}
