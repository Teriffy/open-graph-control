import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';

const root = window.OGC_BOOT?.apiUrl + 'og-card/';
const headers = {
	'Content-Type': 'application/json',
	'X-WP-Nonce': window.OGC_BOOT?.nonce,
};

/**
 * Card status badge — for post, archive term, or author contexts.
 *
 * Props:
 *  - kind:   'post' | 'archive' | 'author'
 *  - postId: number  (kind === 'post')
 *  - tax:    string  (kind === 'archive')
 *  - termId: number  (kind === 'archive')
 *  - userId: number  (kind === 'author')
 */
export default function CardStatusBadge( { kind, postId, tax, termId, userId } ) {
	const [ status, setStatus ] = useState( null );
	const [ regenerating, setRegenerating ] = useState( false );

	const queryString = ( () => {
		const params = new URLSearchParams();
		if ( kind === 'post' && postId ) {
			params.set( 'post_id', String( postId ) );
		}
		if ( kind === 'archive' && tax && termId ) {
			params.set( 'tax', tax );
			params.set( 'term_id', String( termId ) );
		}
		if ( kind === 'author' && userId ) {
			params.set( 'user_id', String( userId ) );
		}
		return params.toString();
	} )();

	const fetchStatus = async () => {
		const res = await fetch( `${ root }status?${ queryString }`, { headers } );
		setStatus( await res.json() );
	};

	useEffect( () => {
		if ( queryString ) {
			fetchStatus();
		}
	}, [ queryString ] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( ! queryString ) {
		return null;
	}
	if ( ! status ) {
		return <Spinner />;
	}

	const regenerate = async () => {
		if ( kind !== 'post' ) {
			return;
		}
		setRegenerating( true );
		try {
			await fetch( `${ root }regenerate`, {
				method: 'POST',
				headers,
				body: JSON.stringify( { scope: 'post', id: postId } ),
			} );
			await fetchStatus();
		} finally {
			setRegenerating( false );
		}
	};

	return (
		<div className="ogc-card-status">
			{ status.exists ? (
				<span>
					{ '✅ ' }
					{ __( 'Auto-card generated', 'open-graph-control' ) }
					{ kind === 'post' && (
						<>
							{ ' · ' }
							<Button
								variant="link"
								onClick={ regenerate }
								disabled={ regenerating }
							>
								{ regenerating
									? __( 'Regenerating…', 'open-graph-control' )
									: __( 'Regenerate', 'open-graph-control' ) }
							</Button>
						</>
					) }
				</span>
			) : (
				<span>
					{ '⏳ ' }
					{ __( 'Auto-card will be generated on next save', 'open-graph-control' ) }
				</span>
			) }
		</div>
	);
}
