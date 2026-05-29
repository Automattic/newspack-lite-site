/**
 * Primary color picker field component.
 */

/**
 * WordPress dependencies.
 */
import { ColorPicker, Button, BaseControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface Props {
	value: string;
	onChange: ( color: string ) => void;
}

/**
 * Color picker with a "Reset to theme default" button.
 *
 * An empty value means the theme default color is active. A hex string means
 * a custom override is set. The picker falls back to NewspackLiteSiteSettings.defaultColor
 * when no override is present, so a valid color is always displayed.
 */
export const ColorPickerField = ( { value, onChange }: Props ) => {
	const displayColor =
		value || window.NewspackLiteSiteSettings?.defaultColor || '#808080';

	return (
		<BaseControl
			id="newspack-lite-primary-color"
			label={ __( 'Primary Color', 'newspack-lite-site' ) }
			help={
				value
					? __(
							'Custom color override active.',
							'newspack-lite-site'
					  )
					: __( 'Using theme default color.', 'newspack-lite-site' )
			}
			__nextHasNoMarginBottom
		>
			<div className="newspack-lite-color-picker-wrap">
				<ColorPicker color={ displayColor } onChange={ onChange } />
				<Button
					variant="secondary"
					onClick={ () => onChange( '' ) }
					disabled={ ! value }
				>
					{ __( 'Reset to theme default', 'newspack-lite-site' ) }
				</Button>
			</div>
		</BaseControl>
	);
};
