/**
 * Category multi-select field component.
 */

/**
 * WordPress dependencies.
 */
import { BaseControl, FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface CategoryData {
	id: number;
	name: string;
	depth: number;
}

/**
 * Return a display label for a category, indented by depth.
 */
function getCategoryLabel( cat: CategoryData ): string {
	const prefix = '—'.repeat( cat.depth );
	return prefix ? prefix + ' ' + cat.name : cat.name;
}

interface Props {
	value: number[];
	onChange: ( ids: number[] ) => void;
}

/**
 * Multi-select category field backed by FormTokenField.
 *
 * Hierarchy is conveyed via em-dash prefix labels derived from the depth value
 * in NewspackLiteSiteSettings.categories, populated by build_ordered_categories() in PHP.
 *
 * An empty selection means "all categories", as noted in the help text.
 */
export const CategoriesField = ( { value, onChange }: Props ) => {
	const categories = window.NewspackLiteSiteSettings?.categories ?? [];
	const suggestions = categories.map( getCategoryLabel );

	// Convert stored term ID array to display label array for FormTokenField.
	const tokenValue = value
		.map( ( id ) => categories.find( ( cat ) => cat.id === id ) )
		.filter( ( cat ): cat is CategoryData => cat !== undefined )
		.map( getCategoryLabel );

	function handleChange( tokens: ( string | { value: string } )[] ) {
		const ids = tokens
			.map( ( token ) =>
				typeof token === 'string' ? token : token.value
			)
			.map( ( label ) =>
				categories.find( ( cat ) => getCategoryLabel( cat ) === label )
			)
			.filter( ( cat ): cat is CategoryData => cat !== undefined )
			.map( ( cat ) => cat.id );
		onChange( ids );
	}

	return (
		<BaseControl
			help={ __(
				'Select categories to include in the lite site archive. Leave empty to include all categories. Selecting a parent category does not automatically include its subcategories — add them individually if needed.',
				'newspack-lite-site'
			) }
			__nextHasNoMarginBottom
		>
			<FormTokenField
				label={ __( 'Categories', 'newspack-lite-site' ) }
				value={ tokenValue }
				suggestions={ suggestions }
				onChange={ handleChange }
				__experimentalValidateInput={ ( token: string ) =>
					categories.some(
						( cat ) => getCategoryLabel( cat ) === token
					)
				}
				__experimentalExpandOnFocus
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		</BaseControl>
	);
};
