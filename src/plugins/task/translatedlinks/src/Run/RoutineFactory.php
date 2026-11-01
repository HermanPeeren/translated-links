<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Run;

use Yepr\Plugin\Task\TranslatedLinks\Content\ContentSources;
use Yepr\Plugin\Task\TranslatedLinks\Html\LinkExtractor;
use Yepr\Plugin\Task\TranslatedLinks\Html\LinkRewriter;
use Yepr\Plugin\Task\TranslatedLinks\Link\LinkTranslator;
use Yepr\Plugin\Task\TranslatedLinks\Repair\Repairer;
use Yepr\Plugin\Task\TranslatedLinks\Repair\RepairSettings;
use Yepr\Plugin\Task\TranslatedLinks\Store\RunStore;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds a Repairer for one task run.
 *
 * The services that are the same for every run are injected here once, by the
 * plugin's service provider. What differs per task - dry run, the user, the time
 * budget - comes from the task's parameters, so the repairer is made here, per run,
 * and nowhere else.
 */
final class RoutineFactory
{
	public const DEFAULT_BUDGET = 20;
	public const MIN_BUDGET     = 5;
	public const MAX_BUDGET     = 300;

	public function __construct(
		private readonly ContentSources $sources,
		private readonly RunStore $runs,
		private readonly LinkExtractor $extractor,
		private readonly LinkRewriter $rewriter,
		private readonly LinkTranslator $translator,
		private readonly Clock $clock
	) {
	}

	/**
	 * @param   object                          $params  The task's parameters.
	 * @param   callable(string, string): void  $log     Receives a message and a priority.
	 */
	public function repairer(object $params, callable $log): Repairer
	{
		$settings = new RepairSettings(
			// On unless switched off: a task saved without the field changes nothing.
			(bool) ($params->dry_run ?? true),
			max(0, (int) ($params->as_user ?? 0)),
			$this->budget($params)
		);

		return new Repairer($this->sources, $this->runs, $this->extractor, $this->rewriter, $this->translator, $this->clock, $settings, $log);
	}

	private function budget(object $params): float
	{
		$budget = (int) ($params->time_budget ?? self::DEFAULT_BUDGET);

		return (float) min(self::MAX_BUDGET, max(self::MIN_BUDGET, $budget));
	}
}
