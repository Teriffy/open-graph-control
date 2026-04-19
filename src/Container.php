<?php
/**
 * DI Container.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl;

/**
 * Minimal PSR-11-inspired service container.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */
final class Container {

	/** @var array<string, callable(Container): mixed> */
	private array $factories = [];

	/** @var array<string, mixed> */
	private array $instances = [];

	/**
	 * @param callable(Container): mixed $factory
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	public function get( string $id ): mixed {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}
		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \OutOfBoundsException( sprintf( 'Service "%s" is not registered.', $id ) );
		}
		$instance               = ( $this->factories[ $id ] )( $this );
		$this->instances[ $id ] = $instance;
		return $instance;
	}
}
