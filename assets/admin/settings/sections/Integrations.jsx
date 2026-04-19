import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	Notice,
	ToggleControl,
} from '@wordpress/components';

export default function Integrations( { settings, conflicts, onChange } ) {
	const takeover = settings?.integrations?.takeover || {};

	const update = ( slug, enabled ) => {
		onChange( {
			integrations: {
				takeover: { [ slug ]: enabled },
			},
		} );
	};

	const active = ( conflicts || [] ).filter( ( i ) => i.active );

	return (
		<div className="ogc-section-integrations">
			<h2>{ __( 'Integrations', 'open-graph-control' ) }</h2>
			<p>
				{ __(
					'When a competing SEO plugin is active, enable takeover to disable its Open Graph output via that plugin\u2019s own opt-out filter. This avoids duplicate tags without touching the other plugin\u2019s code.',
					'open-graph-control'
				) }
			</p>

			{ active.length === 0 && (
				<Notice status="success" isDismissible={ false }>
					{ __(
						'No competing SEO plugins detected.',
						'open-graph-control'
					) }
				</Notice>
			) }

			{ ( conflicts || [] ).map( ( integration ) => (
				<Card key={ integration.slug }>
					<CardHeader>
						<strong>{ integration.label }</strong>
						<span
							style={ {
								marginLeft: '0.5rem',
								fontSize: '0.85em',
								color: integration.active
									? '#d63638'
									: '#50575e',
							} }
						>
							{ integration.active
								? __( 'active', 'open-graph-control' )
								: __( 'not installed', 'open-graph-control' ) }
						</span>
					</CardHeader>
					<CardBody>
						<ToggleControl
							label={ __(
								'Take over Open Graph output',
								'open-graph-control'
							) }
							disabled={ ! integration.active }
							checked={ !! takeover[ integration.slug ] }
							onChange={ ( enabled ) =>
								update( integration.slug, enabled )
							}
						/>
					</CardBody>
				</Card>
			) ) }
		</div>
	);
}
