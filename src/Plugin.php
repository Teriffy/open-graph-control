<?php
/**
 * Plugin root class.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl;

/**
 * Plugin entry point. Holds the service container and wires WP hooks.
 */
final class Plugin {

	public function __construct( private Container $container ) {}

	public function boot(): void {
		add_action( 'init', [ $this, 'on_init' ] );
		add_action( 'init', [ $this, 'load_textdomain' ], 1 );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'open-graph-control',
			false,
			dirname( plugin_basename( OGC_FILE ) ) . '/languages'
		);
	}

	public function on_init(): void {
		$this->container->get( 'images.size_registry' )->register();
		$this->container->get( 'integrations.detector' )->run();
		$this->container->get( 'renderer.head' )->register();
		$this->container->get( 'rest.settings' )->register();
		$this->container->get( 'rest.preview' )->register();
		$this->container->get( 'rest.conflicts' )->register();
		$this->container->get( 'rest.meta' )->register();
		$this->container->get( 'rest.regenerate' )->register();
		$this->container->get( 'images.regenerator' )->register();

		if ( is_admin() ) {
			$this->container->get( 'admin.page' )->register();
			$this->container->get( 'admin.assets' )->register();
			$this->container->get( 'admin.meta_box' )->register();
			$this->container->get( 'admin.notices' )->register();
		}
	}

	public function container(): Container {
		return $this->container;
	}
}
