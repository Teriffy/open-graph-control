import { createRoot } from '@wordpress/element';
import App from './App.jsx';
import './settings.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'ogc-settings-root' );
	if ( root ) {
		createRoot( root ).render( <App /> );
	}
} );
