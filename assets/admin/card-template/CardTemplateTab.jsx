import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner, Button, ToggleControl, Notice } from '@wordpress/components';
import { api } from './api.js';
import BackgroundControls from './BackgroundControls.jsx';
import LogoControl from './LogoControl.jsx';
import PreviewPane from './PreviewPane.jsx';

export default function CardTemplateTab() {
	const [ template, setTemplate ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		api.get().then( setTemplate );
	}, [] );

	if ( ! template ) {
		return <Spinner />;
	}

	const save = async () => {
		setSaving( true );
		try {
			const next = await api.put( template );
			setTemplate( next );
			setNotice( { type: 'success', msg: __( 'Template saved.', 'open-graph-control' ) } );
		} catch ( e ) {
			setNotice( { type: 'error', msg: __( 'Save failed.', 'open-graph-control' ) } );
		} finally {
			setSaving( false );
		}
	};

	const regenAll = async () => {
		// eslint-disable-next-line no-alert
		if ( ! confirm( __( 'Regenerate all cards?', 'open-graph-control' ) ) ) {
			return;
		}
		await api.regenAll();
		setNotice( {
			type: 'info',
			msg: __( 'Regeneration queued — background cron will process cards.', 'open-graph-control' ),
		} );
	};

	return (
		<div className="ogc-card-template">
			{ notice && (
				<Notice status={ notice.type } onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }

			<ToggleControl
				label={ __( 'Enable auto-generated OG cards (fallback only)', 'open-graph-control' ) }
				help={ __(
					'When enabled, posts without an explicit OG image will get an auto-generated card.',
					'open-graph-control'
				) }
				checked={ !! template.enabled }
				onChange={ ( v ) => setTemplate( { ...template, enabled: v } ) }
			/>

			<BackgroundControls template={ template } onChange={ setTemplate } />

			<LogoControl template={ template } onChange={ setTemplate } />

			<PreviewPane template={ template } />

			<div className="ogc-card-template__actions">
				<Button variant="primary" onClick={ save } disabled={ saving }>
					{ saving
						? __( 'Saving…', 'open-graph-control' )
						: __( 'Save changes', 'open-graph-control' ) }
				</Button>
				<Button variant="secondary" onClick={ regenAll }>
					{ __( 'Regenerate all cards', 'open-graph-control' ) }
				</Button>
			</div>
		</div>
	);
}
