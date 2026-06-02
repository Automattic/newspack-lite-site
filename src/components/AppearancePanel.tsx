/**
 * Appearance settings panel component.
 */

/**
 * WordPress dependencies.
 */
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type PanelProps } from '../types/settings';
import { ColorPickerField } from './ColorPickerField';

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
				<div className="newspack-lite-section-header">
					<h3>{ __( 'Colors', 'newspack-lite-site' ) }</h3>
				</div>

				<div className="newspack-lite-section-fields">
					<ColorPickerField
						value={ settings.primary_color ?? '' }
						onChange={ ( val ) => onChange( 'primary_color', val ) }
					/>
				</div>
			</div>

			<div className="newspack-lite-section">
				<div className="newspack-lite-section-header">
					<h3>{ __( 'Typography', 'newspack-lite-site' ) }</h3>
				</div>

				<div className="newspack-lite-section-fields">
					<TextControl
						label={ __( 'Font Import URL', 'newspack-lite-site' ) }
						value={ settings.font_import_url ?? '' }
						onChange={ ( val ) =>
							onChange( 'font_import_url', val )
						}
						placeholder="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap"
						help={ __(
							'URL or <link> tag from your font provider (Google Fonts, Adobe Fonts, etc.). The font will be loaded on lite site pages.',
							'newspack-lite-site'
						) }
					/>

					<TextControl
						label={ __( 'Body Font', 'newspack-lite-site' ) }
						value={ settings.font_body ?? '' }
						onChange={ ( val ) => onChange( 'font_body', val ) }
						placeholder="Open Sans"
						help={ __(
							'Font name to use for body text, must match the imported font (e.g. "Open Sans"). Leave empty to use the system font.',
							'newspack-lite-site'
						) }
					/>
				</div>
			</div>
		</>
	);
};
