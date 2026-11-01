# Translated Links

A Joomla 6.1+ task plugin that points the links in translated articles, category
descriptions and custom modules at the pages in their own language.

A translation keeps the links of its source. In the User Guide every internal link goes
through the menu system, `<a href="index.php?Itemid=417">`, and a translation made with
Translator Feedback still has `Itemid=417`: the English menu item, so a reader of the
Dutch overview lands on English pages. This plugin repoints each such link to the
equivalent menu item in the translation's language:
`<a href="index.php?Itemid=517&amp;lang=nl-NL">`.

It repairs what went wrong in translations made so far. It is not part of Translator
Feedback; see [docs/plan.md](docs/plan.md) for what that can take from it.

## What it does

The plugin adds one task type to **System > Scheduled Tasks**, **Translated links:
repair**. It goes over every article, category description (of any component) and custom
module that is in a language of its own (not "All"), and for each link to a page of
the site in Joomla's own form (`index.php?Itemid=...`, `index.php?option=com_content&...`):

- **The menu item** (`Itemid`), when it is in another language, is replaced by its
  equivalent in the item's language:
  1. the menu item associated with it in that language (Multilingual Associations);
  2. for an alias menu item, the equivalent of the menu item it stands for;
  3. failing those, a menu item in that language that opens the *translation* of the
     article or category the menu item opens. So a translated menu item that nobody
     associated with its source is found too.
- **An article or category** in a `com_content` link (`id`, `catid`) is replaced by its
  translation.
- **`lang=`** is set to the item's language, or added, as the editor's *CMS Content >
  Menu* button does for a menu item in one language. An `#anchor` stays.

Links that are right already, links to menu items for all languages that open a page
for all languages, and links elsewhere are left alone.

A link whose equivalent does not exist yet (the Dutch menu item has not been made) is
left as it is and listed as a warning in the task log. **Run the task again** once those
menu items are made: a link that was repaired already points at its own language and is
not touched again, so running it again only does what is left.

Each change is saved through the component's own model, as its editor saves it, so it
has a version in the item's history with the note "Translated links: N link(s) now point
to nl-NL pages". An item someone has open in the editor is skipped, with a warning.

The task works in short runs (20 seconds by default) and continues where it stopped, so
a site with thousands of articles never hits a time limit.

## Using it

1. Install the package. Make the menu items of the translations first, as far as they
   exist; what is not there yet is reported, and can be done in a later run.
2. Create a task **Translated links: repair**. Choose a user under *Record changes as*.
   Keep **Dry run** on and run it. The task log lists every link it would change, and
   every link it cannot repair yet, with the menu item that has no equivalent.
3. Switch dry run off and run it again. Clear the site cache.
4. Run it again whenever more translated menu items are made.

## Requirements

Joomla 6.1 or later (the plugin is loaded lazily, which Joomla 6.0 cannot do), PHP 8.3,
MySQL or MariaDB. Translator Feedback is not needed; the plugin reads Joomla's own
associations.

## Development

See [docs/development.md](docs/development.md); the design is in
[docs/plan.md](docs/plan.md).

## Licence

GNU General Public License version 3 or later; see [LICENSE](LICENSE).
