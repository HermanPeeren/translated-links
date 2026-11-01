<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Repair;

use Yepr\Plugin\Task\TranslatedLinks\Content\ContentItem;
use Yepr\Plugin\Task\TranslatedLinks\Content\ContentSources;
use Yepr\Plugin\Task\TranslatedLinks\Html\LinkExtractor;
use Yepr\Plugin\Task\TranslatedLinks\Html\LinkRewriter;
use Yepr\Plugin\Task\TranslatedLinks\Link\LinkTranslator;
use Yepr\Plugin\Task\TranslatedLinks\Run\Clock;
use Yepr\Plugin\Task\TranslatedLinks\Run\RunOutcome;
use Yepr\Plugin\Task\TranslatedLinks\Store\Run;
use Yepr\Plugin\Task\TranslatedLinks\Store\RunStore;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One run of the repair: goes over the articles, categories and custom modules in a
 * language of their own, by kind and id, and points their links at the pages of that
 * language, until all are done or the time budget is used up.
 *
 * Where it got to is stored per task, so the next run continues there. An item that is
 * open in an editor is skipped, so that neither save undoes the other. A dry run goes
 * through the same steps and logs what it would change, without saving anything.
 *
 * Running it again changes nothing that was repaired: a link that points at its own
 * language is left alone. So it can be run again after more menu items are made.
 */
final class Repairer
{
	/**
	 * Items read at a time.
	 */
	private const PAGE = 20;

	/**
	 * @param   callable(string, string): void  $log  Receives a message and a priority.
	 */
	public function __construct(
		private readonly ContentSources $sources,
		private readonly RunStore $runs,
		private readonly LinkExtractor $extractor,
		private readonly LinkRewriter $rewriter,
		private readonly LinkTranslator $translator,
		private readonly Clock $clock,
		private readonly RepairSettings $settings,
		private $log
	) {
	}

	public function run(int $taskId): RunOutcome
	{
		$deadline = $this->clock->now() + $this->settings->timeBudget;
		$run      = $this->runs->find($taskId);

		// A dry run that is continued for real, or the other way round, starts again.
		if ($run === null || $run->dryRun !== $this->settings->dryRun) {
			$run = new Run($taskId, $this->settings->dryRun, (string) $this->sources->first());
			$this->log('Repair of translated links started' . ($run->dryRun ? ' (dry run: nothing is saved).' : '.'));
		}

		while ($run->kind !== '') {
			$items = $this->sources->get($run->kind)->page($run->afterId, self::PAGE);

			if ($items === []) {
				$run->kind    = (string) $this->sources->after($run->kind);
				$run->afterId = 0;

				continue;
			}

			foreach ($items as $item) {
				if ($this->clock->now() >= $deadline) {
					$this->runs->save($run);
					$this->log(\sprintf('Time is up; the next run continues after %s %d.', $run->kind, $run->afterId));

					return new RunOutcome(false);
				}

				$this->repairItem($item, $run);
				$run->afterId = $item->id;
			}

			// So that a run that dies (a PHP time limit) loses at most a page.
			$this->runs->save($run);
		}

		return $this->finish($run);
	}

	private function repairItem(ContentItem $item, Run $run): void
	{
		$name     = \sprintf('%s %d "%s" (%s)', ucfirst($item->kind), $item->id, $item->title, $item->language);
		$fields   = $item->fields;
		$changes  = [];
		$problems = [];

		foreach ($item->fields as $field => $html) {
			$done = [];

			foreach ($this->extractor->extract($html) as $link) {
				// The same link more than once in a field: the first rewrite does them all.
				if (isset($done[$link->href])) {
					continue;
				}

				$done[$link->href] = true;
				$outcome           = $this->translator->translate($link->href, $item->language);

				foreach ($outcome->problems as $problem) {
					$problems[] = \sprintf('%s (link "%s"): %s', $link->href, $link->text, $problem);
				}

				if (!$outcome->changed()) {
					continue;
				}

				[$fields[$field], $count] = $this->rewriter->rewrite($fields[$field], $link->href, $outcome->newHref);

				if ($count > 0) {
					$changes[] = \sprintf('%s -> %s (%s)', $link->href, $outcome->newHref, implode('; ', $outcome->changes));
				}
			}
		}

		$run->linksLeft += \count($problems);

		foreach ($problems as $problem) {
			$this->log(\sprintf('%s: left as it is: %s', $name, $problem), 'warning');
		}

		if ($changes === []) {
			return;
		}

		if ($item->checkedOut > 0) {
			$run->itemsSkipped++;
			$this->log(\sprintf(
				'%s is checked out, so its %d link(s) are not repointed; run the task again when it is closed.',
				$name,
				\count($changes)
			), 'warning');

			return;
		}

		if ($this->settings->dryRun) {
			foreach ($changes as $change) {
				$this->log(\sprintf('Would change in %s: %s', $name, $change));
			}

			$run->itemsChanged++;
			$run->linksRepointed += \count($changes);

			return;
		}

		$note = \sprintf('Translated links: %d link(s) now point to %s pages', \count($changes), $item->language);

		try {
			$this->sources->get($item->kind)->save($item, $fields, $this->settings->userId, $note);
		} catch (\RuntimeException $e) {
			$run->itemsSkipped++;
			$this->log(\sprintf('%s could not be saved: %s', $name, $e->getMessage()), 'warning');

			return;
		}

		foreach ($changes as $change) {
			$this->log(\sprintf('Changed in %s: %s', $name, $change));
		}

		$run->itemsChanged++;
		$run->linksRepointed += \count($changes);
	}

	private function finish(Run $run): RunOutcome
	{
		$this->runs->delete($run->taskId);

		$this->log(\sprintf(
			'Repair of translated links finished%s: %d link(s) in %d item(s) %s, %d link(s) left as they are, %d item(s) skipped.',
			$run->dryRun ? ' (dry run)' : '',
			$run->linksRepointed,
			$run->itemsChanged,
			$run->dryRun ? 'would be repointed' : 'repointed',
			$run->linksLeft,
			$run->itemsSkipped
		));

		return new RunOutcome(true);
	}

	private function log(string $message, string $priority = 'info'): void
	{
		($this->log)($message, $priority);
	}
}
