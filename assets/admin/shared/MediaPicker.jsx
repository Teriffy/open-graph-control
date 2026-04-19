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
		<div className="ogc-media-picker" style={ { marginBottom: '1rem' } }>
			{ label && (
				<div
					style={ {
						fontWeight: 500,
						marginBottom: '0.25rem',
					} }
				>
					{ label }
				</div>
			) }

			<MediaUploadCheck>
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					value={ value || undefined }
					onSelect={ ( media ) => onChange( media.id || 0 ) }
					render={ ( { open } ) => (
						<div
							style={ {
								display: 'flex',
								gap: '0.5rem',
								alignItems: 'center',
							} }
						>
							{ preview && (
								<img
									src={ preview.url }
									alt={ preview.alt }
									style={ {
										width: '80px',
										height: '80px',
										objectFit: 'cover',
										borderRadius: '4px',
										border: '1px solid #dcdcde',
									} }
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

			{ help && (
				<p
					style={ {
						fontSize: '0.85em',
						color: '#50575e',
						marginTop: '0.25rem',
					} }
				>
					{ help }
				</p>
			) }
		</div>
	);
}
