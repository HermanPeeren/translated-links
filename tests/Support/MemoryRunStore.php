<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\TranslatedLinks\Tests\Support;

use Yepr\Plugin\Task\TranslatedLinks\Store\Run;
use Yepr\Plugin\Task\TranslatedLinks\Store\RunStore;

/**
 * Runs in an array, copied in and out as the database would.
 */
final class MemoryRunStore implements RunStore
{
    /** @var array<int, Run> */
    public array $runs = [];

    public function find(int $taskId): ?Run
    {
        return isset($this->runs[$taskId]) ? clone $this->runs[$taskId] : null;
    }

    public function save(Run $run): void
    {
        $this->runs[$run->taskId] = clone $run;
    }

    public function delete(int $taskId): void
    {
        unset($this->runs[$taskId]);
    }
}
