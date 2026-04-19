<?php
/**
 * Default values for the ogc_settings option. Placeholder until Phase 2.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Options;

/**
 * Provides the baseline shape for the plugin settings option.
 */
final class DefaultSettings {

	public const SCHEMA_VERSION = 1;

	/**
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		return [ 'version' => self::SCHEMA_VERSION ];
	}
}
