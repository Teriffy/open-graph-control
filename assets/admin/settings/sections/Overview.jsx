import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';

export default function Overview( { settings, conflicts } ) {
	if ( ! settings ) {
		return <Spinner />;
	}

	const enabledPlatforms = Object.entries( settings.platforms || {} )
		.filter( ( [ , config ] ) => config?.enabled )
		.map( ( [ slug ] ) => slug );

	const activeConflicts = ( conflicts || [] ).filter(
		( integration ) => integration.active
	);

	return (
		<div className="ogc-section-overview">
			<h2>{ __( 'Overview', 'open-graph-control' ) }</h2>

			{ activeConflicts.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: comma-separated list of detected SEO plugins. */
						__(
							'Detected competing SEO plugin(s): %s. Configure takeover in the Integrations section to avoid duplicate Open Graph tags.',
							'open-graph-control'
						),
						activeConflicts.map( ( c ) => c.label ).join( ', ' )
					) }
				</Notice>
			) }

			<Card>
				<CardHeader>
					{ __( 'Platforms', 'open-graph-control' ) }
				</CardHeader>
				<CardBody>
					{ sprintf(
						/* translators: %1$d: number enabled, %2$d: total. */
						_n(
							'%1$d of %2$d platform enabled.',
							'%1$d of %2$d platforms enabled.',
							enabledPlatforms.length,
							'open-graph-control'
						),
						enabledPlatforms.length,
						Object.keys( settings.platforms || {} ).length
					) }
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					{ __( 'Output', 'open-graph-control' ) }
				</CardHeader>
				<CardBody>
					<p>
						{ __( 'Strict mode:', 'open-graph-control' ) }{ ' ' }
						<strong>
							{ settings.output?.strict_mode
								? __( 'on', 'open-graph-control' )
								: __( 'off', 'open-graph-control' ) }
						</strong>
					</p>
					<p>
						{ __( 'Comment markers:', 'open-graph-control' ) }{ ' ' }
						<strong>
							{ settings.output?.comment_markers
								? __( 'on', 'open-graph-control' )
								: __( 'off', 'open-graph-control' ) }
						</strong>
					</p>
				</CardBody>
			</Card>
		</div>
	);
}
