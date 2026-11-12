/**
 * Appearance settings panel component.
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type PanelProps, type SettingsSection } from '../types/settings';
import { APPEARANCE_FIELDS } from '../utils/settings-dataform';
import { SettingsSections } from './SettingsSections';

const SECTIONS: SettingsSection[] = [
	{
		id: 'colors',
		title: __( 'Colors', 'newspack-lite-site' ),
		description: __( 'Used for links in stories and the divider lines.', 'newspack-lite-site' ),
		fields: [ 'primary_color' ],
	},
	{
		id: 'typography',
		title: __( 'Typography', 'newspack-lite-site' ),
		description: __( 'Load a web font for body text, or keep the reader’s system font.', 'newspack-lite-site' ),
		fields: [ 'font_import_url', 'font_body' ],
	},
	{
		id: 'advanced',
		title: __( 'Advanced', 'newspack-lite-site' ),
		description: __( 'Add your own CSS to every Lite Site page.', 'newspack-lite-site' ),
		fields: [ 'custom_css' ],
	},
];

/**
 * Renders the Colors, Typography and Advanced sections of the Appearance tab.
 */
export const AppearancePanel = ( { settings, onChange }: PanelProps ) => {
	if ( ! settings ) {
		return null;
	}

	return <SettingsSections sections={ SECTIONS } fields={ APPEARANCE_FIELDS } settings={ settings } onChange={ onChange } />;
};
