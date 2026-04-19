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
		// Services that hook themselves are resolved here in later phases.
	}

	public function container(): Container {
		return $this->container;
	}
}
