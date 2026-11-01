# Plan: repoint the links in translated content

## The problem

The User Guide links to its own pages through the menu system: `index.php?Itemid=417`,
as the editor's *CMS Content > Menu* button makes them (and as the
[link repair plugin](https://github.com/HermanPeeren/link-repair) turned typed URLs into).
Joomla builds the current URL from the menu item on every page view.

Translator Feedback translates an article's text and leaves its links as they were. So
the Dutch overview links to `Itemid=417`, the English menu item, and its readers land on
English pages. The link should be to the Dutch menu item that stands for 417.

The translations are made before their menu items: the Dutch menu items do not exist
while an article is translated. So the links can only be repaired afterwards, and again
as more menu items are made.

## One task routine, run as often as needed

**Translated links: repair** goes over all content in a language of its own, and
repoints what it can. No separate scan: the dry run is the report. Running it again is
safe and cheap, because a repaired link points at its own language and is skipped. The
links it cannot repair yet are warnings in the task log, with the menu item that has no
equivalent.

## Which content

Articles (intro text and full text), category descriptions of every component and custom
modules on the site: the same `ContentSource`s as the link repair plugin, read only
where `language` is not `*`. An item for all languages has no language its links could
be wrong for.

## Finding the equivalent

`Link\LinkTranslator`, per link, for an item in language L:

1. **Only Joomla links** (`Link\JoomlaLink`): `index.php?...` without scheme or host,
   with a leading path to index.php or not, and an optional `#anchor`. Typed URLs are
   for the link repair plugin.
2. **`Itemid=N`**, where menu item N is not in L:
   1. the menu item associated with N in L (`com_menus.item`);
   2. for an alias menu item, the equivalent of the menu item it stands for (at most 3
      in a row);
   3. the article or category N opens (`com_content` article, category, categories),
      its translation in L (`com_content.item` / `com_categories.item`), and a menu
      item in L, or for all languages, that opens that translation; a published one
      before an unpublished one.

   An unpublished equivalent is linked to all the same (the log says so): the link
   works as soon as the menu item is published, and the task need not be run again.
   N for all languages is only reported when it opens an article or category in
   another language than L.
3. **`option=com_content`**: `id` and `catid` of an article link, `id` of a category
   link, replaced by their translations in L.
4. **`lang`**: set to L where the link has one, or added to a repointed link whose
   menu item is in L.

Everything is read from Joomla's own tables (`#__menu`, `#__associations`, `#__content`,
`#__categories`), not from Translator Feedback's queue: menu items and translations made
by hand count too. Menu items are read once per run; languages and association groups
once per item.

## Repairing

As in the link repair plugin: only the `href` value of the matching `<a>` tags changes,
the rest of the HTML stays byte for byte (`Html\LinkRewriter`). The item is saved through
its component's model (`Content\AdminModelSaver`), so it has a version, plugins and
Smart Search take part, and *Modified by* is the user chosen in the task. The model
leaves the item's associations alone, because the save sends none. An item that is
checked out is skipped.

## No time-outs

A time budget per run (default 20 seconds). Where the run is, the kind of content and
the last id done, plus the counts so far, is in `#__translatedlinks_runs`, one row per
task, saved after every page of 20 items and when the time is up; the task returns
`WILL_RESUME` and the scheduler runs it again straight away. The row is removed when the
run is done. A run that is continued with dry run switched the other way starts over.

## Later: in Translator Feedback

What Translator Feedback can take from this, so that new translations get the right
links without a repair:

- `LinkTranslator` with `Translations` is the part to reuse: given a link and a language,
  the link in that language, or why there is none yet. It needs nothing of the task.
- When to run it: not while translating, because the menu items do not exist yet. Two
  moments work: when a translated menu item is saved (repoint the links to its source in
  every published translation of that language), and as a scheduled task like this one
  for what is left.
- Links that do not go through a menu item: an article shown through a category blog
  has no menu item of its own, so its link is `option=com_content&view=article&id=..`
  with the blog's Itemid. `LinkTranslator` already translates `id` and `catid`; the
  Itemid then needs the equivalent of the blog's menu item, which step 2 above gives.
  Other components (contacts, tags) need their own entries in `CONTENT_IDS` and
  `Translations::KINDS`.
