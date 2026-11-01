<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\TranslatedLinks\Tests\Unit\Link;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\TranslatedLinks\Link\LinkTranslator;
use Yepr\Plugin\Task\TranslatedLinks\Tests\Support\FakeTranslations;
use Yepr\Plugin\Task\TranslatedLinks\Translations\Translations;

/**
 * An English source article 10 under menu item 417, its Dutch translation 20 under
 * menu item 517, associated with each other; and an English article 11 under menu
 * item 418 that has no translation yet.
 */
final class LinkTranslatorTest extends TestCase
{
    private FakeTranslations $site;
    private LinkTranslator $translator;

    protected function setUp(): void
    {
        $this->site = (new FakeTranslations())
            ->item(Translations::ARTICLE, 10, 'en-GB')
            ->item(Translations::ARTICLE, 20, 'nl-NL')
            ->item(Translations::ARTICLE, 11, 'en-GB')
            ->item(Translations::CATEGORY, 2, 'en-GB')
            ->item(Translations::CATEGORY, 3, 'nl-NL')
            ->associate(Translations::ARTICLE, ['en-GB' => 10, 'nl-NL' => 20])
            ->associate(Translations::CATEGORY, ['en-GB' => 2, 'nl-NL' => 3])
            ->articleMenuItem(417, 'Articles', 'en-GB', 10)
            ->articleMenuItem(517, 'Artikelen', 'nl-NL', 20)
            ->articleMenuItem(418, 'Menus', 'en-GB', 11)
            ->associate(Translations::MENU_ITEM, ['en-GB' => 417, 'nl-NL' => 517]);

        $this->translator = new LinkTranslator($this->site);
    }

    public function testPointsAMenuItemLinkAtTheAssociatedMenuItem(): void
    {
        $outcome = $this->translator->translate('index.php?Itemid=417', 'nl-NL');

        self::assertSame('index.php?Itemid=517&lang=nl-NL', $outcome->newHref);
        self::assertSame([], $outcome->problems);
    }

    public function testKeepsTheAnchorAndSetsAnExistingLang(): void
    {
        $outcome = $this->translator->translate('index.php?Itemid=417&lang=en-GB#filters', 'nl-NL');

        self::assertSame('index.php?Itemid=517&lang=nl-NL#filters', $outcome->newHref);
    }

    public function testFindsAMenuItemThatIsNotAssociatedThroughTheTranslatedArticle(): void
    {
        $this->site
            ->item(Translations::ARTICLE, 21, 'nl-NL')
            ->associate(Translations::ARTICLE, ['en-GB' => 11, 'nl-NL' => 21])
            ->articleMenuItem(518, 'Menus NL', 'nl-NL', 21);

        self::assertSame('index.php?Itemid=518&lang=nl-NL', $this->translator->translate('index.php?Itemid=418', 'nl-NL')->newHref);
    }

    public function testReportsAMenuItemWithoutEquivalentYet(): void
    {
        $outcome = $this->translator->translate('index.php?Itemid=418', 'nl-NL');

        self::assertFalse($outcome->changed());
        self::assertCount(1, $outcome->problems);
        self::assertStringContainsString('menu item 418 "Menus" (en-GB) has no equivalent in nl-NL', $outcome->problems[0]);
    }

    public function testFollowsAnAliasMenuItem(): void
    {
        $this->site->aliasMenuItem(430, 'Shortcut', 'en-GB', 417);

        self::assertSame('index.php?Itemid=517&lang=nl-NL', $this->translator->translate('index.php?Itemid=430', 'nl-NL')->newHref);
    }

    public function testLeavesLinksThatAreRightAlone(): void
    {
        self::assertFalse($this->translator->translate('index.php?Itemid=517&lang=nl-NL', 'nl-NL')->changed());
        self::assertFalse($this->translator->translate('index.php?Itemid=417', 'en-GB')->changed());
        self::assertFalse($this->translator->translate('https://www.joomla.org/', 'nl-NL')->changed());
    }

    public function testLeavesAMenuItemForAllLanguagesThatOpensOneForAllLanguages(): void
    {
        $this->site->item(Translations::ARTICLE, 1, '*')->articleMenuItem(101, 'Home', '*', 1);

        $outcome = $this->translator->translate('index.php?Itemid=101', 'nl-NL');

        self::assertFalse($outcome->changed());
        self::assertSame([], $outcome->problems);
    }

    public function testReportsAMenuItemForAllLanguagesThatOpensAnEnglishArticle(): void
    {
        $this->site->articleMenuItem(102, 'Menus everywhere', '*', 11);

        self::assertCount(1, $this->translator->translate('index.php?Itemid=102', 'nl-NL')->problems);
    }

    public function testReportsAMenuItemThatDoesNotExist(): void
    {
        self::assertStringContainsString('does not exist', $this->translator->translate('index.php?Itemid=999', 'nl-NL')->problems[0]);
    }

    public function testTranslatesTheArticleAndCategoryOfAContentLink(): void
    {
        $outcome = $this->translator->translate('index.php?option=com_content&view=article&id=10:articles&catid=2&Itemid=417', 'nl-NL');

        self::assertSame('index.php?option=com_content&view=article&id=20&catid=3&Itemid=517&lang=nl-NL', $outcome->newHref);
        self::assertCount(4, $outcome->changes);
    }

    public function testTranslatesWhatItCanAndReportsTheRest(): void
    {
        $outcome = $this->translator->translate('index.php?option=com_content&view=article&id=11&catid=2', 'nl-NL');

        self::assertSame('index.php?option=com_content&view=article&id=11&catid=3', $outcome->newHref);
        self::assertSame(['article 11 (en-GB) has no translation in nl-NL'], $outcome->problems);
    }

    public function testLinksToAnUnpublishedEquivalentAndSaysSo(): void
    {
        $this->site
            ->item(Translations::ARTICLE, 21, 'nl-NL')
            ->associate(Translations::ARTICLE, ['en-GB' => 11, 'nl-NL' => 21])
            ->articleMenuItem(518, 'Menus NL', 'nl-NL', 21, false);

        $outcome = $this->translator->translate('index.php?Itemid=418', 'nl-NL');

        self::assertSame('index.php?Itemid=518&lang=nl-NL', $outcome->newHref);
        self::assertStringContainsString('not published yet', $outcome->changes[0]);
    }
}
