import { __ } from '@wordpress/i18n';
import { RadioControl, ColorPicker, CheckboxControl } from '@wordpress/components';
import MediaPicker from '../shared/MediaPicker.jsx';

export default function BackgroundControls( { template, onChange } ) {
	return (
		<div className="ogc-card-template__bg">
			<RadioControl
				label={ __( 'Background', 'open-graph-control' ) }
				selected={ template.bg_type }
				options={ [
					{ label: __( 'Gradient', 'open-graph-control' ), value: 'gradient' },
					{ label: __( 'Solid color', 'open-graph-control' ), value: 'solid' },
					{ label: __( 'Image', 'open-graph-control' ), value: 'image' },
				] }
				onChange={ ( v ) => onChange( { ...template, bg_type: v } ) }
			/>
			{ template.bg_type !== 'image' && (
				<div className="ogc-card-template__color">
					<label>{ __( 'From color', 'open-graph-control' ) }</label>
					<ColorPicker
						color={ template.bg_color }
						onChange={ ( v ) => onChange( { ...template, bg_color: v } ) }
					/>
				</div>
			) }
			{ template.bg_type === 'gradient' && (
				<div className="ogc-card-template__color">
					<label>{ __( 'To color', 'open-graph-control' ) }</label>
					<ColorPicker
						color={ template.bg_gradient_to }
						onChange={ ( v ) => onChange( { ...template, bg_gradient_to: v } ) }
					/>
				</div>
			) }
			{ template.bg_type === 'image' && (
				<MediaPicker
					label={ __( 'Background image', 'open-graph-control' ) }
					value={ template.bg_image_id }
					onChange={ ( id ) => onChange( { ...template, bg_image_id: id } ) }
				/>
			) }
			<CheckboxControl
				label={ __( 'Text color (white for dark bg, black for light)', 'open-graph-control' ) }
				checked={ template.text_color === '#ffffff' }
				onChange={ ( v ) =>
					onChange( { ...template, text_color: v ? '#ffffff' : '#000000' } )
				}
			/>
		</div>
	);
}
