import FieldSourcesTab from '../../field-sources/FieldSourcesTab.jsx';

/**
 * Thin section wrapper — delegates entirely to the standalone
 * FieldSourcesTab component that is also the entry point for the
 * isolated field-sources bundle.
 */
export default function FieldSources() {
	return <FieldSourcesTab />;
}
