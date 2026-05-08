/* global nlsAdmin */

class ColorPickerSettings {
	constructor() {
		this.picker = document.getElementById( 'nls-primary-color-picker' );
		this.resetBtn = document.getElementById( 'nls-reset-color' );

		if ( ! this.picker || ! this.resetBtn ) {
			return;
		}

		this.resetBtn.addEventListener( 'click', () => this.resetToDefault() );
	}

	resetToDefault() {
		this.picker.value = nlsAdmin.defaultColor;
	}
}

export default ColorPickerSettings;
