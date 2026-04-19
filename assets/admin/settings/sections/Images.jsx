import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

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
	return (
		<div className="ogc-section-images">
			<h2>{ __( 'Images', 'open-graph-control' ) }</h2>
			<p>
				{ __(
					'Three image sizes are auto-registered for attachments uploaded while this plugin is active. Existing attachments only get these variants after a media regeneration (Regenerate Thumbnails plugin or WP-CLI).',
					'open-graph-control'
				) }
			</p>
			{ SIZES.map( ( size ) => (
				<Card key={ size.slug }>
					<CardBody>
						<strong>{ size.label }</strong>
						<span
							style={ {
								marginLeft: '0.5rem',
								fontFamily: 'monospace',
								opacity: 0.7,
							} }
						>
							{ size.dimensions }
						</span>
						<code
							style={ {
								marginLeft: '0.5rem',
								fontSize: '0.85em',
								opacity: 0.7,
							} }
						>
							{ size.slug }
						</code>
						<p
							style={ {
								fontSize: '0.9em',
								color: '#50575e',
								marginTop: '0.5rem',
							} }
						>
							{ __( 'Used by:', 'open-graph-control' ) }{ ' ' }
							{ size.used }
						</p>
					</CardBody>
				</Card>
			) ) }
		</div>
	);
}
