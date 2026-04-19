import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

export default function Advanced( { settings, onChange } ) {
	const output = settings?.output || {};

	const update = ( key, value ) => {
		onChange( { output: { [ key ]: value } } );
	};

	return (
		<div className="ogc-section-advanced">
			<h2>{ __( 'Advanced', 'open-graph-control' ) }</h2>
			<Card>
				<CardBody>
					<ToggleControl
						label={ __( 'Strict mode', 'open-graph-control' ) }
						help={ __(
							'When off (default), Open Graph tags are emitted as both property= and name= for maximum scraper compatibility. When on, only the canonical form is emitted.',
							'open-graph-control'
						) }
						checked={ !! output.strict_mode }
						onChange={ ( v ) => update( 'strict_mode', v ) }
					/>
					<ToggleControl
						label={ __(
							'HTML comment markers',
							'open-graph-control'
						) }
						help={ __(
							'Wraps the emitted block with <!-- Open Graph Control --> comments so it\u2019s easy to spot in page source.',
							'open-graph-control'
						) }
						checked={ !! output.comment_markers }
						onChange={ ( v ) => update( 'comment_markers', v ) }
					/>
					<TextControl
						label={ __(
							'Output cache TTL (seconds)',
							'open-graph-control'
						) }
						help={ __(
							'Opt-in output cache. 0 means no cache — leave it off unless you have measured contention.',
							'open-graph-control'
						) }
						type="number"
						value={ String( output.cache_ttl ?? 0 ) }
						onChange={ ( v ) =>
							update( 'cache_ttl', parseInt( v, 10 ) || 0 )
						}
					/>
				</CardBody>
			</Card>
		</div>
	);
}
