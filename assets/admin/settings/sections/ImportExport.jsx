import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	Notice,
	TextareaControl,
} from '@wordpress/components';

import { api } from '../../shared/api.js';

export default function ImportExport( { settings } ) {
	const [ importText, setImportText ] = useState( '' );
	const [ state, setState ] = useState( { kind: 'idle' } );

	const exportJson = JSON.stringify( settings, null, 2 );

	const download = () => {
		const blob = new Blob( [ exportJson ], { type: 'application/json' } );
		const url = URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = `open-graph-control-settings-${
			new Date().toISOString().split( 'T' )[ 0 ]
		}.json`;
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
		URL.revokeObjectURL( url );
	};

	const runImport = async () => {
		setState( { kind: 'working' } );
		try {
			const parsed = JSON.parse( importText );
			if ( ! parsed || typeof parsed !== 'object' ) {
				throw new Error( 'JSON root must be an object.' );
			}
			await api.saveSettings( parsed );
			setState( {
				kind: 'ok',
				message: __(
					'Settings imported. Reload the page to see the applied values.',
					'open-graph-control'
				),
			} );
		} catch ( err ) {
			setState( { kind: 'error', message: err.message } );
		}
	};

	return (
		<div className="ogc-section-import-export">
			<h2>{ __( 'Import / Export', 'open-graph-control' ) }</h2>

			<Card>
				<CardBody>
					<h3 style={ { marginTop: 0 } }>
						{ __( 'Export', 'open-graph-control' ) }
					</h3>
					<p>
						{ __(
							'Download the current settings as a JSON file. Useful for moving configuration between environments or backing up before a bulk edit.',
							'open-graph-control'
						) }
					</p>
					<Button variant="primary" onClick={ download }>
						{ __( 'Download settings JSON', 'open-graph-control' ) }
					</Button>
					<details style={ { marginTop: '1rem' } }>
						<summary>
							{ __( 'Preview', 'open-graph-control' ) }
						</summary>
						<pre
							style={ {
								background: '#f0f0f1',
								padding: '0.75rem',
								overflow: 'auto',
								maxHeight: '20rem',
							} }
						>
							{ exportJson }
						</pre>
					</details>
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					<h3 style={ { marginTop: 0 } }>
						{ __( 'Import', 'open-graph-control' ) }
					</h3>
					<p>
						{ __(
							'Paste a previously exported settings JSON. Import is deep-merged over the current values, so omitted keys are preserved.',
							'open-graph-control'
						) }
					</p>
					<TextareaControl
						value={ importText }
						onChange={ ( v ) => setImportText( v ) }
						rows={ 10 }
					/>
					<Button
						variant="secondary"
						onClick={ runImport }
						disabled={ ! importText.trim() }
					>
						{ __( 'Import', 'open-graph-control' ) }
					</Button>
					{ state.kind === 'ok' && (
						<Notice status="success" isDismissible={ false }>
							{ state.message }
						</Notice>
					) }
					{ state.kind === 'error' && (
						<Notice status="error" isDismissible={ false }>
							{ state.message }
						</Notice>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
