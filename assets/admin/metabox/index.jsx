import { createRoot } from '@wordpress/element';
import MetaBoxApp from './MetaBoxApp.jsx';

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ogc-metabox-root' );
	if ( root ) {
		const postId = parseInt( root.dataset.postId, 10 ) || 0;
		createRoot( root ).render( <MetaBoxApp postId={ postId } /> );
	}
} );
