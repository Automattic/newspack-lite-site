/**
 * Primary color picker field component.
 */

/**
 * WordPress dependencies.
 */
import { BaseControl, Button } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { Stack } from '@wordpress/ui';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies.
 */
import ColorPicker from 'newspack-components/dist/esm/color-picker';

/**
 * Internal dependencies.
 */
import { type ColorPickerFieldProps } from '../types/settings';

/**
 * Color picker with a "Reset to theme default" button beside it.
 *
 * An empty value means the theme default color is active. A hex string means
 * a custom override is set. The picker falls back to newspackLiteSite.defaultColor
 * when no override is present, so a valid color is always displayed.
 */
export const ColorPickerField = ( { value, onChange }: ColorPickerFieldProps ) => {
	const id = useInstanceId( ColorPickerField, 'newspack-lite-color-picker-field' );
	const displayColor = value || window.newspackLiteSite?.defaultColor || '#808080';

	const handleChange = ( color: string ) => {
		if ( color.toLowerCase() !== displayColor.toLowerCase() ) {
			onChange( color );
		}
	};

	return (
		<BaseControl
			id={ id }
			help={ value ? __( 'Using a custom color.', 'newspack-lite-site' ) : __( "Using the theme's primary color.", 'newspack-lite-site' ) }
			__nextHasNoMarginBottom
		>
			<Stack direction="row" gap="sm" align="flex-end">
				<div className="newspack-lite-color-picker-field__picker">
					<ColorPicker label={ __( 'Primary Color', 'newspack-lite-site' ) } color={ displayColor } onChange={ handleChange } />
				</div>
				<Button variant="secondary" onClick={ () => onChange( '' ) } disabled={ ! value } accessibleWhenDisabled __next40pxDefaultSize>
					{ __( 'Reset', 'newspack-lite-site' ) }
				</Button>
			</Stack>
		</BaseControl>
	);
};
