<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\TranslatedLinks\Link;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A link to a page of this site in Joomla's own form: `index.php?Itemid=123&lang=en-GB`,
 * `index.php?option=com_content&view=article&id=5&catid=2&Itemid=123`, with or without
 * a leading path to index.php and an #anchor.
 *
 * Its query keeps the order it had, so a link that changes only in a value is written
 * back as it was apart from that value.
 */
final class JoomlaLink
{
	/**
	 * A path ending in index.php, without a scheme or host, and its query and fragment.
	 */
	private const PATTERN = '#^((?:/|\./)?(?:[^?\#:]*/)?index\.php)\?([^\#]*)(\#.*)?$#i';

	/**
	 * @param   string                 $path      The part before the "?", for example "index.php".
	 * @param   array<string, string>  $query     The query, in the order it had.
	 * @param   string                 $fragment  "#anchor", or ''.
	 */
	private function __construct(
		public readonly string $path,
		public readonly array $query,
		public readonly string $fragment
	) {
	}

	/**
	 * The link in an href (decoded, as LinkExtractor gives it), or null when it is not a
	 * Joomla link: a typed URL, a link elsewhere, an index.php link with array values.
	 */
	public static function parse(string $href): ?self
	{
		if (!preg_match(self::PATTERN, trim($href), $match)) {
			return null;
		}

		$query = [];

		foreach (explode('&', $match[2]) as $pair) {
			if ($pair === '') {
				continue;
			}

			[$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
			$name           = rawurldecode($name);

			if ($name === '' || str_contains($name, '[')) {
				return null;
			}

			$query[$name] = rawurldecode(str_replace('+', ' ', $value));
		}

		return $query === [] ? null : new self($match[1], $query, $match[3] ?? '');
	}

	public function get(string $name): string
	{
		return $this->query[$name] ?? '';
	}

	/**
	 * A parameter as an id: "5" and the slug "5:my-article" are both 5.
	 */
	public function id(string $name): int
	{
		return (int) $this->get($name);
	}

	/**
	 * This link with one parameter set; a new parameter goes at the end.
	 */
	public function with(string $name, string $value): self
	{
		$query        = $this->query;
		$query[$name] = $value;

		return new self($this->path, $query, $this->fragment);
	}

	/**
	 * The link as it goes into an href, before HTML encoding. Values are written as
	 * Joomla's editor writes them: unencoded, except what would break the query.
	 */
	public function href(): string
	{
		$pairs = [];

		foreach ($this->query as $name => $value) {
			$pairs[] = $name . '=' . strtr($value, ['%' => '%25', '&' => '%26', '#' => '%23', ' ' => '%20']);
		}

		return $this->path . '?' . implode('&', $pairs) . $this->fragment;
	}
}
