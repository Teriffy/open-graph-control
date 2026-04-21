<?php
/**
 * Service container wiring.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Admin\Assets;
use EvzenLeonenko\OpenGraphControl\Admin\MetaBox;
use EvzenLeonenko\OpenGraphControl\Admin\Notices;
use EvzenLeonenko\OpenGraphControl\Admin\Page;
use EvzenLeonenko\OpenGraphControl\Admin\TermEditor;
use EvzenLeonenko\OpenGraphControl\Admin\UserEditor;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\ArchiveMetaController;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\CardController;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\ConflictController;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\MetaController;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\PreviewController;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\RateLimiter;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\RegenerateController;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\SettingsController;
use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository as ArchiveMetaRepository;
use EvzenLeonenko\OpenGraphControl\Cli\Commands as CliCommands;
use EvzenLeonenko\OpenGraphControl\Images\Regenerator;
use EvzenLeonenko\OpenGraphControl\Images\SizeRegistry;
use EvzenLeonenko\OpenGraphControl\Integrations\AIOSEO;
use EvzenLeonenko\OpenGraphControl\Integrations\Detector;
use EvzenLeonenko\OpenGraphControl\Integrations\Jetpack;
use EvzenLeonenko\OpenGraphControl\Integrations\RankMath;
use EvzenLeonenko\OpenGraphControl\Integrations\SEOPress;
use EvzenLeonenko\OpenGraphControl\Integrations\SlimSEO;
use EvzenLeonenko\OpenGraphControl\Integrations\TSF;
use EvzenLeonenko\OpenGraphControl\Integrations\Yoast;
use EvzenLeonenko\OpenGraphControl\OgCard\BackfillCron;
use EvzenLeonenko\OpenGraphControl\OgCard\CardGenerator;
use EvzenLeonenko\OpenGraphControl\OgCard\CardKey;
use EvzenLeonenko\OpenGraphControl\OgCard\CardStore;
use EvzenLeonenko\OpenGraphControl\OgCard\FontProvider;
use EvzenLeonenko\OpenGraphControl\OgCard\GcCron;
use EvzenLeonenko\OpenGraphControl\OgCard\PayloadBuilder;
use EvzenLeonenko\OpenGraphControl\OgCard\RendererPicker;
use EvzenLeonenko\OpenGraphControl\OgCard\ResolverHook;
use EvzenLeonenko\OpenGraphControl\OgCard\Scheduler;
use EvzenLeonenko\OpenGraphControl\OgCard\Template;
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
use EvzenLeonenko\OpenGraphControl\Validation\Validator;
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
		$container->set(
			'archivemeta.repository',
			static fn () => new ArchiveMetaRepository()
		);

		// Resolvers.
		$container->set(
			'resolver.title',
			static fn ( Container $c ) => new Title(
				$c->get( 'postmeta.repository' ),
				$c->get( 'options.repository' ),
				$c->get( 'archivemeta.repository' )
			)
		);
		$container->set(
			'resolver.description',
			static fn ( Container $c ) => new Description(
				$c->get( 'postmeta.repository' ),
				$c->get( 'options.repository' ),
				$c->get( 'archivemeta.repository' )
			)
		);
		$container->set(
			'resolver.image',
			static fn ( Container $c ) => new Image(
				$c->get( 'postmeta.repository' ),
				$c->get( 'options.repository' ),
				$c->get( 'archivemeta.repository' )
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
			'renderer.cache',
			static fn ( Container $c ) => new \EvzenLeonenko\OpenGraphControl\Renderer\Cache(
				$c->get( 'options.repository' )
			)
		);
		$container->set(
			'renderer.head',
			static fn ( Container $c ) => new Head(
				$c->get( 'platform.registry' ),
				$c->get( 'renderer.tag_builder' ),
				$c->get( 'options.repository' ),
				$c->get( 'postmeta.repository' ),
				$c->get( 'renderer.cache' ),
				$c->get( 'archivemeta.repository' )
			)
		);

		// Images.
		$container->set(
			'images.size_registry',
			static fn () => new SizeRegistry()
		);

		// Admin UI shells.
		$container->set(
			'admin.page',
			static fn () => new Page()
		);
		$container->set(
			'admin.assets',
			static fn () => new Assets()
		);
		$container->set(
			'admin.meta_box',
			static fn ( Container $c ) => new MetaBox( $c->get( 'options.repository' ) )
		);
		$container->set(
			'admin.notices',
			static fn ( Container $c ) => new Notices(
				$c->get( 'integrations.detector' ),
				$c->get( 'options.repository' )
			)
		);
		$container->set(
			'admin.term_editor',
			static fn ( Container $c ) => new TermEditor( $c->get( 'archivemeta.repository' ) )
		);
		$container->set(
			'admin.user_editor',
			static fn ( Container $c ) => new UserEditor( $c->get( 'archivemeta.repository' ) )
		);

		// REST controllers.
		$container->set(
			'rest.settings',
			static fn ( Container $c ) => new SettingsController( $c->get( 'options.repository' ) )
		);
		$container->set(
			'validation.validator',
			static fn () => new Validator()
		);
		$container->set(
			'rest.rate_limiter',
			static fn () => new RateLimiter()
		);
		$container->set(
			'rest.preview',
			static fn ( Container $c ) => new PreviewController(
				$c->get( 'platform.registry' ),
				$c->get( 'options.repository' ),
				$c->get( 'validation.validator' ),
				$c->get( 'rest.rate_limiter' )
			)
		);
		$container->set(
			'rest.conflicts',
			static fn ( Container $c ) => new ConflictController( $c->get( 'integrations.detector' ) )
		);
		$container->set(
			'rest.meta',
			static fn ( Container $c ) => new MetaController( $c->get( 'postmeta.repository' ) )
		);
		$container->set(
			'rest.archive_meta',
			static fn ( Container $c ) => new ArchiveMetaController( $c->get( 'archivemeta.repository' ) )
		);
		$container->set(
			'images.regenerator',
			static fn () => new Regenerator()
		);
		$container->set(
			'rest.regenerate',
			static fn ( Container $c ) => new RegenerateController( $c->get( 'images.regenerator' ) )
		);

		$container->set(
			'cli.commands',
			static fn ( Container $c ) => new CliCommands(
				$c->get( 'platform.registry' ),
				$c->get( 'renderer.tag_builder' ),
				$c->get( 'options.repository' ),
				$c->get( 'validation.validator' ),
				$c->get( 'images.regenerator' )
			)
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

		// OG Card generation (v0.4).
		$container->set(
			'ogcard.template_provider',
			static fn () => static fn (): Template => Template::from_array(
				(array) get_option( 'ogc_card_template', [] )
			)
		);
		$container->set(
			'ogcard.store',
			static function (): CardStore {
				$uploads = wp_upload_dir();
				return new CardStore(
					$uploads['basedir'],
					$uploads['baseurl']
				);
			}
		);
		$container->set(
			'ogcard.payload_builder',
			static fn ( Container $c ) => new PayloadBuilder(
				$c->get( 'resolver.title' ),
				$c->get( 'resolver.description' )
			)
		);
		$container->set(
			'ogcard.renderer_picker',
			static fn () => new RendererPicker( new FontProvider() )
		);
		$container->set(
			'ogcard.generator',
			static function ( Container $c ): CardGenerator {
				/** @var callable(): Template $template_fn */
				$template_fn     = $c->get( 'ogcard.template_provider' );
				$payload_builder = $c->get( 'ogcard.payload_builder' );
				$picker          = $c->get( 'ogcard.renderer_picker' );
				$payload_fn      = static function ( CardKey $key ) use ( $payload_builder ) {
					return match ( $key->kind ) {
						'post'    => $payload_builder->for_post( (int) $key->post_id() ),
						'archive' => $payload_builder->for_archive_term( (string) $key->taxonomy(), (int) $key->term_id() ),
						'author'  => $payload_builder->for_author( (int) $key->user_id() ),
						default   => throw new \UnexpectedValueException( "Unknown CardKey kind: {$key->kind}" ),
					};
				};
				return new CardGenerator(
					picker: static fn () => $picker->pick(),
					store: $c->get( 'ogcard.store' ),
					template_provider: $template_fn,
					payload_provider: $payload_fn,
				);
			}
		);
		$container->set(
			'ogcard.scheduler',
			static fn ( Container $c ) => new Scheduler(
				$c->get( 'ogcard.generator' ),
				$c->get( 'ogcard.store' )
			)
		);
		$container->set(
			'ogcard.resolver_hook',
			static fn ( Container $c ) => new ResolverHook(
				$c->get( 'ogcard.store' ),
				$c->get( 'ogcard.generator' ),
				$c->get( 'ogcard.template_provider' )
			)
		);
		$container->set(
			'ogcard.backfill_cron',
			static fn ( Container $c ) => new BackfillCron(
				$c->get( 'ogcard.generator' ),
				$c->get( 'ogcard.store' ),
				$c->get( 'ogcard.template_provider' )
			)
		);
		$container->set(
			'ogcard.gc_cron',
			static function ( Container $c ): GcCron {
				$uploads = wp_upload_dir();
				return new GcCron(
					$uploads['basedir'] . '/og-cards',
					$c->get( 'ogcard.template_provider' )
				);
			}
		);
		$container->set(
			'ogcard.rest_controller',
			static fn ( Container $c ) => new CardController(
				$c->get( 'ogcard.store' ),
				$c->get( 'ogcard.generator' ),
				$c->get( 'ogcard.renderer_picker' )
			)
		);
	}
}
