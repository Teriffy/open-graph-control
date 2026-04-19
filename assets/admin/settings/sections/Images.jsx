import { __, sprintf } from '@wordpress/i18n';
import { Button, Card, CardBody, Notice } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';

import { api } from '../../shared/api.js';

const SIZES = [
	{
		slug: 'ogc_landscape',
		label: 'Landscape',
		dimensions: '1200 × 630',
		used: 'Facebook, LinkedIn, Twitter (summary_large_image), iMessage wide, Threads, Discord, WhatsApp',
	},
	{
		slug: 'ogc_square',
		label: 'Square',
		dimensions: '600 × 600',
		used: 'Twitter (summary), iMessage (when prefer_square)',
	},
	{
		slug: 'ogc_pinterest',
		label: 'Pinterest',
		dimensions: '1000 × 1500',
		used: 'Pinterest (vertical Rich Pin image)',
	},
];

export default function Images() {
	const [ regen, setRegen ] = useState( { status: 'idle', processed: 0 } );
	const pollTimer = useRef( null );

	useEffect( () => {
		api.regenerateStatus()
			.then( ( r ) => setRegen( r ) )
			.catch( () => {} );
		return () => {
			if ( pollTimer.current ) {
				clearInterval( pollTimer.current );
			}
		};
	}, [] );

	useEffect( () => {
		if ( regen.status !== 'running' ) {
			if ( pollTimer.current ) {
				clearInterval( pollTimer.current );
				pollTimer.current = null;
			}
			return;
		}
		if ( ! pollTimer.current ) {
			pollTimer.current = setInterval( async () => {
				try {
					const r = await api.regenerateStatus();
					setRegen( r );
				} catch ( e ) {}
			}, 2000 );
		}
	}, [ regen.status ] );

	const start = async () => {
		setRegen( { status: 'running', processed: 0 } );
		try {
			const r = await api.regenerateStart();
			setRegen( r );
		} catch ( e ) {
			setRegen( { status: 'idle', processed: 0 } );
		}
	};

	return (
		<div className="ogc-section-images">
			<h2>{ __( 'Images', 'open-graph-control' ) }</h2>
			<p>
				{ __(
					'Three image sizes are auto-registered for attachments uploaded while this plugin is active. Existing attachments need to be regenerated once before they can be served at those sizes.',
					'open-graph-control'
				) }
			</p>

			<Card>
				<CardBody>
					<h3 style={ { marginTop: 0 } }>
						{ __(
							'Regenerate existing attachments',
							'open-graph-control'
						) }
					</h3>
					<p>
						{ __(
							'Walks the media library in batches and recomputes attachment metadata so existing images get the new OG size variants. Safe to run at any time; runs in the background via WP-Cron.',
							'open-graph-control'
						) }
					</p>
					<Button
						variant="primary"
						onClick={ start }
						disabled={ regen.status === 'running' }
					>
						{ regen.status === 'running'
							? __( 'Running…', 'open-graph-control' )
							: __( 'Start regeneration', 'open-graph-control' ) }
					</Button>
					{ regen.status === 'running' && (
						<p className="ogc-muted ogc-images__progress">
							{ sprintf(
								/* translators: %d: processed count */
								__(
									'Processed %d attachments so far…',
									'open-graph-control'
								),
								regen.processed
							) }
						</p>
					) }
					{ regen.status === 'done' && (
						<Notice status="success" isDismissible={ false }>
							{ sprintf(
								/* translators: %d: processed count */
								__(
									'Regeneration complete. %d attachments processed.',
									'open-graph-control'
								),
								regen.processed
							) }
						</Notice>
					) }
				</CardBody>
			</Card>

			{ SIZES.map( ( size ) => (
				<Card key={ size.slug }>
					<CardBody>
						<strong>{ size.label }</strong>
						<span className="ogc-meta ogc-images__dimensions">
							{ size.dimensions }
						</span>
						<code className="ogc-meta">{ size.slug }</code>
						<p className="ogc-muted ogc-images__usage">
							{ __( 'Used by:', 'open-graph-control' ) }{ ' ' }
							{ size.used }
						</p>
					</CardBody>
				</Card>
			) ) }
		</div>
	);
}
