import CardTemplateTab from '../../card-template/CardTemplateTab.jsx';

/**
 * Thin section wrapper — delegates entirely to the standalone
 * CardTemplateTab component that is also the entry point for the
 * isolated card-template bundle.
 */
export default function CardTemplate() {
	return <CardTemplateTab />;
}
