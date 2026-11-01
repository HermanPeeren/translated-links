CREATE TABLE IF NOT EXISTS `#__translatedlinks_runs` (
  `task_id` int unsigned NOT NULL,
  `kind` varchar(20) NOT NULL DEFAULT '',
  `after_id` int unsigned NOT NULL DEFAULT 0,
  `dry_run` tinyint unsigned NOT NULL DEFAULT 1,
  `items_changed` int unsigned NOT NULL DEFAULT 0,
  `links_repointed` int unsigned NOT NULL DEFAULT 0,
  `links_left` int unsigned NOT NULL DEFAULT 0,
  `items_skipped` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
