# Developing the translated links plugin

## Layout

`src/` is the root of the installable package, laid out as it will be on a site:

```
src/
  translatedlinks.xml                    the manifest; <files folder="plugins/task/translatedlinks">
  script.php                             install script: Joomla 6.1 / PHP 8.3 checks, enables the plugin
  plugins/task/translatedlinks/
    services/provider.php                the composition root: registers the services, builds the plugin lazily
    forms/repair.xml                     the task's parameters
    language/en-GB/                      plg_task_translatedlinks(.sys).ini
    sql/                                 the run table, and updates/mysql/<version>.sql
    src/
      Extension/TranslatedLinks.php      the task plugin: parameters in, exit code out
      Run/RoutineFactory.php             builds a Repairer from one task's parameters
      Run/Clock.php, SystemClock.php     time, for the time budget
      Repair/Repairer.php                one run of the repair
      Link/JoomlaLink.php                an index.php?... link: read, change a parameter, write back
      Link/LinkTranslator.php            a link and a language -> the link in that language, or why not
      Translations/Translations.php      menu items, languages and associations (interface)
      Translations/DatabaseTranslations.php  the same on #__menu, #__content, #__categories, #__associations
      Html/LinkExtractor.php             <a href> and link text out of HTML
      Html/LinkRewriter.php              replace one href value, nothing else
      Content/                           articles, categories and custom modules: read a page, save through the model
      Store/                             the run table, behind RunStore
tests/
  Unit/                                  PHPUnit, no Joomla, no database
  Support/                               in-memory content, runs and translations; a fake clock
build/build.php                          zips src/ into build/plg_task_translatedlinks-<version>.zip
tools/                                   install-local.php, seed-dev-site.php, phpstan-bootstrap.php
```

The namespace is `Yepr\Plugin\Task\TranslatedLinks`. `Html/`, `Content/` and `Run/Clock`
come from the link repair plugin, with the item's language added to `ContentItem`. The
design and its reasons are in [plan.md](plan.md).

## Dependency injection

`services/provider.php` is the only place that wires services together:

| Service | Built from |
|---|---|
| `Translations` | `DatabaseTranslations` on the site's `DatabaseInterface` |
| `RunStore` | `DatabaseRunStore` on the database |
| `AdminModelSaver` | the application and `UserFactoryInterface` |
| `ContentSources` | `ArticleSource`, `CategorySource`, `ModuleSource` on the database and the saver |
| `LinkTranslator` | `Translations` |
| `LinkExtractor`, `LinkRewriter`, `Clock` | themselves |
| `RoutineFactory` | all of the above |
| `PluginInterface` | `TranslatedLinks`, with the factory, through `$container->lazy()` |

What differs per task (dry run, the user, the time budget) comes from the task's
parameters, so `RoutineFactory` makes the repairer per run.

## The development site

`/joomla` holds Joomla 6.2.0, installed and git-ignored; database
`jlatest-translated-links` (user `root`, no password), table prefix `jtl_`. To set it up:

```bash
curl -sSL -o joomla.tar.gz https://github.com/joomla/joomla-cms/releases/download/6.2.0/Joomla_6.2.0-Stable-Full_Package.tar.gz
mkdir -p joomla && tar -xzf joomla.tar.gz -C joomla && rm joomla.tar.gz
php joomla/installation/joomla.php install -n --site-name="Translated links dev" --admin-user=Admin --admin-username=yourusername --admin-password=yourpassword --admin-email=admin@example.invalid --db-type=mysqli --db-host=localhost --db-user=root --db-pass= --db-name=jlatest-translated-links --db-prefix=jtl_
rm -rf joomla/installation
composer install-local
php tools/seed-dev-site.php
```

`tools/seed-dev-site.php` writes straight into the database (no web server needed):
Dutch as a content language, the language filter with associations, an English overview
linking to three English menu items, its Dutch translation with the same links, Dutch
translations of two of the three pages with their own menu items (one associated with
its source, one not), and a Dutch category whose description links to an English menu
item.

Create the task (in *System > Scheduled Tasks*, or with a row in `jtl_scheduler_tasks`
of type `translatedlinks.repair`), and run it from the command line:

```bash
php joomla/cli/joomla.php scheduler:list
php joomla/cli/joomla.php scheduler:run --id=<id>
```

The task log is `joomla/administrator/logs/joomla_scheduler.php`. On the seeded site the
dry run would repoint 3 links in 2 items and leave 1 (to "Users", not translated yet).

## Commands

| Command | What it does |
|---|---|
| `composer test` | PHPUnit |
| `composer analyse` | PHPStan, level 8, against the Joomla in `/joomla` |
| `composer cs` | phpcs: PSR-12, with Joomla's tabs in `src/` |
| `composer cs-fix` | php-cs-fixer on tests, build and tools |
| `composer build` | the package zip in `build/` |
| `composer install-local` | build, then install into `/joomla` through Joomla's CLI |

## Known limits

- **Who made a version.** As in the link repair plugin: the version history records the
  logged-in user as the editor, and a scheduled task has none, so the editor is empty.
  *Modified by* and the version note identify the change.
- **Links without a menu item of their own** (an article shown through a category blog)
  are only repaired as far as `id`, `catid` and the blog's Itemid go; see "Later" in
  [plan.md](plan.md).
- Typed URLs (`/user-guide/...`) are not looked at: turn them into menu item links with
  the link repair plugin first.

## Releasing

Bump `<version>` in `src/translatedlinks.xml`, add `sql/updates/mysql/<version>.sql` (a
comment is enough when the schema did not change), commit, and push a tag `v<version>`.
The release workflow checks that tag and manifest agree, runs the checks, builds the
package and attaches it to a GitHub release.
