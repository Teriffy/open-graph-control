import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	Notice,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

import { api } from '../../shared/api.js';

export default function Advanced( { settings, onChange } ) {
	const output = settings?.output || {};
	const [ resetting, setResetting ] = useState( false );
	const [ resetNotice, setResetNotice ] = useState( null );

	const update = ( key, value ) => {
		onChange( { output: { [ key ]: value } } );
	};

	const reset = async () => {
		if (
			// eslint-disable-next-line no-alert
			! window.confirm(
				__(
					'Reset ALL plugin settings to defaults? Per-post overrides are NOT affected.',
					'open-graph-control'
				)
			)
		) {
			return;
		}
		setResetting( true );
		try {
			await api.resetSettings();
			setResetNotice( {
				status: 'success',
				message: __(
					'Settings reset. Reload the page to see the defaults.',
					'open-graph-control'
				),
			} );
		} catch ( e ) {
			setResetNotice( { status: 'error', message: e.message } );
		} finally {
			setResetting( false );
		}
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

			<Card>
				<CardBody>
					<h3 style={ { marginTop: 0 } }>
						{ __( 'Reset to defaults', 'open-graph-control' ) }
					</h3>
					<p>
						{ __(
							'Deletes the stored settings row and reseeds from the plugin defaults. Per-post overrides stay untouched.',
							'open-graph-control'
						) }
					</p>
					<Button
						variant="secondary"
						isDestructive
						onClick={ reset }
						disabled={ resetting }
					>
						{ resetting
							? __( 'Resetting…', 'open-graph-control' )
							: __( 'Reset all settings', 'open-graph-control' ) }
					</Button>
					{ resetNotice && (
						<Notice
							status={ resetNotice.status }
							isDismissible={ false }
						>
							{ resetNotice.message }
						</Notice>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
