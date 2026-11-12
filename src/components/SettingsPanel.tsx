/**
 * General settings panel component.
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type PanelProps, type SettingsSection } from '../types/settings';
import { SETTINGS_FIELDS } from '../utils/settings-dataform';
import { SettingsSections } from './SettingsSections';

const SECTIONS: SettingsSection[] = [
	{
		id: 'lite-site',
		title: 'Lite Site',
		description: __( 'Set its address and how many posts each homepage page lists.', 'newspack-lite-site' ),
		fields: [ 'enabled', 'url_base', 'posts_per_page' ],
	},
	{
		id: 'content',
		title: __( 'Content', 'newspack-lite-site' ),
		description: __( 'A post must match every filter you set here to appear. Exclusions override inclusions.', 'newspack-lite-site' ),
		fields: [ 'categories', 'include_subcategories', 'tags', 'excluded_categories', 'excluded_tags' ],
	},
	{
		id: 'links-and-footer',
		title: __( 'Links and Footer', 'newspack-lite-site' ),
		description: __( 'Set how external links open and what the footer shows.', 'newspack-lite-site' ),
		fields: [ 'external_links_new_tab', 'footer_html' ],
	},
	{
		id: 'analytics',
		title: __( 'Analytics', 'newspack-lite-site' ),
		description: __( 'Track Lite Site visits with Google Analytics 4.', 'newspack-lite-site' ),
		fields: [ 'ga4_measurement_id' ],
	},
];

/**
 * Renders the Lite Site, Content, Links and Footer, and Analytics sections of the General tab.
 */
export const SettingsPanel = ( { settings, onChange }: PanelProps ) => {
	if ( ! settings ) {
		return null;
	}

	return <SettingsSections sections={ SECTIONS } fields={ SETTINGS_FIELDS } settings={ settings } onChange={ onChange } />;
};
