<?php
/**
 * Yoast SEO integration.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Integrations;

/**
 * Detects Yoast and opts out of its Open Graph output using the
 * plugin's own wpseo_enable_open_graph filter (supported since v20.x).
 */
final class Yoast extends AbstractIntegration {

	public function slug(): string {
		return 'yoast';
	}

	public function label(): string {
		return 'Yoast SEO';
	}

	public function is_active(): bool {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
	}

	public function apply_takeover(): void {
		add_filter( 'wpseo_enable_open_graph', '__return_false' );
	}

	public function register_value_bridge(): void {
		add_filter(
			'ogc_seo_plugin_title',
			static function ( $value ) {
				if ( null !== $value && '' !== $value ) {
					return $value;
				}
				if ( function_exists( 'wpseo_replace_vars' ) && function_exists( 'YoastSEO' ) ) {
					$title = (string) YoastSEO()->meta->for_current_page()->title;
					return '' === $title ? $value : $title;
				}
				return $value;
			}
		);

		add_filter(
			'ogc_seo_plugin_desc',
			static function ( $value ) {
				if ( null !== $value && '' !== $value ) {
					return $value;
				}
				if ( function_exists( 'YoastSEO' ) ) {
					$desc = (string) YoastSEO()->meta->for_current_page()->description;
					return '' === $desc ? $value : $desc;
				}
				return $value;
			}
		);
	}
}
