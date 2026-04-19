<?php
/**
 * Inline Open Graph overrides editor on the term edit screen.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Admin;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository;
use WP_Term;

/**
 * Injects the React mount point on every public taxonomy's term-edit form.
 *
 * The React bundle (build/admin/archive.js) takes over once mounted and talks
 * to the REST API directly; no server-side save hook is wired here.
 */
final class TermEditor {

	/**
	 * The repository is accepted for symmetry with UserEditor / future save hooks,
	 * but rendering itself is stateless — the React bundle talks to REST directly.
	 */
	public function __construct( Repository $archive ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $archive );
	}

	public function register(): void {
		add_action( 'admin_init', [ $this, 'hook_taxonomies' ] );
	}

	public function hook_taxonomies(): void {
		/** @var array<string, string> $taxes */
		$taxes = get_taxonomies( [ 'public' => true ], 'names' );
		unset( $taxes['attachment'] );
		foreach ( $taxes as $tax ) {
			add_action( "{$tax}_edit_form_fields", [ $this, 'render' ], 20, 2 );
		}
	}

	public function render( WP_Term $term, string $taxonomy ): void {
		if ( ! current_user_can( 'edit_term', $term->term_id ) ) {
			return;
		}
		printf(
			'<tr class="form-field ogc-archive-row"><th scope="row"><label>%s</label></th>' .
			'<td><div id="ogc-archive-root" data-kind="term" data-tax="%s" data-id="%d"></div></td></tr>',
			esc_html__( 'Open Graph overrides', 'open-graph-control' ),
			esc_attr( $taxonomy ),
			(int) $term->term_id
		);
	}
}
