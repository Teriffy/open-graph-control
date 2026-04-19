import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';

import { api } from '../shared/api.js';
import Overview from './sections/Overview.jsx';
import SiteDefaults from './sections/SiteDefaults.jsx';
import Platforms from './sections/Platforms.jsx';

const SECTIONS = [
	{ key: 'overview', label: 'Overview', Component: Overview },
	{ key: 'site', label: 'Site defaults', Component: SiteDefaults },
	{ key: 'platforms', label: 'Platforms', Component: Platforms },
];

/**
 * Deep-merge two plain objects (patch wins on leaves).
 *
 * @param {*} base  Starting value or object.
 * @param {*} patch Override value or object.
 * @return {*} Merged value.
 */
function deepMerge( base, patch ) {
	if ( typeof base !== 'object' || base === null ) {
		return patch;
	}
	if ( typeof patch !== 'object' || patch === null ) {
		return patch;
	}
	const out = Array.isArray( base ) ? [ ...base ] : { ...base };
	for ( const key of Object.keys( patch ) ) {
		out[ key ] = deepMerge( base[ key ], patch[ key ] );
	}
	return out;
}

export default function App() {
	const [ activeKey, setActiveKey ] = useState( 'overview' );
	const [ settings, setSettings ] = useState( null );
	const [ conflicts, setConflicts ] = useState( null );
	const [ status, setStatus ] = useState( { kind: 'idle' } );

	useEffect( () => {
		Promise.all( [ api.getSettings(), api.conflicts() ] )
			.then( ( [ next, conf ] ) => {
				setSettings( next );
				setConflicts( conf.integrations || [] );
			} )
			.catch( ( err ) =>
				setStatus( { kind: 'error', message: err.message } )
			);
	}, [] );

	const applyPatch = ( patch ) => {
		setSettings( ( prev ) => deepMerge( prev, patch ) );
	};

	const save = async () => {
		if ( ! settings ) {
			return;
		}
		setStatus( { kind: 'saving' } );
		try {
			const next = await api.saveSettings( settings );
			setSettings( next );
			setStatus( { kind: 'saved' } );
		} catch ( err ) {
			setStatus( { kind: 'error', message: err.message } );
		}
	};

	if ( status.kind === 'error' && ! settings ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ status.message }
			</Notice>
		);
	}

	if ( ! settings ) {
		return <Spinner />;
	}

	const section =
		SECTIONS.find( ( s ) => s.key === activeKey ) || SECTIONS[ 0 ];
	const SectionComponent = section.Component;

	return (
		<div className="ogc-app">
			<h1 className="wp-heading-inline">
				{ __( 'Open Graph Control', 'open-graph-control' ) }
			</h1>

			<div
				className="ogc-layout"
				style={ { display: 'flex', gap: '1.5rem', marginTop: '1rem' } }
			>
				<nav className="ogc-nav" style={ { minWidth: '180px' } }>
					{ SECTIONS.map( ( { key, label } ) => (
						<button
							key={ key }
							type="button"
							className={ `components-button ${
								key === activeKey ? 'is-primary' : ''
							}` }
							style={ {
								display: 'block',
								width: '100%',
								marginBottom: '4px',
								textAlign: 'left',
							} }
							onClick={ () => setActiveKey( key ) }
						>
							{ label }
						</button>
					) ) }
				</nav>

				<main className="ogc-content" style={ { flex: 1 } }>
					<SectionComponent
						settings={ settings }
						conflicts={ conflicts }
						onChange={ applyPatch }
					/>

					<div style={ { marginTop: '1.5rem' } }>
						<Button
							variant="primary"
							onClick={ save }
							disabled={ status.kind === 'saving' }
						>
							{ status.kind === 'saving'
								? __( 'Saving…', 'open-graph-control' )
								: __( 'Save changes', 'open-graph-control' ) }
						</Button>
						{ status.kind === 'saved' && (
							<span
								style={ {
									marginLeft: '1rem',
									color: '#00a32a',
								} }
							>
								{ __( 'Saved.', 'open-graph-control' ) }
							</span>
						) }
						{ status.kind === 'error' && (
							<span
								style={ {
									marginLeft: '1rem',
									color: '#d63638',
								} }
							>
								{ status.message }
							</span>
						) }
					</div>
				</main>
			</div>
		</div>
	);
}
