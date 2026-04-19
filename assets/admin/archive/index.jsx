import { createRoot } from '@wordpress/element';
import ArchiveEditor from './ArchiveEditor.jsx';
import './archive.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	document.querySelectorAll( '#ogc-archive-root' ).forEach( ( el ) => {
		const kind = el.getAttribute( 'data-kind' ) || 'term';
		const tax = el.getAttribute( 'data-tax' ) || undefined;
		const id = parseInt( el.getAttribute( 'data-id' ) || '0', 10 );
		if ( ! id ) {
			return;
		}
		createRoot( el ).render(
			<ArchiveEditor kind={ kind } tax={ tax } id={ id } />
		);
	} );
} );
