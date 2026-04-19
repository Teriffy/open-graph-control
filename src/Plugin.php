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
	}

	public function on_init(): void {
		$this->container->get( 'images.size_registry' )->register();
		$this->container->get( 'renderer.head' )->register();
	}

	public function container(): Container {
		return $this->container;
	}
}
