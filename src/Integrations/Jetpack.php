<?php
/**
 * Jetpack integration.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Integrations;

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
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- method_exists check removed since WP stubs type Jetpack.
		if ( is_callable( [ 'Jetpack', 'is_module_active' ] ) ) {
			/** @var callable $callable */
			$callable = [ 'Jetpack', 'is_module_active' ];
			return (bool) $callable( 'enhanced-distribution' );
		}
		return true;
	}

	public function apply_takeover(): void {
		add_filter( 'jetpack_enable_open_graph', '__return_false' );
	}
}
