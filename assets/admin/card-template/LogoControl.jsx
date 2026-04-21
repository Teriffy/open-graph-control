import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';
import MediaPicker from '../shared/MediaPicker.jsx';

export default function LogoControl( { template, onChange } ) {
	return (
		<div className="ogc-card-template__logo">
			<MediaPicker
				label={ __( 'Logo (optional)', 'open-graph-control' ) }
				value={ template.logo_id || 0 }
				onChange={ ( id ) => onChange( { ...template, logo_id: id } ) }
			/>
			<CheckboxControl
				label={ __( 'Show site name in header', 'open-graph-control' ) }
				checked={ !! template.show_site_name }
				onChange={ ( v ) => onChange( { ...template, show_site_name: v } ) }
			/>
			<CheckboxControl
				label={ __( 'Show meta line (date · author)', 'open-graph-control' ) }
				checked={ !! template.show_meta_line }
				onChange={ ( v ) => onChange( { ...template, show_meta_line: v } ) }
			/>
		</div>
	);
}
