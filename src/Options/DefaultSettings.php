<?php
/**
 * Default values for the ogc_settings option.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical shape and default values for the ogc_settings option.
 *
 * Deep-merged over the stored option at read time by Options\Repository,
 * so adding a new key here automatically backfills it for existing installs.
 */
final class DefaultSettings {

	public const SCHEMA_VERSION = 1;

	/**
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		return [
			'version'         => self::SCHEMA_VERSION,
			'site'            => self::site(),
			'platforms'       => self::platforms(),
			'post_types'      => self::post_types(),
			'non_post_pages'  => self::non_post_pages(),
			'integrations'    => self::integrations(),
			'output'          => self::output(),
			'fallback_chains' => self::fallback_chains(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function site(): array {
		return [
			'name'            => '',
			'description'     => '',
			'locale'          => '',
			'type'            => 'website',
			'master_image_id' => 0,
			'theme_color'     => '#2271b1',
		];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function platforms(): array {
		return [
			'facebook'  => [
				'enabled'   => true,
				'fb_app_id' => '',
			],
			'twitter'   => [
				'enabled' => true,
				'card'    => 'summary_large_image',
				'site'    => '',
				'creator' => '',
			],
			'linkedin'  => [
				'enabled' => true,
			],
			'imessage'  => [
				'enabled'       => true,
				'prefer_square' => false,
			],
			'threads'   => [
				'enabled' => true,
			],
			'mastodon'  => [
				'enabled'           => true,
				'fediverse_creator' => '',
			],
			'bluesky'   => [
				'enabled' => true,
			],
			'whatsapp'  => [
				'enabled'      => true,
				'max_image_kb' => 280,
			],
			'discord'   => [
				'enabled' => true,
			],
			'pinterest' => [
				'enabled'        => true,
				'rich_pins_type' => 'article',
			],
			'telegram'  => [
				'enabled' => true,
			],
			'slack'     => [
				'enabled' => true,
			],
		];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function post_types(): array {
		return [
			'post' => [
				'enabled'      => true,
				'default_type' => 'article',
			],
			'page' => [
				'enabled'      => true,
				'default_type' => 'website',
			],
		];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function non_post_pages(): array {
		return [
			'front'     => [
				'enabled' => true,
				'use'     => 'site_defaults',
			],
			'blog'      => [
				'enabled' => true,
				'use'     => 'site_defaults',
			],
			'archive'   => [
				'enabled' => true,
				'use'     => 'archive_meta',
			],
			'author'    => [
				'enabled' => true,
				'use'     => 'profile',
			],
			'search'    => [
				'enabled' => false,
				'use'     => 'site_defaults',
			],
			'not_found' => [
				'enabled' => false,
				'use'     => 'site_defaults',
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function integrations(): array {
		return [
			'detected' => [],
			'takeover' => [],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function output(): array {
		return [
			'strict_mode'     => false,
			'comment_markers' => true,
			'cache_ttl'       => 0,
		];
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	private static function fallback_chains(): array {
		return [
			'title'       => [ 'post_meta_override', 'acf_title_field', 'jet_title_field', 'archive_override', 'seo_plugin_title', 'post_title', 'site_name' ],
			'description' => [ 'post_meta_override', 'acf_description_field', 'jet_description_field', 'archive_override', 'seo_plugin_desc', 'post_excerpt', 'post_content_trim', 'site_description' ],
			'image'       => [ 'post_meta_override', 'archive_override', 'featured_image', 'first_content_image', 'first_block_image', 'site_master_image', 'auto_card' ],
		];
	}
}
