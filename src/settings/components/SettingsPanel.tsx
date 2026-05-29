/**
 * General settings panel component.
 */

/**
 * WordPress dependencies.
 */
import {
	ToggleControl,
	TextControl,
	TextareaControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type SiteSettings } from '../hooks/useSettings';
import { CategoriesField } from './CategoriesField';

interface Props {
	settings: SiteSettings | null;
	onChange: < K extends keyof SiteSettings >(
		key: K,
		value: SiteSettings[ K ]
	) => void;
}

/**
 * Renders all fields for the General settings section.
 */
export const SettingsPanel = ( { settings, onChange }: Props ) => {
	if ( ! settings ) {
		return null;
	}

	return (
		<div className="newspack-lite-section">
			<div className="newspack-lite-section-header">
				<h3>{ __( 'General', 'newspack-lite-site' ) }</h3>
				<p>
					{ __(
						"Lite Site is a text-only version of this website that loads faster and uses less data. It's designed to allow your readers to still be able to access your content despite connectivity issues, poor network coverage, or in the event of natural disasters and emergencies.",
						'newspack-lite-site'
					) }
				</p>
			</div>

			<div className="newspack-lite-section-fields">
				<ToggleControl
					label={ __(
						'Enable lite site feature',
						'newspack-lite-site'
					) }
					checked={ !! settings.enabled }
					onChange={ ( val ) => onChange( 'enabled', val ) }
					__nextHasNoMarginBottom
				/>

				<TextControl
					label={ __( 'URL Base', 'newspack-lite-site' ) }
					value={ settings.url_base ?? 'lite' }
					onChange={ ( val ) => onChange( 'url_base', val ) }
					help={ sprintf(
						/* translators: %s: site URL without trailing slash, e.g. https://example.com */
						__(
							'The URL base for the lite site (e.g. "lite" for %s/lite/article-slug).',
							'newspack-lite-site'
						),
						window.location.origin
					) }
					__nextHasNoMarginBottom
				/>

				<NumberControl
					label={ __(
						'Posts per archive page',
						'newspack-lite-site'
					) }
					value={ settings.posts_per_page ?? 10 }
					min={ 1 }
					max={ 100 }
					step={ 1 }
					onChange={ ( val: string | undefined ) => {
						const parsed = parseInt( val ?? '', 10 );
						onChange(
							'posts_per_page',
							isNaN( parsed )
								? 1
								: Math.min( 100, Math.max( 1, parsed ) )
						);
					} }
					help={ __(
						'Number of posts shown per page on the lite site archive. Defaults to the WordPress Reading setting.',
						'newspack-lite-site'
					) }
					__next40pxDefaultSize
				/>

				<CategoriesField
					value={ settings.categories ?? [] }
					onChange={ ( val ) => onChange( 'categories', val ) }
				/>

				<TextareaControl
					label={ __( 'Footer HTML', 'newspack-lite-site' ) }
					value={ settings.footer_html ?? '' }
					onChange={ ( val ) => onChange( 'footer_html', val ) }
					rows={ 5 }
					help={ __(
						'HTML to be displayed in the footer of lite site pages.',
						'newspack-lite-site'
					) }
					__nextHasNoMarginBottom
				/>

				<TextControl
					label={ __( 'GA4 Measurement ID', 'newspack-lite-site' ) }
					value={ settings.ga4_measurement_id ?? '' }
					onChange={ ( val ) =>
						onChange( 'ga4_measurement_id', val )
					}
					placeholder="G-XXXXXXXXXX"
					help={ __(
						'Google Analytics 4 Measurement ID. Since lite pages strip all scripts, this is used to re-inject GA4 tracking.',
						'newspack-lite-site'
					) }
					__nextHasNoMarginBottom
				/>
			</div>
		</div>
	);
};
