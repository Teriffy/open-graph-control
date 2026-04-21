<?php
/**
 * REST controller for OG Card management.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

use EvzenLeonenko\OpenGraphControl\OgCard\BackfillCron;
use EvzenLeonenko\OpenGraphControl\OgCard\CardGenerator;
use EvzenLeonenko\OpenGraphControl\OgCard\CardKey;
use EvzenLeonenko\OpenGraphControl\OgCard\CardStore;
use EvzenLeonenko\OpenGraphControl\OgCard\Payload;
use EvzenLeonenko\OpenGraphControl\OgCard\RendererPicker;
use EvzenLeonenko\OpenGraphControl\OgCard\Template;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes five REST endpoints for OG card administration:
 *
 * - GET  /og-card/template   → read current template config
 * - PUT  /og-card/template   → update template config (validates hex, bg_type)
 * - POST /og-card/preview    → returns base64 PNG data URI for live preview
 * - POST /og-card/regenerate → schedules regeneration (all posts or a single post)
 * - GET  /og-card/status     → returns generation counts or per-key status
 *
 * All endpoints require `manage_options` capability (WP REST nonce enforced).
 */
final class CardController {

	/**
	 * REST namespace shared by all plugin endpoints.
	 *
	 * @var string
	 */
	private const NS = 'open-graph-control/v1';

	/**
	 * Creates a new CardController instance.
	 *
	 * @param CardStore      $store     Store for card filesystem operations.
	 * @param CardGenerator  $generator Generator for rendering and caching cards.
	 * @param RendererPicker $picker    Picker for selecting the appropriate renderer.
	 */
	public function __construct(
		private readonly CardStore $store,
		private readonly CardGenerator $generator,
		private readonly RendererPicker $picker,
	) {}

	/**
	 * Hooks route registration onto `rest_api_init`.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers all five OG-card REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$auth = static fn(): bool => current_user_can( 'manage_options' );

		register_rest_route(
			self::NS,
			'/og-card/template',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_template' ],
					'permission_callback' => $auth,
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'put_template' ],
					'permission_callback' => $auth,
				],
			]
		);
		register_rest_route(
			self::NS,
			'/og-card/preview',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'post_preview' ],
				'permission_callback' => $auth,
			]
		);
		register_rest_route(
			self::NS,
			'/og-card/regenerate',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'post_regenerate' ],
				'permission_callback' => $auth,
			]
		);
		register_rest_route(
			self::NS,
			'/og-card/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => $auth,
			]
		);
	}

	/**
	 * GET /og-card/template — returns the current template configuration.
	 *
	 * @param WP_REST_Request $req Incoming REST request.
	 *
	 * @return WP_REST_Response 200 with template array + `enabled` flag.
	 */
	public function get_template( WP_REST_Request $req ): WP_REST_Response {
		unset( $req );
		$opts = (array) get_option( 'ogc_card_template', [] );
		$tpl  = Template::from_array( $opts );
		return new WP_REST_Response( $tpl->to_array() + [ 'enabled' => ! empty( $opts['enabled'] ) ], 200 );
	}

	/**
	 * PUT /og-card/template — validates and persists updated template config.
	 *
	 * @param WP_REST_Request $req Incoming REST request (JSON body).
	 *
	 * @return WP_REST_Response 200 with stored config, or 400 on validation failure.
	 */
	public function put_template( WP_REST_Request $req ): WP_REST_Response {
		$data = (array) $req->get_json_params();
		try {
			$template = Template::from_array( $data );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_REST_Response( [ 'error' => $e->getMessage() ], 400 );
		}
		$stored = $template->to_array() + [ 'enabled' => ! empty( $data['enabled'] ) ];
		update_option( 'ogc_card_template', $stored );
		return new WP_REST_Response( $stored, 200 );
	}

