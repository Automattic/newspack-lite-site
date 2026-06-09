/**
 * Appearance settings panel component.
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
import { APPEARANCE_FIELDS } from '../utils/settings-dataform';

/**
 * Renders the Appearance, Colors, and Typography settings sections.
 */
export const AppearancePanel = ( { settings, onChange }: PanelProps ) => {
	if ( ! settings ) {
		return null;
	}

	return (
		<>
			<div className="newspack-lite-section">
				<div className="newspack-lite-section-header">
					<h3>{ __( 'Appearance', 'newspack-lite-site' ) }</h3>
					<p>
						{ __(
							'Customize the visual appearance of your lite site. Keep changes minimal, as adding custom fonts or styles increases page size and may slow down the experience for readers on limited connections.',
							'newspack-lite-site'
						) }
					</p>
				</div>
			</div>

			<div className="newspack-lite-section">
				<div className="newspack-lite-section-fields">
					<DataForm
						data={ settings }
						fields={ APPEARANCE_FIELDS }
						form={ {
							layout: { type: 'regular' },
							fields: [
								{
									id: 'colors',
									label: __( 'Colors', 'newspack-lite-site' ),
									children: [ 'primary_color' ],
								},
								{
									id: 'typography',
									label: __(
										'Typography',
										'newspack-lite-site'
									),
									children: [
										'font_import_url',
										'font_body',
									],
								},
								{
									id: 'advanced',
									label: __(
										'Advanced',
										'newspack-lite-site'
									),
									children: [ 'custom_css' ],
								},
							],
						} }
						onChange={ ( partial ) =>
							onChange( partial as Partial< SiteSettings > )
						}
					/>
				</div>
			</div>
		</>
	);
};
