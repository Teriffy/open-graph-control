<?php
/**
 * Shared behavior for SEO plugin integrations.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Most integrations need nothing beyond the interface contract. This
 * base class exists so subclasses can add shared helpers later (e.g.
 * memoizing the is_active() result) without changing every subclass.
 */
abstract class AbstractIntegration implements IntegrationInterface {

	abstract public function slug(): string;

	abstract public function label(): string;

	abstract public function is_active(): bool;

	abstract public function apply_takeover(): void;

	public function register_value_bridge(): void {
		// Default: no bridge. Subclasses that expose SEO title/desc override this.
	}
}
