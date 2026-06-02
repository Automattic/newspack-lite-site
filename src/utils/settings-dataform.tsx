/**
 * DataForm configuration for the Settings page.
 */

/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { type Field, type DataFormControlProps } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { type SiteSettings } from '../types/settings';
import { CategoriesField } from '../components/CategoriesField';
import { ColorPickerField } from '../components/ColorPickerField';

/**
 * Custom Edit component wrapping CategoriesField for use in DataForm.
 */
const CategoriesFieldEdit = ( {
	data,
	onChange,
}: DataFormControlProps< SiteSettings > ) => (
	<CategoriesField
		value={ data.categories ?? [] }
		onChange={ ( ids ) => onChange( { categories: ids } ) }
	/>
);

/**
 * Custom Edit component wrapping ColorPickerField for use in DataForm.
 */
const ColorPickerFieldEdit = ( {
	data,
	onChange,
}: DataFormControlProps< SiteSettings > ) => (
	<ColorPickerField
		value={ data.primary_color ?? '' }
		onChange={ ( val ) => onChange( { primary_color: val } ) }
	/>
);

/**
 * Field definitions for the General settings DataForm.
 */
export const SETTINGS_FIELDS: Field< SiteSettings >[] = [
	{
		id: 'enabled',
		label: __( 'Enable lite site feature', 'newspack-lite-site' ),
		type: 'boolean',
	},
	{
		id: 'url_base',
		label: __( 'URL Base', 'newspack-lite-site' ),
		type: 'text',
		description: sprintf(
			/* translators: %s: site URL without trailing slash, e.g. https://example.com */
			__(
				'The URL base for the lite site (e.g. "lite" for %s/lite/article-slug).',
				'newspack-lite-site'
			),
			window.location.origin
		),
	},
	{
		id: 'posts_per_page',
		label: __( 'Posts per archive page', 'newspack-lite-site' ),
		type: 'integer',
		description: __(
			'Number of posts shown per page on the lite site archive. Defaults to the WordPress Reading setting.',
			'newspack-lite-site'
		),
	},
	{
		id: 'categories',
		label: __( 'Categories', 'newspack-lite-site' ),
		Edit: CategoriesFieldEdit,
	},
	{
		id: 'footer_html',
		label: __( 'Footer HTML', 'newspack-lite-site' ),
		type: 'text',
		Edit: { control: 'textarea', rows: 5 },
		description: __(
			'HTML to be displayed in the footer of lite site pages.',
			'newspack-lite-site'
		),
	},
	{
		id: 'ga4_measurement_id',
		label: __( 'GA4 Measurement ID', 'newspack-lite-site' ),
		type: 'text',
		description: __(
			'Google Analytics 4 Measurement ID. Since lite pages strip all scripts, this is used to re-inject GA4 tracking.',
			'newspack-lite-site'
		),
	},
];

/**
 * Field definitions for the Appearance settings DataForm.
 */
export const APPEARANCE_FIELDS: Field< SiteSettings >[] = [
	{
		id: 'primary_color',
		label: __( 'Primary Color', 'newspack-lite-site' ),
		Edit: ColorPickerFieldEdit,
	},
	{
		id: 'font_import_url',
		label: __( 'Font Import URL', 'newspack-lite-site' ),
		type: 'text',
		description: __(
			'URL or <link> tag from your font provider (Google Fonts, Adobe Fonts, etc.). The font will be loaded on lite site pages.',
			'newspack-lite-site'
		),
	},
	{
		id: 'font_body',
		label: __( 'Body Font', 'newspack-lite-site' ),
		type: 'text',
		description: __(
			'Font name to use for body text, must match the imported font (e.g. "Open Sans"). Leave empty to use the system font.',
			'newspack-lite-site'
		),
	},
];
