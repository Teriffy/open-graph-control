const ICONS = {
	error: '⚠',
	warn: '⚠',
	info: 'ℹ',
};

const SEVERITY_CLASS = {
	error: 'ogc-warning-item ogc-warning-item--error',
	warn: 'ogc-warning-item ogc-warning-item--warn',
	info: 'ogc-warning-item',
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
		<ul aria-label="Validation warnings" className="ogc-warning-list">
			{ warnings.map( ( w, idx ) => (
				<li
					key={ `${ w.field }-${ idx }` }
					role={ w.severity === 'error' ? 'alert' : 'status' }
					className={
						SEVERITY_CLASS[ w.severity ] || SEVERITY_CLASS.info
					}
				>
					<span aria-hidden="true" className="ogc-warning-item__icon">
						{ ICONS[ w.severity ] || ICONS.info }
					</span>
					<code className="ogc-warning-item__field">{ w.field }</code>
					{ w.message }
				</li>
			) ) }
		</ul>
	);
}