	/**
	 * POST /og-card/preview — renders a one-off card and returns it as base64 PNG.
	 *
	 * @param WP_REST_Request $req Incoming REST request (JSON body with optional `template`, `title`, etc.).
	 *
	 * @return WP_REST_Response 200 with `image` (data URI) and `bytes`, 400 on invalid template, 500 on render failure.
	 */
	public function post_preview( WP_REST_Request $req ): WP_REST_Response {
		$data = (array) $req->get_json_params();
		try {
			$template = Template::from_array( isset( $data['template'] ) ? (array) $data['template'] : [] );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_REST_Response( [ 'error' => $e->getMessage() ], 400 );
		}
		$payload = new Payload(
			title:       (string) ( $data['title'] ?? 'Sample title for preview' ),
			description: (string) ( $data['description'] ?? 'Sample description text' ),
			site_name:   (string) get_bloginfo( 'name' ),
			url:         (string) get_site_url(),
			meta_line:   (string) ( $data['meta_line'] ?? wp_date( 'F Y' ) ),
		);
		try {
			$bytes = $this->picker->pick()->render( $template, $payload );
		} catch ( \Throwable $e ) {
			return new WP_REST_Response( [ 'error' => $e->getMessage() ], 500 );
		}
		return new WP_REST_Response(
			[
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Justified: PNG bytes to data URI for live preview; no obfuscation intent.
				'image' => 'data:image/png;base64,' . base64_encode( $bytes ),
				'bytes' => strlen( $bytes ),
			],
			200
		);
	}

	/**
	 * POST /og-card/regenerate — purges card cache and schedules backfill, or regenerates one post.
	 *
	 * Accepts JSON params:
	 *   - `scope` (string): `'all'` purges everything and queues cron; `'post'` regenerates a single post.
	 *   - `id`    (int):    Required when scope is `'post'`.
	 *
	 * @param WP_REST_Request $req Incoming REST request.
	 *
	 * @return WP_REST_Response 202 (all), 200 (post), or 400 on invalid input.
	 */
	public function post_regenerate( WP_REST_Request $req ): WP_REST_Response {
		$scope = (string) $req->get_param( 'scope' );
		if ( 'all' === $scope ) {
			$this->store->purge_all();
			wp_schedule_single_event( time() + 1, BackfillCron::HOOK );
			return new WP_REST_Response( [ 'status' => 'queued' ], 202 );
		}
		if ( 'post' === $scope ) {
			$id = (int) $req->get_param( 'id' );
			if ( $id <= 0 ) {
				return new WP_REST_Response( [ 'error' => 'invalid id' ], 400 );
			}
			$this->store->delete_for_key( CardKey::for_post( $id ) );
			$path = $this->generator->ensure( CardKey::for_post( $id ) );
			return new WP_REST_Response( [ 'path' => $path ], 200 );
		}
		return new WP_REST_Response( [ 'error' => 'invalid scope' ], 400 );
	}

	/**
	 * GET /og-card/status — returns global generation counts or a per-key existence check.
	 *
	 * Query params (all optional; if any are provided, a per-key check is performed):
	 *   - `post_id` (int)
	 *   - `tax`     (string) + `term_id` (int) — together identify an archive
	 *   - `user_id` (int)
	 *
	 * @param WP_REST_Request $req Incoming REST request.
	 *
	 * @return WP_REST_Response 200 with counts (global) or `exists`/`url` (per-key).
	 */
	public function get_status( WP_REST_Request $req ): WP_REST_Response {
		$template = Template::from_array( (array) get_option( 'ogc_card_template', [] ) );

		$post_id = (int) $req->get_param( 'post_id' );
		$tax     = (string) $req->get_param( 'tax' );
		$term_id = (int) $req->get_param( 'term_id' );
		$user_id = (int) $req->get_param( 'user_id' );

		if ( $post_id > 0 || ( $term_id > 0 && '' !== $tax ) || $user_id > 0 ) {
			$key = match ( true ) {
				$post_id > 0                => CardKey::for_post( $post_id ),
				$term_id > 0 && '' !== $tax => CardKey::for_archive( $tax, $term_id ),
				default                     => CardKey::for_author( $user_id ),
			};
			return new WP_REST_Response(
				[
					'exists' => $this->store->exists( $key, $template, 'landscape' ),
					'url'    => $this->store->url( $key, $template, 'landscape' ),
				],
				200
			);
		}

		// Global counts.
		$counts  = function_exists( 'wp_count_posts' ) ? wp_count_posts() : null;
		$total   = $counts && isset( $counts->publish ) ? (int) $counts->publish : 0;
		$missing = count( $this->store->missing_post_ids( $template, 1000 ) );
		return new WP_REST_Response(
			[
				'total'     => $total,
				'missing'   => $missing,
				'generated' => max( 0, $total - $missing ),
			],
			200
		);
	}
}
