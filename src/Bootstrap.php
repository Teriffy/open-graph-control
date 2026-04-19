<?php
/**
 * Service container wiring.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl;

use EvzenLeonenko\OpenGraphControl\Admin\Rest\ConflictController;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\PreviewController;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\SettingsController;
use EvzenLeonenko\OpenGraphControl\Images\SizeRegistry;
use EvzenLeonenko\OpenGraphControl\Integrations\AIOSEO;
use EvzenLeonenko\OpenGraphControl\Integrations\Detector;
use EvzenLeonenko\OpenGraphControl\Integrations\Jetpack;
use EvzenLeonenko\OpenGraphControl\Integrations\RankMath;
use EvzenLeonenko\OpenGraphControl\Integrations\SEOPress;
use EvzenLeonenko\OpenGraphControl\Integrations\SlimSEO;
use EvzenLeonenko\OpenGraphControl\Integrations\TSF;
use EvzenLeonenko\OpenGraphControl\Integrations\Yoast;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Platforms\Bluesky;
use EvzenLeonenko\OpenGraphControl\Platforms\Discord;
use EvzenLeonenko\OpenGraphControl\Platforms\Facebook;
use EvzenLeonenko\OpenGraphControl\Platforms\IMessage;
use EvzenLeonenko\OpenGraphControl\Platforms\LinkedIn;
use EvzenLeonenko\OpenGraphControl\Platforms\Mastodon;
use EvzenLeonenko\OpenGraphControl\Platforms\Pinterest;
use EvzenLeonenko\OpenGraphControl\Platforms\PlatformInterface;
use EvzenLeonenko\OpenGraphControl\Platforms\Registry as PlatformRegistry;
use EvzenLeonenko\OpenGraphControl\Platforms\Slack;
use EvzenLeonenko\OpenGraphControl\Platforms\Telegram;
use EvzenLeonenko\OpenGraphControl\Platforms\Threads;
use EvzenLeonenko\OpenGraphControl\Platforms\Twitter;
use EvzenLeonenko\OpenGraphControl\Platforms\WhatsApp;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;
use EvzenLeonenko\OpenGraphControl\Renderer\Head;
use EvzenLeonenko\OpenGraphControl\Renderer\TagBuilder;
use EvzenLeonenko\OpenGraphControl\Resolvers\Description;
use EvzenLeonenko\OpenGraphControl\Resolvers\Image;
use EvzenLeonenko\OpenGraphControl\Resolvers\Locale;
use EvzenLeonenko\OpenGraphControl\Resolvers\Title;
use EvzenLeonenko\OpenGraphControl\Resolvers\Type;
use EvzenLeonenko\OpenGraphControl\Resolvers\Url;

/**
 * Registers all plugin services into the container.
 *
 * Kept as a single static method so the main plugin file (and tests) can
 * wire dependencies without scattering the knowledge of service IDs.
 */
final class Bootstrap {

	public static function register( Container $container ): void {
		// Repositories.
		$container->set(
			'options.repository',
			static fn () => new OptionsRepository()
		);
		$container->set(
			'postmeta.repository',
			static fn () => new PostMetaRepository()
		);

		// Resolvers.
		$container->set(
			'resolver.title',
			static fn ( Container $c ) => new Title(
				$c->get( 'postmeta.repository' ),
				$c->get( 'options.repository' )
			)
		);
		$container->set(
			'resolver.description',
			static fn ( Container $c ) => new Description(
				$c->get( 'postmeta.repository' ),
				$c->get( 'options.repository' )
			)
		);
		$container->set(
			'resolver.image',
			static fn ( Container $c ) => new Image(
				$c->get( 'postmeta.repository' ),
				$c->get( 'options.repository' )
			)
		);
		$container->set(
			'resolver.type',
			static fn ( Container $c ) => new Type(
				$c->get( 'postmeta.repository' ),
				$c->get( 'options.repository' )
			)
		);
		$container->set(
			'resolver.url',
			static fn () => new Url()
		);
		$container->set(
			'resolver.locale',
			static fn ( Container $c ) => new Locale(
				$c->get( 'options.repository' )
			)
		);

		// Platforms.
		$platform_classes = [
			'facebook'  => Facebook::class,
			'twitter'   => Twitter::class,
			'linkedin'  => LinkedIn::class,
			'imessage'  => IMessage::class,
			'threads'   => Threads::class,
			'mastodon'  => Mastodon::class,
			'bluesky'   => Bluesky::class,
			'whatsapp'  => WhatsApp::class,
			'discord'   => Discord::class,
			'pinterest' => Pinterest::class,
			'telegram'  => Telegram::class,
			'slack'     => Slack::class,
		];
		foreach ( $platform_classes as $slug => $fqcn ) {
			$container->set(
				'platform.' . $slug,
				static fn ( Container $c ): PlatformInterface => new $fqcn(
					$c->get( 'options.repository' ),
					$c->get( 'resolver.title' ),
					$c->get( 'resolver.description' ),
					$c->get( 'resolver.image' ),
					$c->get( 'resolver.type' ),
					$c->get( 'resolver.url' ),
					$c->get( 'resolver.locale' )
				)
			);
		}

		$container->set(
			'platform.registry',
			static function ( Container $c ) use ( $platform_classes ): PlatformRegistry {
				$registry = new PlatformRegistry();
				foreach ( array_keys( $platform_classes ) as $slug ) {
					$registry->register( $c->get( 'platform.' . $slug ) );
				}
				return $registry;
			}
		);

		// Renderer.
		$container->set(
			'renderer.tag_builder',
			static function ( Container $c ): TagBuilder {
				$strict = (bool) $c->get( 'options.repository' )->get_path( 'output.strict_mode' );
				return new TagBuilder( $strict );
			}
		);
		$container->set(
			'renderer.head',
			static fn ( Container $c ) => new Head(
				$c->get( 'platform.registry' ),
				$c->get( 'renderer.tag_builder' ),
				$c->get( 'options.repository' )
			)
		);

		// Images.
		$container->set(
			'images.size_registry',
			static fn () => new SizeRegistry()
		);

		// REST controllers.
		$container->set(
			'rest.settings',
			static fn ( Container $c ) => new SettingsController( $c->get( 'options.repository' ) )
		);
		$container->set(
			'rest.preview',
			static fn ( Container $c ) => new PreviewController( $c->get( 'platform.registry' ) )
		);
		$container->set(
			'rest.conflicts',
			static fn ( Container $c ) => new ConflictController( $c->get( 'integrations.detector' ) )
		);

		// Integrations.
		$container->set(
			'integrations.detector',
			static function ( Container $c ): Detector {
				$detector = new Detector( $c->get( 'options.repository' ) );
				$detector->register( new Yoast() );
				$detector->register( new RankMath() );
				$detector->register( new AIOSEO() );
				$detector->register( new SEOPress() );
				$detector->register( new Jetpack() );
				$detector->register( new TSF() );
				$detector->register( new SlimSEO() );
				return $detector;
			}
		);
	}
}
