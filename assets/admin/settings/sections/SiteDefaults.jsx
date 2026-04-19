import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

import MediaPicker from '../../shared/MediaPicker.jsx';

const OG_TYPES = [
	{ label: 'website', value: 'website' },
	{ label: 'article', value: 'article' },
	{ label: 'profile', value: 'profile' },
	{ label: 'product', value: 'product' },
];

export default function SiteDefaults( { settings, onChange } ) {
	const site = settings?.site || {};

	const update = ( key, value ) => {
		onChange( { site: { [ key ]: value } } );
	};

	return (
		<div className="ogc-section-site-defaults">
			<h2>{ __( 'Site defaults', 'open-graph-control' ) }</h2>
			<Card>
				<CardBody>
					<TextControl
						label={ __( 'Site name', 'open-graph-control' ) }
						help={ __(
							'Used for og:site_name. Leave blank to fall back to the WordPress site title.',
							'open-graph-control'
						) }
						value={ site.name || '' }
						onChange={ ( v ) => update( 'name', v ) }
					/>

					<TextareaControl
						label={ __( 'Site description', 'open-graph-control' ) }
						help={ __(
							'Fallback description used on non-post pages when no more specific value exists.',
							'open-graph-control'
						) }
						value={ site.description || '' }
						onChange={ ( v ) => update( 'description', v ) }
					/>

					<TextControl
						label={ __( 'Locale', 'open-graph-control' ) }
						help={ __(
							'e.g. en_US, cs_CZ. Leave blank to use the WordPress site locale.',
							'open-graph-control'
						) }
						value={ site.locale || '' }
						onChange={ ( v ) => update( 'locale', v ) }
					/>

					<SelectControl
						label={ __( 'Default OG type', 'open-graph-control' ) }
						value={ site.type || 'website' }
						options={ OG_TYPES }
						onChange={ ( v ) => update( 'type', v ) }
					/>

					<MediaPicker
						label={ __(
							'Site master image',
							'open-graph-control'
						) }
						help={ __(
							'Used as the fallback og:image across contexts when no more specific image resolves. Ideal: 2400×1260+ so all three platform sizes can be derived.',
							'open-graph-control'
						) }
						value={ site.master_image_id || 0 }
						onChange={ ( id ) => update( 'master_image_id', id ) }
					/>

					<TextControl
						label={ __(
							'Discord theme color',
							'open-graph-control'
						) }
						help={ __(
							'HEX color used as the left bar in Discord embed cards.',
							'open-graph-control'
						) }
						type="color"
						value={ site.theme_color || '#2271b1' }
						onChange={ ( v ) => update( 'theme_color', v ) }
					/>
				</CardBody>
			</Card>
		</div>
	);
}
