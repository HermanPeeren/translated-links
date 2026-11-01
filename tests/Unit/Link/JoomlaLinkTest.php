<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\TranslatedLinks\Tests\Unit\Link;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\TranslatedLinks\Link\JoomlaLink;

final class JoomlaLinkTest extends TestCase
{
    public function testReadsAMenuItemLink(): void
    {
        $link = JoomlaLink::parse('index.php?Itemid=417');

        self::assertNotNull($link);
        self::assertSame(417, $link->id('Itemid'));
        self::assertSame('index.php?Itemid=417', $link->href());
    }

    public function testKeepsPathOrderAndFragment(): void
    {
        $link = JoomlaLink::parse('/site/index.php?option=com_content&view=article&id=5:my-article&catid=2&Itemid=7#part');

        self::assertNotNull($link);
        self::assertSame(5, $link->id('id'));
        self::assertSame(
            '/site/index.php?option=com_content&view=article&id=12&catid=2&Itemid=7&lang=nl-NL#part',
            $link->with('id', '12')->with('lang', 'nl-NL')->href()
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notJoomlaLinks(): array
    {
        return [
            'typed path'   => ['user-manual/articles/articles-filter'],
            'other host'   => ['https://example.org/index.php?Itemid=3'],
            'no query'     => ['index.php'],
            'anchor'       => ['#top'],
            'mail'         => ['mailto:a@example.org'],
            'array value'  => ['index.php?option=com_tags&id[]=3'],
            'other script' => ['administrator/index2.php?Itemid=3'],
        ];
    }

    #[DataProvider('notJoomlaLinks')]
    public function testLeavesOtherLinksAlone(string $href): void
    {
        self::assertNull(JoomlaLink::parse($href));
    }
}
