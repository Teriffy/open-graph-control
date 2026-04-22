import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SelectControl, Spinner } from '@wordpress/components';
import { api } from './api.js';

/**
 * Per-plugin section showing a table of post types with Title + Description
 * field selectors.
 *
 * @param {Object}   props
 * @param {string}   props.plugin     Plugin slug: 'acf' or 'jet'.
 * @param {string}   props.label      Human-readable plugin label.
 * @param {string[]} props.postTypes  List of public post type slugs.
 * @param {Object}   props.mapping    Current mapping for this plugin keyed by post type.
 * @param {Function} props.onChange   Callback with updated mapping when a field changes.
 */
export default function PluginRow( { plugin, label, postTypes, mapping, onChange } ) {
	const [ fieldsByPostType, setFieldsByPostType ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ detected, setDetected ] = useState( null );

	useEffect( () => {
		// Probe detection using the first post type or 'post'.
		const probe = postTypes[ 0 ] || 'post';
		api
			.getFields( plugin, probe )
			.then( ( fields ) => {
				setDetected( Array.isArray( fields ) && fields.length > 0 );
				// Eagerly load fields for all post types.
				return Promise.all(
					postTypes.map( ( pt ) =>
						api.getFields( plugin, pt ).then( ( f ) => [ pt, Array.isArray( f ) ? f : [] ] )
					)
				);
			} )
			.then( ( entries ) => {
				setFieldsByPostType( Object.fromEntries( entries ) );
			} )
			.catch( () => setDetected( false ) )
			.finally( () => setLoading( false ) );
	}, [ plugin, postTypes ] );

	/**
	 * Builds SelectControl options for a given post type.
	 *
	 * @param {string} postType
	 * @return {Array<{label: string, value: string}>}
	 */
	const optionsFor = ( postType ) => {
		const fields = fieldsByPostType[ postType ] || [];
		return [
			{ label: __( '— none —', 'open-graph-control' ), value: '' },
			...fields.map( ( f ) => ( { label: f, value: f } ) ),
		];
	};

	const handleChange = ( postType, kind, value ) => {
		const next = {
			...mapping,
			[ postType ]: {
				...( mapping[ postType ] || {} ),
				[ kind ]: value || null,
			},
		};
		onChange( next );
	};

	return (
		<div className={ `ogc-plugin-row ogc-plugin-row--${ plugin }` }>
			<h3 className="ogc-plugin-row__heading">
				{ label }{ ' ' }
				{ detected === null && <Spinner /> }
				{ detected === true && (
					<span className="ogc-plugin-row__badge ogc-plugin-row__badge--detected" title={ __( 'Plugin detected', 'open-graph-control' ) }>
						{ '\u2705' }
					</span>
				) }
				{ detected === false && (
					<span className="ogc-plugin-row__badge ogc-plugin-row__badge--missing" title={ __( 'Plugin not active', 'open-graph-control' ) }>
						{ '\u274C' }
					</span>
				) }
			</h3>

			{ loading ? (
				<Spinner />
			) : (
				<table className="ogc-plugin-row__table widefat">
					<thead>
						<tr>
							<th>{ __( 'Post type', 'open-graph-control' ) }</th>
							<th>{ __( 'Title field', 'open-graph-control' ) }</th>
							<th>{ __( 'Description field', 'open-graph-control' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ postTypes.map( ( pt ) => (
							<tr key={ pt }>
								<td>
									<code>{ pt }</code>
								</td>
								<td>
									<SelectControl
										label={ __( 'Title field', 'open-graph-control' ) }
										hideLabelFromVision
										value={ mapping[ pt ]?.title || '' }
										options={ optionsFor( pt ) }
										onChange={ ( v ) => handleChange( pt, 'title', v ) }
									/>
								</td>
								<td>
									<SelectControl
										label={ __( 'Description field', 'open-graph-control' ) }
										hideLabelFromVision
										value={ mapping[ pt ]?.description || '' }
										options={ optionsFor( pt ) }
										onChange={ ( v ) => handleChange( pt, 'description', v ) }
									/>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
