/**
 * TypeScript types for the Settings & Appearance page.
 */

/**
 * The shape of the plugin's saved settings object.
 */
export interface SiteSettings {
	enabled: boolean;
	url_base: string;
	posts_per_page: number;
	categories: number[];
	include_subcategories: boolean;
	tags: number[];
	excluded_categories: number[];
	excluded_tags: number[];
	footer_html: string;
	custom_css: string;
	external_links_new_tab: boolean;
	ga4_measurement_id: string;
	primary_color: string;
	font_import_url: string;
	font_body: string;
}

/**
 * A single category entry with its depth in the category tree.
 */
export interface CategoryData {
	id: number;
	name: string;
	depth: number;
}

/**
 * Props for the CategoriesField component.
 */
export interface CategoriesFieldProps {
	value: number[];
	onChange: ( ids: number[] ) => void;
	label?: string;
	help?: string;
}

/**
 * A single tag item returned by the REST API.
 */
export interface TagItem {
	id: number;
	name: string;
}

/**
 * Props for the TagsField component.
 */
export interface TagsFieldProps {
	value: number[];
	onChange: ( ids: number[] ) => void;
	label: string;
	help?: string;
}

/**
 * Props for the ColorPickerField component.
 */
export interface ColorPickerFieldProps {
	value: string;
	onChange: ( color: string ) => void;
}

/**
 * Props for the AppHeader component.
 */
export interface AppHeaderProps {
	headerText: string;
	subHeaderText?: string;
}

/**
 * Props for a settings panel component.
 */
export interface PanelProps {
	settings: SiteSettings | null;
	onChange: ( partial: Partial< SiteSettings > ) => void;
}
