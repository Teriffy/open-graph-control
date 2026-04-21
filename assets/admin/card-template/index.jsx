import { createRoot } from '@wordpress/element';
import CardTemplateTab from './CardTemplateTab.jsx';

document.addEventListener( 'DOMContentLoaded', () => {
	const el = document.getElementById( 'ogc-card-template-root' );
	if ( el ) {
		createRoot( el ).render( <CardTemplateTab /> );
	}
} );
