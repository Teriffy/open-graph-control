import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

import { api } from '../shared/api.js';
import MediaPicker from '../shared/MediaPicker.jsx';

export default function ArchiveEditor( { kind, tax, id } ) {
	const [ meta, setMeta ] = useState( null );
	const [ state, setState ] = useState( { kind: 'idle' } );

	useEffect( () => {
		const fetch =
			kind === 'user'
				? api.archive.getUser( id )
				: api.archive.getTerm( tax, id );
		fetch
			.then( setMeta )
			.catch( ( err ) =>
				setState( { kind: 'error', message: err.message } )
			);
	}, [ kind, tax, id ] );

	if ( ! meta ) {
		return <Spinner />;
	}

	const excluded = ( meta.exclude || [] ).includes( 'all' );
	const patch = ( changes ) =>
		setMeta( ( prev ) => ( { ...prev, ...changes } ) );

	const save = async () => {
		setState( { kind: 'saving' } );
		try {
			const next =
				kind === 'user'
					? await api.archive.saveUser( id, meta )
					: await api.archive.saveTerm( tax, id, meta );
			setMeta( next );
			setState( { kind: 'saved' } );
		} catch ( err ) {
			setState( { kind: 'error', message: err.message } );
		}
	};

	return (
		<div className="ogc-archive-editor">
			<TextControl
				label={ __( 'OG title', 'open-graph-control' ) }
				value={ meta.title || '' }
				onChange={ ( v ) => patch( { title: v } ) }
			/>
			<TextareaControl
				label={ __( 'OG description', 'open-graph-control' ) }
				value={ meta.description || '' }
				onChange={ ( v ) => patch( { description: v } ) }
			/>
			<MediaPicker
				label={ __( 'OG image', 'open-graph-control' ) }
				value={ meta.image_id || 0 }
				onChange={ ( imageId ) => patch( { image_id: imageId } ) }
			/>
			<CheckboxControl
				label={ __(
					'Suppress OG tags for this archive',
					'open-graph-control'
				) }
				checked={ excluded }
				onChange={ ( enabled ) =>
					patch( {
						exclude: enabled
							? [
									...( meta.exclude || [] ).filter(
										( x ) => x !== 'all'
									),
									'all',
							  ]
							: ( meta.exclude || [] ).filter(
									( x ) => x !== 'all'
							  ),
					} )
				}
			/>

			<div className="ogc-section-footer">
				<Button
					variant="primary"
					onClick={ save }
					disabled={ state.kind === 'saving' }
					aria-busy={ state.kind === 'saving' }
					aria-label={ __( 'Save overrides', 'open-graph-control' ) }
				>
					{ state.kind === 'saving'
						? __( 'Saving…', 'open-graph-control' )
						: __( 'Save overrides', 'open-graph-control' ) }
				</Button>
				<span
					role="status"
					aria-live="polite"
					aria-atomic="true"
					className="ogc-section-footer__status-region"
				>
					{ state.kind === 'saved' && (
						<span className="ogc-section-footer__status ogc-section-footer__status--saved">
							{ __( 'Saved.', 'open-graph-control' ) }
						</span>
					) }
					{ state.kind === 'error' && (
						<span className="ogc-section-footer__status ogc-section-footer__status--error">
							{ state.message }
						</span>
					) }
				</span>
			</div>
		</div>
	);
}
