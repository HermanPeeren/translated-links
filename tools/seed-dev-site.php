<?php

/**
 * Fills the development site with a small two-language user guide, translated the way
 * Translator Feedback translated it: the Dutch articles kept the links of their English
 * sources, `index.php?Itemid=...` to English menu items.
 *
 *   php tools/seed-dev-site.php
 *
 * Straight into the database, so no web server is needed: a content language, the
 * language filter with associations, articles, a category, two menus and the
 * associations between them. The repair itself then saves through Joomla's own models.
 * Development site only. Run it once, on a fresh site.
 *
 * What the repair should then do in the Dutch overview: point the link to "Articles"
 * at "Artikelen" (associated menu items), the link to "Menus" at "Menu's" (a Dutch menu
 * item nobody associated, found through the translated article), and leave the link to
 * "Users" (not translated yet) as it is, with a warning. In the Dutch category it
 * points the link to "Articles" at "Artikelen" too.
 */

declare(strict_types=1);

$root   = \dirname(__DIR__);
$joomla = $root . '/joomla';

// The site's settings, read from configuration.php without loading Joomla.
$configuration = (string) file_get_contents($joomla . '/configuration.php');
$setting       = static function (string $name) use ($configuration): string {
    return preg_match('/public \$' . $name . " = '([^']*)'/", $configuration, $match) ? $match[1] : '';
};

$db     = new mysqli($setting('host'), $setting('user'), $setting('password'), $setting('db'));
$prefix = $setting('dbprefix');
$now    = gmdate('Y-m-d H:i:s');

/**
 * @param list<int|string> $params
 */
$value = static function (string $sql, array $params = []) use ($db): mixed {
    $result = $db->execute_query($sql, $params);

    return $result instanceof mysqli_result ? $result->fetch_column() : null;
};

if ((int) $value("SELECT COUNT(*) FROM {$prefix}languages WHERE lang_code = 'nl-NL'") > 0) {
    fwrite(STDERR, "The site has Dutch already; seed a fresh site.\n");
    exit(1);
}

// Dutch as a content language, and the language filter with associations.
$db->execute_query(
    "INSERT INTO {$prefix}languages (asset_id, lang_code, title, title_native, sef, image, description, metakey, metadesc, sitename,"
    . " published, access, ordering)"
    . " VALUES (0, 'nl-NL', 'Nederlands', 'Nederlands', 'nl', 'nl_nl', '', '', '', '', 1, 1, 2)"
);
$db->execute_query(
    "UPDATE {$prefix}extensions SET enabled = 1, params = '{\"item_associations\":\"1\",\"remove_default_prefix\":\"1\"}'"
    . " WHERE type = 'plugin' AND folder = 'system' AND element = 'languagefilter'"
);

$admin = (int) $value("SELECT user_id FROM {$prefix}user_usergroup_map WHERE group_id = 8 ORDER BY user_id LIMIT 1");
$slug  = static fn (string $title): string => trim(strtolower((string) preg_replace('/\W+/', '-', $title)), '-');

$article = static function (string $title, string $text, string $language) use ($db, $prefix, $now, $admin, $slug): int {
    $db->execute_query(
        "INSERT INTO {$prefix}content (title, alias, introtext, `fulltext`, state, catid, created, created_by, modified, modified_by,"
        . " publish_up, images, urls, attribs, metadesc, metadata, access, language)"
        . " VALUES (?, ?, ?, '', 1, 2, ?, ?, ?, ?, ?, '{}', '{}', '{}', '', '{}', 1, ?)",
        [$title, $slug($title), $text, $now, $admin, $now, $admin, $now, $language]
    );

    return (int) $db->insert_id;
};

$contentComponent = (int) $value("SELECT extension_id FROM {$prefix}extensions WHERE type = 'component' AND element = 'com_content'");

// Menu items at the root of their menu. The nested set is extended at the right of the
// tree, so the root's rgt has to grow with it.
$menuItem = static function (
    string $menu,
    string $title,
    int $articleId,
    string $language
) use (
    $db,
    $prefix,
    $value,
    $contentComponent,
    $slug
): int {
    $right = (int) $value("SELECT rgt FROM {$prefix}menu WHERE id = 1");

    $db->execute_query(
        "INSERT INTO {$prefix}menu (menutype, title, alias, path, link, type, published, parent_id, level, component_id, access, params,"
        . " lft, rgt, language, img, client_id)"
        . " VALUES (?, ?, ?, ?, ?, 'component', 1, 1, 1, ?, 1, '{}', ?, ?, ?, '', 0)",
        [
            $menu,
            $title,
            $slug($title),
            $slug($title),
            'index.php?option=com_content&view=article&id=' . $articleId,
            $contentComponent,
            $right,
            $right + 1,
            $language,
        ]
    );
    $id = (int) $db->insert_id;
    $db->execute_query("UPDATE {$prefix}menu SET rgt = ? WHERE id = 1", [$right + 2]);

    return $id;
};

