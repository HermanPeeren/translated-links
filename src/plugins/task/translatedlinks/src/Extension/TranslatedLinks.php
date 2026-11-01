<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Extension;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Yepr\Plugin\Task\TranslatedLinks\Run\RoutineFactory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Offers the task type "Translated links: repair" to the Task Scheduler.
 *
 * The work is in Repairer. This class hands the task's parameters to the injected
 * factory, runs what it gets back and turns the outcome into an exit code: OK when
 * done, WILL_RESUME when the time budget ran out with work left, which makes the
 * scheduler run the task again straight away.
 */
final class TranslatedLinks extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/**
	 * @var array<string, array<string, string>>
	 */
	protected const TASKS_MAP = [
		'translatedlinks.repair' => [
			'langConstPrefix' => 'PLG_TASK_TRANSLATEDLINKS_REPAIR',
			'form'            => 'repair',
			'method'          => 'repair',
		],
	];

	/**
	 * @var boolean
	 */
	protected $autoloadLanguage = true;

	/**
	 * @param   array<string, mixed>  $config  The plugin's row, from PluginHelper::getPlugin().
	 */
	public function __construct(array $config, private readonly RoutineFactory $routines)
	{
		parent::__construct($config);
	}

	/**
	 * @return  array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * The repair routine.
	 *
	 * @return  integer  A TaskStatus exit code.
	 */
	protected function repair(ExecuteTaskEvent $event): int
	{
		$params   = $event->getArgument('params');
		$repairer = $this->routines->repairer(\is_object($params) ? $params : (object) [], function (string $message, string $priority): void {
			$this->logTask($message, $priority);
		});

		try {
			return $repairer->run($event->getTaskId())->finished ? TaskStatus::OK : TaskStatus::WILL_RESUME;
		} catch (\RuntimeException $e) {
			$this->logTask(\sprintf(Text::_('PLG_TASK_TRANSLATEDLINKS_LOG_FAILED'), $e->getMessage()), 'error');

			return TaskStatus::KNOCKOUT;
		}
	}
}
