<?php
/**
 * Plugin root class.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin entry point. Holds the service container and wires WP hooks.
 */
final class Plugin {

	public function __construct( private Container $container ) {}

	public function boot(): void {
		add_action( 'init', [ $this, 'on_init' ] );
	}

	public function on_init(): void {
		$this->container->get( 'postmeta.repository' )->register();
		$this->container->get( 'archivemeta.repository' )->register();
		$this->container->get( 'images.size_registry' )->register();
		$this->container->get( 'integrations.detector' )->run();
		$this->container->get( 'renderer.cache' )->register();
		$this->container->get( 'renderer.head' )->register();
		$this->container->get( 'rest.settings' )->register();
		$this->container->get( 'rest.preview' )->register();
		$this->container->get( 'rest.conflicts' )->register();
		$this->container->get( 'rest.meta' )->register();
		$this->container->get( 'rest.archive_meta' )->register();
		$this->container->get( 'rest.regenerate' )->register();
		$this->container->get( 'images.regenerator' )->register();

		if ( is_admin() ) {
			$this->container->get( 'admin.page' )->register();
			$this->container->get( 'admin.assets' )->register();
			$this->container->get( 'admin.meta_box' )->register();
			$this->container->get( 'admin.notices' )->register();
			$this->container->get( 'admin.term_editor' )->register();
			$this->container->get( 'admin.user_editor' )->register();
		}

		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( 'ogc', $this->container->get( 'cli.commands' ) );
			\WP_CLI::add_command( 'ogc cards', $this->container->get( 'cli.cards_command' ) );
		}

		// Field-sources resolvers + REST controller (v0.4).
		/** @var \EvzenLeonenko\OpenGraphControl\Integrations\AcfFieldResolver $acf_resolver */
		$acf_resolver = $this->container->get( 'integrations.acf_field_resolver' );
		$acf_resolver->register();
		/** @var \EvzenLeonenko\OpenGraphControl\Integrations\JetEngineFieldResolver $jet_resolver */
		$jet_resolver = $this->container->get( 'integrations.jetengine_field_resolver' );
		$jet_resolver->register();
		/** @var \EvzenLeonenko\OpenGraphControl\Admin\Rest\FieldSourcesController $field_sources_controller */
		$field_sources_controller = $this->container->get( 'rest.field_sources_controller' );
		$field_sources_controller->register();

		// OG Card generation (v0.4).
		$this->container->get( 'ogcard.rest_controller' )->register();
		/** @var \EvzenLeonenko\OpenGraphControl\OgCard\Scheduler $card_scheduler */
		$card_scheduler = $this->container->get( 'ogcard.scheduler' );
		$card_scheduler->register();
		/** @var \EvzenLeonenko\OpenGraphControl\OgCard\ResolverHook $resolver_hook */
		$resolver_hook = $this->container->get( 'ogcard.resolver_hook' );
		$resolver_hook->register();
		/** @var \EvzenLeonenko\OpenGraphControl\OgCard\BackfillCron $backfill_cron */
		$backfill_cron = $this->container->get( 'ogcard.backfill_cron' );
		add_action( \EvzenLeonenko\OpenGraphControl\OgCard\BackfillCron::HOOK, [ $backfill_cron, 'tick' ] );
		/** @var \EvzenLeonenko\OpenGraphControl\OgCard\GcCron $gc_cron */
		$gc_cron = $this->container->get( 'ogcard.gc_cron' );
		add_action( \EvzenLeonenko\OpenGraphControl\OgCard\GcCron::HOOK, [ $gc_cron, 'tick' ] );
	}

	public function container(): Container {
		return $this->container;
	}
}
