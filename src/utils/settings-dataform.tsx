/**
 * DataForm configuration for the Settings page.
 */

/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { TextareaControl, TextControl } from '@wordpress/components';
import { type Field, type DataFormControlProps } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import { type SiteSettings } from '../types/settings';
import { CategoriesField } from '../components/CategoriesField';
import { ColorPickerField } from '../components/ColorPickerField';
import { TagsField } from '../components/TagsField';

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
 * Custom Edit component wrapping TagsField for tag inclusion in DataForm.
 */
const TagsFieldEdit = ( {
	data,
	onChange,
}: DataFormControlProps< SiteSettings > ) => (
	<TagsField
		label={ __( 'Tags', 'newspack-lite-site' ) }
		value={ data.tags ?? [] }
		onChange={ ( ids ) => onChange( { tags: ids } ) }
		help={ __(
			'Include all content with this Tag in your Lite Site. Leave it empty to include posts with any Tag.',
			'newspack-lite-site'
		) }
	/>
);

/**
 * Custom Edit component wrapping CategoriesField for category exclusion in DataForm.
 */
const ExcludedCategoriesFieldEdit = ( {
	data,
	onChange,
}: DataFormControlProps< SiteSettings > ) => (
	<CategoriesField
		label={ __( 'Excluded Categories', 'newspack-lite-site' ) }
		value={ data.excluded_categories ?? [] }
		onChange={ ( ids ) => onChange( { excluded_categories: ids } ) }
		help={ __(
			'Content in these Categories will not appear on your Lite Site.',
			'newspack-lite-site'
		) }
	/>
);

/**
 * Custom Edit component wrapping TagsField for tag exclusion in DataForm.
 */
const ExcludedTagsFieldEdit = ( {
	data,
	onChange,
}: DataFormControlProps< SiteSettings > ) => (
	<TagsField
		label={ __( 'Excluded Tags', 'newspack-lite-site' ) }
		value={ data.excluded_tags ?? [] }
		onChange={ ( ids ) => onChange( { excluded_tags: ids } ) }
		help={ __(
			'Content with these Tags will not appear on your Lite Site.',
			'newspack-lite-site'
		) }
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
 * Custom Edit component for the Font Import URL field with placeholder and caution help text.
 */
const FontImportUrlFieldEdit = ( {
	data,
	field,
	onChange,
}: DataFormControlProps< SiteSettings > ) => (
	<TextControl
		label={ field.label }
		value={ data.font_import_url ?? '' }
		placeholder={ __(
			'<link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet"> or https://fonts.googleapis.com/css?family=Open+Sans',
			'newspack-lite-site'
		) }
		onChange={ ( val ) => onChange( { font_import_url: val } ) }
		help={ __(
			'The font will be loaded on your Lite Site pages. Caution: Loading multiple fonts or font weights can slow down your Lite Site. Choose only the fonts and weights you need.',
			'newspack-lite-site'
		) }
	/>
);

/**
 * Custom Edit component for the Footer HTML field with code-style textarea.
 */
const FooterHtmlFieldEdit = ( {
	data,
	field,
	onChange,
}: DataFormControlProps< SiteSettings > ) => (
	<TextareaControl
		label={ field.label }
		value={ data.footer_html ?? '' }
		onChange={ ( val ) => onChange( { footer_html: val } ) }
		rows={ 5 }
		className="newspack-lite-code-textarea"
	/>
);

/**
 * Custom Edit component for the Custom CSS field with code-style textarea.
 */
const CustomCssFieldEdit = ( {
	data,
	field,
	onChange,
}: DataFormControlProps< SiteSettings > ) => (
	<TextareaControl
		label={ field.label }
		help={ field.description }
		value={ data.custom_css ?? '' }
		onChange={ ( val ) => onChange( { custom_css: val } ) }
		rows={ 10 }
		className="newspack-lite-code-textarea"
	/>
);

/**
 * Field definitions for the General settings DataForm.
 */
export const SETTINGS_FIELDS: Field< SiteSettings >[] = [
	{
		id: 'enabled',
		label: __( 'Enable Lite Sites for your content', 'newspack-lite-site' ),
		type: 'boolean',
	},
	{
		id: 'url_base',
		label: __( 'Lite Site Suffix', 'newspack-lite-site' ),
		type: 'text',
		description: sprintf(
			/* translators: %s: site URL without trailing slash, e.g. https://example.com */
			__(
				'The URL base for the Lite Site (e.g. "lite" for %s/lite/article-slug).',
				'newspack-lite-site'
			),
			window.location.origin
		),
	},
	{
		id: 'posts_per_page',
		label: __(
			'Amount of content visible on Lite Site homepage',
			'newspack-lite-site'
		),
		type: 'integer',
		description: __(
			'Amount of content shown before a Back/Next button appears. Defaults to your WordPress Reading setting value for pagination.',
			'newspack-lite-site'
		),
	},
	{
		id: 'categories',
		label: __( 'Categories', 'newspack-lite-site' ),
		Edit: CategoriesFieldEdit,
	},
	{
		id: 'include_subcategories',
		label: __( 'Include subcategories', 'newspack-lite-site' ),
		type: 'boolean',
	},
	{
		id: 'tags',
		label: __( 'Tags', 'newspack-lite-site' ),
		Edit: TagsFieldEdit,
	},
	{
		id: 'excluded_categories',
		label: __( 'Excluded Categories', 'newspack-lite-site' ),
		Edit: ExcludedCategoriesFieldEdit,
	},
	{
		id: 'excluded_tags',
		label: __( 'Excluded Tags', 'newspack-lite-site' ),
		Edit: ExcludedTagsFieldEdit,
	},
	{
		id: 'external_links_new_tab',
		label: __(
			'Open any external links in a new tab',
			'newspack-lite-site'
		),
		type: 'boolean',
		description: __(
			'When enabled, links to external websites open in a new browser tab. Disable to open them in place.',
			'newspack-lite-site'
		),
	},
	{
		id: 'footer_html',
		label: __( 'Footer HTML', 'newspack-lite-site' ),
		Edit: FooterHtmlFieldEdit,
		description: __(
			'HTML content shown in the footer across all Lite Site pages. Consider including Accessibility and Privacy policies and other essential content. Use the Lite Site Suffix set to provide low-bandwidth, text-only versions.',
			'newspack-lite-site'
		),
	},
	{
		id: 'ga4_measurement_id',
		label: __( 'GA4 Measurement ID', 'newspack-lite-site' ),
		type: 'text',
		description: __(
			'Google Analytics 4 Measurement ID. Lite Site strips all the usual WordPress script inclusions, so this will re-inject GA4 tracking (which is turned off by default, so Lite Sites load very quickly).',
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
		label: __( 'Font Provider Import Code or URL', 'newspack-lite-site' ),
		Edit: FontImportUrlFieldEdit,
	},
	{
		id: 'font_body',
		label: __( 'Body Font', 'newspack-lite-site' ),
		type: 'text',
		description: __(
			'Font name for Body Font. This must match the Font Provider Import (e.g. "Open Sans"). Leave empty to use the user\'s device system font.',
			'newspack-lite-site'
		),
	},
	{
		id: 'custom_css',
		label: __( 'Custom CSS', 'newspack-lite-site' ),
		Edit: CustomCssFieldEdit,
		description: __(
			'CSS injected into the <head> of every Lite Site page, after built-in stylesheets. Use this to override default styles or add custom rules.',
			'newspack-lite-site'
		),
	},
];
