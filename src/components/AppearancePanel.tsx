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

const IS_BLOCK_THEME = !! window.newspackLiteSite?.isBlockTheme;

const COLORS_SECTION: SettingsSection = {
	id: 'colors',
	title: __( 'Colors', 'newspack-lite-site' ),
	description: __( 'Used for links in stories and the divider lines.', 'newspack-lite-site' ),
	fields: [ 'primary_color' ],
};

const SECTIONS: SettingsSection[] = [
	...( IS_BLOCK_THEME ? [] : [ COLORS_SECTION ] ),
	{
		id: 'typography',
		title: __( 'Typography', 'newspack-lite-site' ),
		description: IS_BLOCK_THEME
			? __( 'Use one of the site’s fonts for body text, or keep the reader’s system font.', 'newspack-lite-site' )
			: __( 'Load a web font for body text, or keep the reader’s system font.', 'newspack-lite-site' ),
		fields: IS_BLOCK_THEME ? [ 'font_body' ] : [ 'font_import_url', 'font_body' ],
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
export const AppearancePanel = ( { settings, savedSettings, onChange }: PanelProps ) => {
	if ( ! settings ) {
		return null;
	}

	return (
		<SettingsSections
			sections={ SECTIONS }
			fields={ APPEARANCE_FIELDS }
			settings={ settings }
			savedSettings={ savedSettings }
			onChange={ onChange }
		/>
	);
};
