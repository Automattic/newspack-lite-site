/**
 * General settings panel component.
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { DataForm } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { type PanelProps, type SiteSettings } from '../types/settings';
import { SETTINGS_FIELDS } from '../utils/settings-dataform';

/**
 * Renders all fields for the General settings section.
 */
export const SettingsPanel = ( { settings, onChange }: PanelProps ) => {
	if ( ! settings ) {
		return null;
	}

	return (
		<div className="newspack-lite-section">
			<div className="newspack-lite-section-header">
				<h3>{ __( 'General', 'newspack-lite-site' ) }</h3>
				<p>
					{ __(
						'Lite Site is a text-only version of your WordPress site designed for fast, low-bandwidth access during connectivity interruptions, poor network coverage or emergencies.',
						'newspack-lite-site'
					) }
				</p>
			</div>

			<div className="newspack-lite-section-fields">
				<DataForm
					data={ settings }
					fields={ SETTINGS_FIELDS }
					form={ {
						layout: { type: 'regular' },
						fields: [
							'enabled',
							'url_base',
							'posts_per_page',
							'categories',
							'include_subcategories',
							'tags',
							'excluded_categories',
							'excluded_tags',
							'external_links_new_tab',
							'footer_html',
							'ga4_measurement_id',
						],
					} }
					onChange={ ( partial ) =>
						onChange( partial as Partial< SiteSettings > )
					}
				/>
			</div>
		</div>
	);
};
