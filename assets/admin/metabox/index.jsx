import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

function MetaBoxPlaceholder( { postId } ) {
	return (
		<div style={ { padding: '1rem', color: '#50575e' } }>
			<strong>
				{ __( 'Open Graph Control', 'open-graph-control' ) }
			</strong>
			<p>
				{ __(
					'Per-post override UI ships in v0.2. In the meantime, overrides can be written to the _ogc_meta post meta via the REST API or the ogc_resolve_* filters.',
					'open-graph-control'
				) }
			</p>
			<p style={ { fontSize: '0.85em', opacity: 0.7 } }>
				Post ID: { postId }
			</p>
		</div>
	);
}

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ogc-metabox-root' );
	if ( root ) {
		const postId = parseInt( root.dataset.postId, 10 ) || 0;
		createRoot( root ).render( <MetaBoxPlaceholder postId={ postId } /> );
	}
} );
