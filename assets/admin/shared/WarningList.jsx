const COLORS = {
	error: '#d63638',
	warn: '#dba617',
	info: '#72aee6',
};

const ICONS = {
	error: '⚠',
	warn: '⚠',
	info: 'ℹ',
};

/**
 * Renders a flat list of preview warnings. Accepts the shape returned by
 * PreviewController:  [ { severity, field, message } ]
 *
 * @param {Object} props
 * @param {Array}  props.warnings Validator output array.
 * @return {null|Element} React element or null when empty.
 */
export default function WarningList( { warnings } ) {
	if ( ! warnings || warnings.length === 0 ) {
		return null;
	}
	return (
		<ul
			aria-label="Validation warnings"
			style={ {
				listStyle: 'none',
				margin: '0.75rem 0 0',
				padding: 0,
			} }
		>
			{ warnings.map( ( w, idx ) => (
				<li
					key={ `${ w.field }-${ idx }` }
					role={ w.severity === 'error' ? 'alert' : 'status' }
					style={ {
						padding: '0.4rem 0.6rem',
						borderLeft: `4px solid ${
							COLORS[ w.severity ] || COLORS.info
						}`,
						background: '#f6f7f7',
						marginBottom: '0.25rem',
						fontSize: '0.9em',
					} }
				>
					<span
						aria-hidden="true"
						style={ { marginRight: '0.5rem' } }
					>
						{ ICONS[ w.severity ] || ICONS.info }
					</span>
					<code
						style={ {
							marginRight: '0.5rem',
							fontSize: '0.85em',
						} }
					>
						{ w.field }
					</code>
					{ w.message }
				</li>
			) ) }
		</ul>
	);
}
