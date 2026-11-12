/**
 * DataForm configuration for the Settings page.
 */

/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { TextareaControl } from '@wordpress/components';
import { type Field, type DataFormControlProps } from '@wordpress/dataviews';

/**
 * External dependencies.
 */
import TextControl from 'newspack-components/dist/esm/text-control';

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
const CategoriesFieldEdit = ( { data, onChange }: DataFormControlProps< SiteSettings > ) => (
	<CategoriesField value={ data.categories ?? [] } onChange={ ids => onChange( { categories: ids } ) } />
);

/**
 * Custom Edit component wrapping TagsField for tag inclusion in DataForm.
 */
const TagsFieldEdit = ( { data, onChange }: DataFormControlProps< SiteSettings > ) => (
	<TagsField
		label={ __( 'Tags', 'newspack-lite-site' ) }
		value={ data.tags ?? [] }
		onChange={ ids => onChange( { tags: ids } ) }
		help={ __( 'Only posts with at least one of these tags appear on the Lite Site. Leave empty to include all posts.', 'newspack-lite-site' ) }
	/>
);

/**
 * Custom Edit component wrapping CategoriesField for category exclusion in DataForm.
 */
const ExcludedCategoriesFieldEdit = ( { data, onChange }: DataFormControlProps< SiteSettings > ) => (
	<CategoriesField
		label={ __( 'Excluded Categories', 'newspack-lite-site' ) }
		value={ data.excluded_categories ?? [] }
		onChange={ ids => onChange( { excluded_categories: ids } ) }
		help={ __( 'Posts in these categories never appear on the Lite Site, even if they match the filters above.', 'newspack-lite-site' ) }
	/>
);

/**
 * Custom Edit component wrapping TagsField for tag exclusion in DataForm.
 */
const ExcludedTagsFieldEdit = ( { data, onChange }: DataFormControlProps< SiteSettings > ) => (
	<TagsField
		label={ __( 'Excluded Tags', 'newspack-lite-site' ) }
		value={ data.excluded_tags ?? [] }
		onChange={ ids => onChange( { excluded_tags: ids } ) }
		help={ __( 'Posts with these tags never appear on the Lite Site, even if they match the filters above.', 'newspack-lite-site' ) }
	/>
);

/**
 * Custom Edit component for the URL Base field with a conditional live-site link.
 */
const UrlBaseFieldEdit = ( { data, field, onChange }: DataFormControlProps< SiteSettings > ) => {
	const homeUrl = window.newspackLiteSite?.homeUrl ?? `${ window.location.origin }/`;
	const archiveUrl = `${ homeUrl }${ data.url_base || 'lite' }/`;
	const help = sprintf(
		/* translators: %s: example Lite Site URL. */
		__( 'The path your Lite Site is served from, for example %s.', 'newspack-lite-site' ),
		archiveUrl
	);
	return (
		<TextControl
			label={ field.label }
			value={ data.url_base ?? '' }
			onChange={ ( val: string ) => onChange( { url_base: val } ) }
			help={ help }
			withMargin={ false }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
};

/**
 * Custom Edit component wrapping ColorPickerField for use in DataForm.
 */
const ColorPickerFieldEdit = ( { data, onChange }: DataFormControlProps< SiteSettings > ) => (
	<ColorPickerField value={ data.primary_color ?? '' } onChange={ val => onChange( { primary_color: val } ) } />
);

/**
 * Custom Edit component for the Font Import URL field with placeholder and caution help text.
 */
const FontImportUrlFieldEdit = ( { data, field, onChange }: DataFormControlProps< SiteSettings > ) => (
	<TextControl
		label={ field.label }
		value={ data.font_import_url ?? '' }
		placeholder="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap"
		onChange={ ( val: string ) => onChange( { font_import_url: val } ) }
		help={ __(
			'Paste the embed code or stylesheet URL from a font provider such as Google Fonts. Each extra font or weight makes pages slower to load, so add only what you need.',
			'newspack-lite-site'
		) }
		withMargin={ false }
		__nextHasNoMarginBottom
		__next40pxDefaultSize
	/>
);

/**
 * Custom Edit component for the Footer HTML field with code-style textarea.
 */
const FooterHtmlFieldEdit = ( { data, field, onChange }: DataFormControlProps< SiteSettings > ) => (
	<TextareaControl
		label={ field.label }
		value={ data.footer_html ?? '' }
		onChange={ val => onChange( { footer_html: val } ) }
		help={ field.description }
		rows={ 5 }
		className="newspack-lite-code-textarea"
		__nextHasNoMarginBottom
	/>
);

/**
 * Custom Edit component for the Custom CSS field with code-style textarea.
 */
const CustomCssFieldEdit = ( { data, field, onChange }: DataFormControlProps< SiteSettings > ) => (
	<TextareaControl
		label={ field.label }
		help={ field.description }
		value={ data.custom_css ?? '' }
		onChange={ val => onChange( { custom_css: val } ) }
		rows={ 10 }
		className="newspack-lite-code-textarea"
		__nextHasNoMarginBottom
	/>
);

/**
 * Field definitions for the General settings DataForm.
 */
export const SETTINGS_FIELDS: Field< SiteSettings >[] = [
	{
		id: 'enabled',
		/* translators: %s: product name, Lite Site. */
		label: sprintf( __( 'Enable %s', 'newspack-lite-site' ), 'Lite Site' ),
		type: 'boolean',
	},
	{
		id: 'url_base',
		label: __( 'Lite Site Path', 'newspack-lite-site' ),
		Edit: UrlBaseFieldEdit,
	},
	{
		id: 'posts_per_page',
		label: __( 'Posts per Page', 'newspack-lite-site' ),
		type: 'integer',
		description: __(
			'How many posts each page of the Lite Site homepage lists. Readers use the page links at the bottom to see older posts.',
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
		label: __( 'Open external links in a new tab', 'newspack-lite-site' ),
		type: 'boolean',
		description: __( 'Links to this site always open in the same tab.', 'newspack-lite-site' ),
	},
	{
		id: 'footer_html',
		label: __( 'Footer HTML', 'newspack-lite-site' ),
		Edit: FooterHtmlFieldEdit,
		description: __(
			'Shown at the bottom of every Lite Site page. Add links to your accessibility and privacy policies and other essential information. Basic HTML only; scripts are removed.',
			'newspack-lite-site'
		),
	},
	{
		id: 'ga4_measurement_id',
		label: __( 'GA4 Measurement ID', 'newspack-lite-site' ),
		type: 'text',
		description: __(
			"Lite Site pages don't load your site's usual scripts, so analytics stays off unless you add an ID here. Paste a Measurement ID, such as G-XXXXXXXXXX, to track visits with Google Analytics. This adds one script to every page.",
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
		label: __( 'Font Embed Code or URL', 'newspack-lite-site' ),
		Edit: FontImportUrlFieldEdit,
	},
	{
		id: 'font_body',
		label: __( 'Body Font', 'newspack-lite-site' ),
		type: 'text',
		description: __(
			"Enter the font name exactly as the provider lists it, for example Open Sans. Leave empty to use each reader's system font.",
			'newspack-lite-site'
		),
	},
	{
		id: 'custom_css',
		label: __( 'Custom CSS', 'newspack-lite-site' ),
		Edit: CustomCssFieldEdit,
		description: __( 'Added after the built-in styles, so your rules can override them. Every rule adds to page weight.', 'newspack-lite-site' ),
	},
];
