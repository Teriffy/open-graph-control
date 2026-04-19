<?php
/**
 * All in One SEO integration.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Integrations;

/**
 * Detects AIOSEO and disables its social meta output via
 * aioseo_disable_social_meta.
 */
final class AIOSEO extends AbstractIntegration {

	public function slug(): string {
		return 'aioseo';
	}

	public function label(): string {
		return 'All in One SEO';
	}

	public function is_active(): bool {
		return defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' );
	}

	public function apply_takeover(): void {
		add_filter( 'aioseo_disable_social_meta', '__return_true' );
	}
}
