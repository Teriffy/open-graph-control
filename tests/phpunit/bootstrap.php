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

// Plugin source files guard against direct access via ABSPATH. Declare it
// here so autoloaded classes don't trigger exit() during test runs.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require __DIR__ . '/../../vendor/autoload.php';

// Boot Patchwork before declaring any polyfills that Brain Monkey may need to
// redeclare per-test. Files required AFTER this point are processed by
// Patchwork's stream wrapper and can be safely redeclared via Functions\when().
require_once __DIR__ . '/../../vendor/antecedent/patchwork/Patchwork.php';
require __DIR__ . '/wp-polyfills.php';

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

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Minimal WP_CLI shim for unit tests.
	 *
	 * Records calls so tests can assert on them.
	 */
	class WP_CLI {
		/** @var array<int, array{method: string, message: string}> */
		public static array $calls = [];

		public static function reset(): void {
			self::$calls = [];
		}

		public static function success( string $message ): void {
			self::$calls[] = [ 'method' => 'success', 'message' => $message ];
		}

		public static function error( string $message, bool $exit = true ): void {
			self::$calls[] = [ 'method' => 'error', 'message' => $message ];
			if ( $exit ) {
				throw new \RuntimeException( "WP_CLI::error: {$message}" );
			}
		}

		public static function warning( string $message ): void {
			self::$calls[] = [ 'method' => 'warning', 'message' => $message ];
		}

		public static function line( string $message = '' ): void {
			self::$calls[] = [ 'method' => 'line', 'message' => $message ];
		}
	}
}
