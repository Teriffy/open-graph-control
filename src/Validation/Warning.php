<?php
/**
 * Validation warning value object.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Validation;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight struct describing a single validation issue surfaced to the
 * admin UI (e.g. "title exceeds 90 chars" or "image missing alt text").
 */
final class Warning {

	public const INFO  = 'info';
	public const WARN  = 'warn';
	public const ERROR = 'error';

	public function __construct(
		public readonly string $severity,
		public readonly string $field,
		public readonly string $message
	) {}

	/**
	 * @return array{severity: string, field: string, message: string}
	 */
	public function to_array(): array {
		return [
			'severity' => $this->severity,
			'field'    => $this->field,
			'message'  => $this->message,
		];
	}
}
