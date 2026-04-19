<?php
/**
 * Jetpack integration.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Detects Jetpack with its enhanced-distribution module (Open Graph Tags)
 * and disables the Open Graph output via jetpack_enable_open_graph.
 */
final class Jetpack extends AbstractIntegration {

	public function slug(): string {
		return 'jetpack';
	}

	public function label(): string {
		return 'Jetpack';
	}

	public function is_active(): bool {
		if ( ! class_exists( 'Jetpack' ) ) {
			return false;
		}
		// If enhanced-distribution is not active, Jetpack isn't emitting OG tags.
		$callable = [ 'Jetpack', 'is_module_active' ];
		if ( ! is_callable( $callable ) ) {
			return true;
		}
		/** @var callable(string): bool $callable */
		return (bool) $callable( 'enhanced-distribution' );
	}

	public function apply_takeover(): void {
		add_filter( 'jetpack_enable_open_graph', '__return_false' );
	}
}
