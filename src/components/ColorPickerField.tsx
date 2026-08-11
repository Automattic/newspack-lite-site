/**
 * Primary color picker field component.
 */

/**
 * WordPress dependencies.
 */
import { ColorPicker, Button, BaseControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type ColorPickerFieldProps } from '../types/settings';

/**
 * Color picker with a "Reset to theme default" button.
 *
 * An empty value means the theme default color is active. A hex string means
 * a custom override is set. The picker falls back to newspackLiteSite.defaultColor
 * when no override is present, so a valid color is always displayed.
 */
export const ColorPickerField = ( { value, onChange }: ColorPickerFieldProps ) => {
	const displayColor = value || window.newspackLiteSite?.defaultColor || '#808080';

	return (
		<BaseControl
			id="newspack-lite-primary-color"
			label={ __( 'Primary Color', 'newspack-lite-site' ) }
			help={
				value
					? __( 'Primary Color override active.', 'newspack-lite-site' )
					: __( "Defaulting to your theme's Primary Color.", 'newspack-lite-site' )
			}
		>
			<div className="newspack-lite-color-picker-wrap">
				<ColorPicker color={ displayColor } onChange={ onChange } />
				<Button variant="secondary" onClick={ () => onChange( '' ) } disabled={ ! value }>
					{ __( "Reset to your theme's default Primary Color", 'newspack-lite-site' ) }
				</Button>
			</div>
		</BaseControl>
	);
};
