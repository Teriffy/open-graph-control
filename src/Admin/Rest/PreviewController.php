<?php
/**
 * Live preview REST endpoint.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Platforms\Registry as PlatformRegistry;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Validation\Validator;
use WP_REST_Request;
use WP_REST_Response;

/**
 * POST /open-graph-control/v1/preview
 *
 * Body: { post_id?: int, context?: string }
 *
 * Returns the tag stream that wp_head would emit for the given context,
 * grouped by kind::key so the React preview components can pick the
 * values they need (twitter:title, og:title, fediverse:creator, ...).
 *
 * The preview ignores per-request overrides for now. Phase 13 will add
 * an "overrides" parameter that temporarily mutates the context via
 * filters so editors can see unsaved changes reflected in the preview.
 */
final class PreviewController extends AbstractController {

	public function __construct(
		private PlatformRegistry $registry,
		private OptionsRepository $options,
		private Validator $validator,
		private RateLimiter $rate_limiter
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_BASE,
			'/preview',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
					'context' => [
						'type'     => 'string',
						'required' => false,
						'default'  => 'singular',
					],
				],
			]
		);
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		if ( ! $this->rate_limiter->check( 'preview' ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'rate_limited',
					'message' => 'Too many preview requests. Please wait a minute.',
				],
				429
			);
		}

		$post_id = (int) $request->get_param( 'post_id' );
		$kind    = (string) $request->get_param( 'context' );

		$context = $this->context_from_kind( $kind, $post_id );

		$tags = [];
		$json = [];
		foreach ( $this->registry->collect_tags( $context ) as $tag ) {
			$tags[ $tag->kind . ':' . $tag->key ] = $tag->content;
		}
		foreach ( $this->registry->collect_json_ld( $context ) as $payload ) {
			$json[] = $payload;
		}

		$warnings = array_map(
			static fn ( $warning ) => $warning->to_array(),
			$this->validator->validate( $tags, $this->options->get() )
		);

		return new WP_REST_Response(
			[
				'tags'     => $tags,
				'json_ld'  => $json,
				'warnings' => $warnings,
			],
			200
		);
	}

	private function context_from_kind( string $kind, int $post_id ): Context {
		return match ( $kind ) {
			'front'     => Context::for_front(),
			'blog'      => Context::for_blog(),
			'archive'   => Context::for_archive( 'taxonomy' ),
			'author'    => Context::for_author( 0 ),
			'date'      => Context::for_date(),
			'search'    => Context::for_search(),
			'not_found' => Context::for_404(),
			default     => $post_id > 0 ? Context::for_post( $post_id ) : Context::for_front(),
		};
	}
}
