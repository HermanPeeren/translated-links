<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\TranslatedLinks\Tests\Unit\Repair;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\TranslatedLinks\Content\ContentSources;
use Yepr\Plugin\Task\TranslatedLinks\Html\LinkExtractor;
use Yepr\Plugin\Task\TranslatedLinks\Html\LinkRewriter;
use Yepr\Plugin\Task\TranslatedLinks\Link\LinkTranslator;
use Yepr\Plugin\Task\TranslatedLinks\Repair\Repairer;
use Yepr\Plugin\Task\TranslatedLinks\Repair\RepairSettings;
use Yepr\Plugin\Task\TranslatedLinks\Tests\Support\FakeClock;
use Yepr\Plugin\Task\TranslatedLinks\Tests\Support\FakeTranslations;
use Yepr\Plugin\Task\TranslatedLinks\Tests\Support\MemoryRunStore;
use Yepr\Plugin\Task\TranslatedLinks\Tests\Support\MemorySource;
use Yepr\Plugin\Task\TranslatedLinks\Translations\Translations;

final class RepairerTest extends TestCase
{
    private const TASK = 7;

    private MemorySource $articles;
    private MemorySource $categories;
    private MemoryRunStore $runs;

    /** @var list<array{0: string, 1: string}> */
    private array $log = [];

    protected function setUp(): void
    {
        $this->articles   = MemorySource::articles();
        $this->categories = MemorySource::categories();
        $this->runs       = new MemoryRunStore();
        $this->log        = [];
    }

    private function repairer(bool $dryRun = false, float $budget = 20.0, float $step = 0.0): Repairer
    {
        $site = (new FakeTranslations())
            ->item(Translations::ARTICLE, 10, 'en-GB')
            ->item(Translations::ARTICLE, 20, 'nl-NL')
            ->item(Translations::ARTICLE, 11, 'en-GB')
            ->associate(Translations::ARTICLE, ['en-GB' => 10, 'nl-NL' => 20])
            ->articleMenuItem(417, 'Articles', 'en-GB', 10)
            ->articleMenuItem(517, 'Artikelen', 'nl-NL', 20)
            ->articleMenuItem(418, 'Menus', 'en-GB', 11)
            ->associate(Translations::MENU_ITEM, ['en-GB' => 417, 'nl-NL' => 517]);

        return new Repairer(
            new ContentSources($this->articles, $this->categories),
            $this->runs,
            new LinkExtractor(),
            new LinkRewriter(),
            new LinkTranslator($site),
            new FakeClock($step),
            new RepairSettings($dryRun, 42, $budget),
            function (string $message, string $priority): void {
                $this->log[] = [$priority, $message];
            }
        );
    }

    private function lastLine(): string
    {
        return $this->log === [] ? '' : $this->log[\count($this->log) - 1][1];
    }

    private function allLines(): string
    {
        return implode("\n", array_column($this->log, 1));
    }

    public function testRepointsTheLinksOfATranslationAndSavesIt(): void
    {
        $this->articles->put(
            20,
            'nl-NL',
            '<p>Zie <a href="index.php?Itemid=417">artikelen</a> en <a class="x" href="index.php?Itemid=417">nog eens</a>.</p>',
            '<p><a href="index.php?Itemid=418">menus</a></p>'
        );

        self::assertTrue($this->repairer()->run(self::TASK)->finished);

        $fields = $this->articles->get(20)->fields;
        self::assertSame(
            '<p>Zie <a href="index.php?Itemid=517&amp;lang=nl-NL">artikelen</a> en '
            . '<a class="x" href="index.php?Itemid=517&amp;lang=nl-NL">nog eens</a>.</p>',
            $fields['introtext']
        );
        self::assertSame('<p><a href="index.php?Itemid=418">menus</a></p>', $fields['fulltext']);
        self::assertSame([['id' => 20, 'userId' => 42, 'note' => 'Translated links: 1 link(s) now point to nl-NL pages']], $this->articles->saves);
        self::assertStringContainsString('1 link(s) in 1 item(s) repointed, 1 link(s) left as they are', $this->lastLine());
        self::assertSame([], $this->runs->runs);
    }

    public function testLeavesTheSourceLanguageAlone(): void
    {
        $this->articles->put(10, 'en-GB', '<a href="index.php?Itemid=417">articles</a>');

        $this->repairer()->run(self::TASK);

        self::assertSame([], $this->articles->saves);
    }

    public function testADryRunSavesNothing(): void
    {
        $this->articles->put(20, 'nl-NL', '<a href="index.php?Itemid=417">artikelen</a>');

        $this->repairer(dryRun: true)->run(self::TASK);

        self::assertSame([], $this->articles->saves);
        self::assertStringContainsString('Would change in Article 20', $this->allLines());
    }

    public function testSkipsACheckedOutItem(): void
    {
        $this->articles->put(20, 'nl-NL', '<a href="index.php?Itemid=417">artikelen</a>', '', 5);

        $this->repairer()->run(self::TASK);

        self::assertSame([], $this->articles->saves);
        self::assertStringContainsString('1 item(s) skipped', $this->lastLine());
    }

    public function testASaveThatFailsIsLoggedAndTheRunGoesOn(): void
    {
        $this->articles->put(20, 'nl-NL', '<a href="index.php?Itemid=417">a</a>');
        $this->categories->put(3, 'nl-NL', '<a href="index.php?Itemid=417">b</a>');
        $this->articles->failSaveWith = 'refused';

        self::assertTrue($this->repairer()->run(self::TASK)->finished);
        self::assertCount(1, $this->categories->saves);
        self::assertStringContainsString('could not be saved: refused', $this->allLines());
    }

    public function testContinuesWhereTheLastRunStopped(): void
    {
        foreach ([20, 21, 22] as $id) {
            $this->articles->put($id, 'nl-NL', '<a href="index.php?Itemid=417">a</a>');
        }

        // Every reading of the clock takes a second: the budget allows two items.
        $first = $this->repairer(budget: 2.5, step: 1.0)->run(self::TASK);

        self::assertFalse($first->finished);
        self::assertSame(21, $this->runs->runs[self::TASK]->afterId);

        self::assertTrue($this->repairer()->run(self::TASK)->finished);
        self::assertSame([20, 21, 22], array_column($this->articles->saves, 'id'));
        self::assertStringContainsString('3 link(s) in 3 item(s) repointed', $this->lastLine());
    }

    public function testRunningAgainChangesNothing(): void
    {
        $this->articles->put(20, 'nl-NL', '<a href="index.php?Itemid=417">a</a>');

        $this->repairer()->run(self::TASK);
        $this->repairer()->run(self::TASK);

        self::assertCount(1, $this->articles->saves);
    }
}
