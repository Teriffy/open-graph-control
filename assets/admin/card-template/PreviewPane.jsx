import { useEffect, useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api } from './api.js';

export default function PreviewPane( { template } ) {
	const [ src, setSrc ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		const t = setTimeout( async () => {
			setLoading( true );
			setError( null );
			try {
				const res = await api.preview( { template } );
				if ( res.error ) {
					setError( res.error );
					setSrc( null );
				} else {
					setSrc( res.image );
				}
			} catch ( e ) {
				setError( e.message );
			} finally {
				setLoading( false );
			}
		}, 300 );
		return () => clearTimeout( t );
	// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ JSON.stringify( template ) ] );

	return (
		<div className="ogc-card-template__preview">
			<h3>{ __( 'Live preview', 'open-graph-control' ) }</h3>
			{ loading && <Spinner /> }
			{ error && <p className="ogc-error">{ error }</p> }
			{ src && (
				<img
					src={ src }
					alt=""
					style={ { maxWidth: '600px', height: 'auto', display: 'block' } }
				/>
			) }
			<p className="description">
				{ __( 'Updates ~300ms after each change.', 'open-graph-control' ) }
			</p>
		</div>
	);
}
