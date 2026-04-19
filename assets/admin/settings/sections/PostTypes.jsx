import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import {
	Card,
	CardBody,
	CardHeader,
	SelectControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';

import { api } from '../../shared/api.js';

const OG_TYPES = [
	{ label: 'article', value: 'article' },
	{ label: 'website', value: 'website' },
	{ label: 'profile', value: 'profile' },
	{ label: 'product', value: 'product' },
	{ label: 'book', value: 'book' },
];

export default function PostTypes( { settings, onChange } ) {
	const [ types, setTypes ] = useState( null );

	useEffect( () => {
		api.postTypes()
			.then( ( data ) => setTypes( data.post_types || [] ) )
			.catch( () => setTypes( [] ) );
	}, [] );

	if ( ! types ) {
		return <Spinner />;
	}

	const config = settings?.post_types || {};

	const update = ( slug, patch ) => {
		onChange( { post_types: { [ slug ]: patch } } );
	};

	return (
		<div className="ogc-section-post-types">
			<h2>{ __( 'Post types', 'open-graph-control' ) }</h2>
			<p>
				{ __(
					'Enable Open Graph Control on each public post type and pick the default og:type it should emit.',
					'open-graph-control'
				) }
			</p>
			{ types.map( ( type ) => {
				const entry = config[ type.slug ] || {
					enabled: false,
					default_type: 'article',
				};
				return (
					<Card key={ type.slug }>
						<CardHeader>
							<strong>{ type.label }</strong>
							<span className="ogc-meta">{ type.slug }</span>
						</CardHeader>
						<CardBody>
							<ToggleControl
								label={ __( 'Enabled', 'open-graph-control' ) }
								checked={ !! entry.enabled }
								onChange={ ( enabled ) =>
									update( type.slug, { enabled } )
								}
							/>
							{ entry.enabled && (
								<SelectControl
									label={ __(
										'Default og:type',
										'open-graph-control'
									) }
									value={ entry.default_type || 'article' }
									options={ OG_TYPES }
									onChange={ ( v ) =>
										update( type.slug, {
											default_type: v,
										} )
									}
								/>
							) }
						</CardBody>
					</Card>
				);
			} ) }
		</div>
	);
}
