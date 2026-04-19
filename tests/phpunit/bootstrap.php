<?php
/**
 * PHPUnit bootstrap file.
 *
 * Provides WP escaping polyfills so TagBuilder and similar components can be
 * unit-tested without spinning up WordPress. Brain Monkey handles filters and
 * actions; these helpers handle the direct escaping helpers used at output time.
 *
 * @package EvzenLeonenko\OpenGraphControl\Tests
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		$filtered = filter_var( (string) $url, FILTER_SANITIZE_URL );
		return false === $filtered ? '' : $filtered;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $text ) {
		return trim( (string) $text );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string, mixed> */
		private array $params = [];

		/** @var array<string, mixed>|null */
		private ?array $json_params = null;

		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		/**
		 * @param array<string, mixed>|null $body
		 */
		public function set_json_params( ?array $body ): void {
			$this->json_params = $body;
		}

		public function get_json_params() {
			return $this->json_params;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public function __construct(
			public mixed $data = null,
			public int $status = 200
		) {}

		public function get_data() {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}
	}
}
