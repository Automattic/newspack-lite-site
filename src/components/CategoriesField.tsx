/**
 * Category multi-select field component.
 */

/**
 * WordPress dependencies.
 */
import { BaseControl, FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import {
	type CategoryData,
	type CategoriesFieldProps,
} from '../types/settings';
import { getCategoryLabel } from '../utils/formatters';

/**
 * Multi-select category field backed by FormTokenField.
 *
 * Hierarchy is conveyed via em-dash prefix labels derived from each
 * category's depth. An empty selection means "all categories".
 */
export const CategoriesField = ( {
	value,
	onChange,
	label = __( 'Categories', 'newspack-lite-site' ),
	help = __(
		'Select categories to include in the lite site archive. Leave empty to include all categories.',
		'newspack-lite-site'
	),
}: CategoriesFieldProps ) => {
	const categories = window.newspackLiteSite?.categories ?? [];
	const suggestions = categories.map( getCategoryLabel );

	// Convert stored term ID array to display label array for FormTokenField.
	const tokenValue = value
		.map( ( id ) => categories.find( ( cat ) => cat.id === id ) )
		.filter( ( cat ): cat is CategoryData => cat !== undefined )
		.map( getCategoryLabel );

	const handleChange = ( tokens: ( string | { value: string } )[] ) => {
		const ids = tokens
			.map( ( token ) =>
				typeof token === 'string' ? token : token.value
			)
			.map( ( tokenLabel ) =>
				categories.find(
					( cat ) => getCategoryLabel( cat ) === tokenLabel
				)
			)
			.filter( ( cat ): cat is CategoryData => cat !== undefined )
			.map( ( cat ) => cat.id );
		onChange( ids );
	};

	return (
		<BaseControl help={ help }>
			<FormTokenField
				label={ label }
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
			/>
		</BaseControl>
	);
};
