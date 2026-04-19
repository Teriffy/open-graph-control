import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { useEffect, useState } from '@wordpress/element';

/**
 * Wrapper around @wordpress/media-utils' MediaUpload that exposes a
 * simple (attachmentId, preview, clear) interface. Falls back to a
 * text input if the Media Library isn't available on the page (e.g.
 * during early mount before wp.media is ready).
 *
 * @param {Object}   props
 * @param {number}   props.value    Attachment ID (0 = unset).
 * @param {Function} props.onChange Called with new attachment ID (or 0).
 * @param {string}   [props.label]  Field label.
 * @param {string}   [props.help]   Help text.
 * @return {Element} Media picker.
 */
export default function MediaPicker( { value, onChange, label, help } ) {
	const [ preview, setPreview ] = useState( null );

	useEffect( () => {
		if ( ! value ) {
			setPreview( null );
			return;
		}
		const media = window.wp?.media;
		if ( ! media ) {
			return;
		}
		const attachment = media.attachment( value );
		attachment.fetch().then( () => {
			const url =
				attachment.get( 'sizes' )?.thumbnail?.url ||
				attachment.get( 'url' );
			setPreview( {
				url,
				alt: attachment.get( 'alt' ) || '',
			} );
		} );
	}, [ value ] );

	return (
		<div className="ogc-media-picker">
			{ label && (
				<div className="ogc-media-picker__label">{ label }</div>
			) }

			<MediaUploadCheck>
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					value={ value || undefined }
					onSelect={ ( media ) => onChange( media.id || 0 ) }
					render={ ( { open } ) => (
						<div className="ogc-media-picker__row">
							{ preview && (
								<img
									src={ preview.url }
									alt={ preview.alt }
									className="ogc-media-picker__preview"
								/>
							) }
							<Button variant="secondary" onClick={ open }>
								{ value
									? __(
											'Replace image',
											'open-graph-control'
									  )
									: __(
											'Select image',
											'open-graph-control'
									  ) }
							</Button>
							{ value > 0 && (
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => onChange( 0 ) }
								>
									{ __( 'Remove', 'open-graph-control' ) }
								</Button>
							) }
						</div>
					) }
				/>
			</MediaUploadCheck>

			{ help && <p className="ogc-media-picker__help">{ help }</p> }
		</div>
	);
}