$associate = static function (string $context, int ...$ids) use ($db, $prefix): void {
    $key = md5($context . implode(',', $ids));

    foreach ($ids as $id) {
        $db->execute_query("INSERT INTO {$prefix}associations (id, context, `key`) VALUES (?, ?, ?)", [$id, $context, $key]);
    }
};

$db->execute_query(
    "INSERT INTO {$prefix}menu_types (asset_id, menutype, title, description, client_id) VALUES (0, 'mainmenu-nl', 'Hoofdmenu', '', 0)"
);

// The English source.
$articles = $article('Articles', '<p>About articles.</p>', 'en-GB');
$menus    = $article('Menus', '<p>About menus.</p><h2 id="items">Menu items</h2>', 'en-GB');
$users    = $article('Users', '<p>About users.</p>', 'en-GB');

$articlesItem = $menuItem('mainmenu', 'Articles', $articles, 'en-GB');
$menusItem    = $menuItem('mainmenu', 'Menus', $menus, 'en-GB');
$usersItem    = $menuItem('mainmenu', 'Users', $users, 'en-GB');

$overviewText = '<ul>'
    . '<li><a href="index.php?Itemid=' . $articlesItem . '">%s</a></li>'
    . '<li><a href="index.php?Itemid=' . $menusItem . '&amp;lang=en-GB#items">%s</a></li>'
    . '<li><a href="index.php?Itemid=' . $usersItem . '">%s</a></li>'
    . '<li><a href="https://www.joomla.org/">joomla.org</a></li>'
    . '</ul>';

$overview     = $article('Overview', \sprintf($overviewText, 'Articles', 'Menu items', 'Users'), 'en-GB');
$overviewItem = $menuItem('mainmenu', 'Overview', $overview, 'en-GB');

// The Dutch translation: the same links, so still to the English menu items.
$artikelen = $article('Artikelen', '<p>Over artikelen.</p>', 'nl-NL');
$menusNl   = $article('Menu-s', '<p>Over menu-s.</p><h2 id="items">Menu-items</h2>', 'nl-NL');
$overzicht = $article('Overzicht', \sprintf($overviewText, 'Artikelen', 'Menu-items', 'Gebruikers'), 'nl-NL');

$artikelenItem = $menuItem('mainmenu-nl', 'Artikelen', $artikelen, 'nl-NL');
$menusNlItem   = $menuItem('mainmenu-nl', 'Menu-s', $menusNl, 'nl-NL');
$overzichtItem = $menuItem('mainmenu-nl', 'Overzicht', $overzicht, 'nl-NL');

$associate('com_content.item', $articles, $artikelen);
$associate('com_content.item', $menus, $menusNl);
$associate('com_content.item', $overview, $overzicht);

// "Menu-s" is left out on purpose: a menu item nobody associated.
$associate('com_menus.item', $articlesItem, $artikelenItem);
$associate('com_menus.item', $overviewItem, $overzichtItem);

// A Dutch category whose description has a link to an English menu item.
$right = (int) $value("SELECT rgt FROM {$prefix}categories WHERE id = 1");
$db->execute_query(
    "INSERT INTO {$prefix}categories (parent_id, lft, rgt, level, path, extension, title, alias, description, published, access,"
    . " params, metadesc, metadata,"
    . " created_user_id, created_time, modified_user_id, modified_time, language)"
    . " VALUES (1, ?, ?, 1, 'handleiding', 'com_content', 'Handleiding', 'handleiding', ?, 1, 1, '{}', '', '{}', ?, ?, ?, ?, 'nl-NL')",
    [$right, $right + 1, '<p>Begin bij <a href="index.php?Itemid=' . $articlesItem . '">artikelen</a>.</p>', $admin, $now, $admin, $now]
);
$category = (int) $db->insert_id;
$db->execute_query("UPDATE {$prefix}categories SET rgt = ? WHERE id = 1", [$right + 2]);

// The "Welcome to Joomla" tour starts by itself on a fresh site.
$db->execute_query("UPDATE {$prefix}guidedtours SET autostart = 0 WHERE autostart = 1");

printf(
    "English: articles %d, %d, %d, overview %d; menu items %d, %d, %d, overview %d.\n"
    . "Dutch: articles %d, %d, overview %d; menu items %d, %d, overview %d; category %d.\n",
    $articles,
    $menus,
    $users,
    $overview,
    $articlesItem,
    $menusItem,
    $usersItem,
    $overviewItem,
    $artikelen,
    $menusNl,
    $overzicht,
    $artikelenItem,
    $menusNlItem,
    $overzichtItem,
    $category
);
