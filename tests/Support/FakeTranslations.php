<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\TranslatedLinks\Tests\Support;

use Yepr\Plugin\Task\TranslatedLinks\Translations\MenuItem;
use Yepr\Plugin\Task\TranslatedLinks\Translations\Translations;

/**
 * A small multilingual site in arrays: menu items, the languages of articles and
 * categories, and association groups.
 */
final class FakeTranslations implements Translations
{
    /** @var array<int, MenuItem> */
    private array $menuItems = [];

    /** @var array<string, string> By "kind.id". */
    private array $languages = [];

    /** @var array<string, array<string, int>> By "kind.id": the group, by language. */
    private array $groups = [];

    /**
     * A menu item that opens an article.
     */
    public function articleMenuItem(int $id, string $title, string $language, int $articleId, bool $published = true): self
    {
        $this->menuItems[$id] = new MenuItem(
            $id,
            $title,
            $language,
            $published,
            'component',
            ['option' => 'com_content', 'view' => 'article', 'id' => (string) $articleId]
        );

        return $this;
    }

    public function aliasMenuItem(int $id, string $title, string $language, int $target): self
    {
        $this->menuItems[$id] = new MenuItem($id, $title, $language, true, MenuItem::ALIAS, [], $target);

        return $this;
    }

    public function item(string $kind, int $id, string $language): self
    {
        $this->languages[$kind . '.' . $id] = $language;

        return $this;
    }

    /**
     * Associates items of one kind with each other.
     *
     * @param array<string, int> $idsByLanguage
     */
    public function associate(string $kind, array $idsByLanguage): self
    {
        foreach ($idsByLanguage as $id) {
            $this->groups[$kind . '.' . $id] = $idsByLanguage;
        }

        return $this;
    }

    public function menuItem(int $id): ?MenuItem
    {
        return $this->menuItems[$id] ?? null;
    }

    public function menuItemOpening(string $opens, int $id, string $language): ?MenuItem
    {
        $best = null;

        foreach ($this->menuItems as $item) {
            if ($item->opens() !== $opens || $item->opensId() !== $id) {
                continue;
            }

            if ($item->language === $language) {
                return $item;
            }

            if ($item->forAllLanguages()) {
                $best ??= $item;
            }
        }

        return $best;
    }

    public function language(string $kind, int $id): ?string
    {
        return $this->languages[$kind . '.' . $id] ?? null;
    }

    public function translation(string $kind, int $id, string $language): ?int
    {
        return $this->groups[$kind . '.' . $id][$language] ?? null;
    }
}
