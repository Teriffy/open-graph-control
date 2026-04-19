<?php
/**
 * Service container wiring.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;
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
		$container->set(
			'options.repository',
			static fn () => new OptionsRepository()
		);
		$container->set(
			'postmeta.repository',
			static fn () => new PostMetaRepository()
		);

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
	}
}
