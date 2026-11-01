<?php

/**
 * @package     TranslatedLinks
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Yepr\Plugin\Task\TranslatedLinks\Content\AdminModelSaver;
use Yepr\Plugin\Task\TranslatedLinks\Content\ArticleSource;
use Yepr\Plugin\Task\TranslatedLinks\Content\CategorySource;
use Yepr\Plugin\Task\TranslatedLinks\Content\ContentSources;
use Yepr\Plugin\Task\TranslatedLinks\Content\ModuleSource;
use Yepr\Plugin\Task\TranslatedLinks\Extension\TranslatedLinks;
use Yepr\Plugin\Task\TranslatedLinks\Html\LinkExtractor;
use Yepr\Plugin\Task\TranslatedLinks\Html\LinkRewriter;
use Yepr\Plugin\Task\TranslatedLinks\Link\LinkTranslator;
use Yepr\Plugin\Task\TranslatedLinks\Run\Clock;
use Yepr\Plugin\Task\TranslatedLinks\Run\RoutineFactory;
use Yepr\Plugin\Task\TranslatedLinks\Run\SystemClock;
use Yepr\Plugin\Task\TranslatedLinks\Store\DatabaseRunStore;
use Yepr\Plugin\Task\TranslatedLinks\Store\RunStore;
use Yepr\Plugin\Task\TranslatedLinks\Translations\DatabaseTranslations;
use Yepr\Plugin\Task\TranslatedLinks\Translations\Translations;

/**
 * The composition root: the only place the plugin's services are put together.
 *
 * Joomla hands each extension a child container, so what is registered here is the
 * plugin's own and does not leak into the site's container. Every service is shared
 * and built on first use, and the plugin itself is a lazy proxy (on PHP 8.4 and
 * later): a page that runs no task builds none of it.
 */
return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->share(
			Translations::class,
			static fn (Container $container): Translations => new DatabaseTranslations($container->get(DatabaseInterface::class))
		);

		$container->share(
			RunStore::class,
			static fn (Container $container): RunStore => new DatabaseRunStore($container->get(DatabaseInterface::class))
		);

		$container->share(
			AdminModelSaver::class,
			static fn (Container $container): AdminModelSaver => new AdminModelSaver(
				Factory::getApplication(),
				$container->get(UserFactoryInterface::class)
			)
		);

		// The kinds of content whose links are repaired.
		$container->share(
			ContentSources::class,
			static function (Container $container): ContentSources {
				$db    = $container->get(DatabaseInterface::class);
				$saver = $container->get(AdminModelSaver::class);

				return new ContentSources(
					new ArticleSource($db, $saver),
					new CategorySource($db, $saver),
					new ModuleSource($db, $saver)
				);
			}
		);

		$container->share(LinkExtractor::class, static fn (): LinkExtractor => new LinkExtractor());
		$container->share(LinkRewriter::class, static fn (): LinkRewriter => new LinkRewriter());
		$container->share(Clock::class, static fn (): Clock => new SystemClock());

		$container->share(
			LinkTranslator::class,
			static fn (Container $container): LinkTranslator => new LinkTranslator($container->get(Translations::class))
		);

		$container->share(
			RoutineFactory::class,
			static fn (Container $container): RoutineFactory => new RoutineFactory(
				$container->get(ContentSources::class),
				$container->get(RunStore::class),
				$container->get(LinkExtractor::class),
				$container->get(LinkRewriter::class),
				$container->get(LinkTranslator::class),
				$container->get(Clock::class)
			)
		);

		$container->set(
			PluginInterface::class,
			$container->lazy(TranslatedLinks::class, function (Container $container) {
				$plugin = new TranslatedLinks(
					(array) PluginHelper::getPlugin('task', 'translatedlinks'),
					$container->get(RoutineFactory::class)
				);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			})
		);
	}
};
