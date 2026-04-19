<?php
/**
 * Service container wiring.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl;

use EvzenLeonenko\OpenGraphControl\Images\SizeRegistry;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Platforms\Facebook;
use EvzenLeonenko\OpenGraphControl\Platforms\Registry as PlatformRegistry;
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
		$container->set(
			'platform.facebook',
			static fn ( Container $c ) => new Facebook(
				$c->get( 'options.repository' ),
				$c->get( 'resolver.title' ),
				$c->get( 'resolver.description' ),
				$c->get( 'resolver.image' ),
				$c->get( 'resolver.type' ),
				$c->get( 'resolver.url' ),
				$c->get( 'resolver.locale' )
			)
		);

		$container->set(
			'platform.registry',
			static function ( Container $c ): PlatformRegistry {
				$registry = new PlatformRegistry();
				$registry->register( $c->get( 'platform.facebook' ) );
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
	}
}
