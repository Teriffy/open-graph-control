import { createRoot } from '@wordpress/element';
import FieldSourcesTab from './FieldSourcesTab.jsx';

document.addEventListener( 'DOMContentLoaded', () => {
	const el = document.getElementById( 'ogc-field-sources-root' );
	if ( el ) {
		createRoot( el ).render( <FieldSourcesTab /> );
	}
} );
