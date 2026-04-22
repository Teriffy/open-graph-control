import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Spinner, Button, Notice } from '@wordpress/components';
import { api } from './api.js';
import PluginRow from './PluginRow.jsx';

/**
 * Main Field Sources tab — loads the current mapping and renders per-plugin
 * rows for ACF and JetEngine.
 */
export default function FieldSourcesTab() {
	const [ mapping, setMapping ] = useState( null );
	const [ postTypes, setPostTypes ] = useState( [] );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		api.getMapping().then( ( m ) => setMapping( m || { acf: {}, jet: {} } ) );
		// Public post types come from OGC_BOOT — if available, use them; otherwise empty list.
		setPostTypes( Array.isArray( window.OGC_BOOT?.postTypes ) ? window.OGC_BOOT.postTypes : [ 'post', 'page' ] );
	}, [] );

	if ( ! mapping ) {
		return <Spinner />;
	}

	const save = async () => {
		setSaving( true );
		try {
			const next = await api.putMapping( mapping );
			setMapping( next );
			setNotice( { type: 'success', msg: __( 'Mapping saved.', 'open-graph-control' ) } );
		} catch ( e ) {
			setNotice( { type: 'error', msg: __( 'Save failed.', 'open-graph-control' ) } );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="ogc-field-sources">
			{ notice && (
				<Notice status={ notice.type } onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }
			<p>
				{ __(
					'Map ACF or JetEngine custom fields to the OG title and description resolver chains. When mapped and populated, the field value wins over post_title / post_excerpt.',
					'open-graph-control'
				) }
			</p>
			<PluginRow
				plugin="acf"
				label="ACF"
				postTypes={ postTypes }
				mapping={ mapping.acf || {} }
				onChange={ ( next ) => setMapping( { ...mapping, acf: next } ) }
			/>
			<PluginRow
				plugin="jet"
				label="JetEngine"
				postTypes={ postTypes }
				mapping={ mapping.jet || {} }
				onChange={ ( next ) => setMapping( { ...mapping, jet: next } ) }
			/>
			<Button variant="primary" onClick={ save } disabled={ saving }>
				{ saving
					? __( 'Saving…', 'open-graph-control' )
					: __( 'Save mappings', 'open-graph-control' ) }
			</Button>
		</div>
	);
}
