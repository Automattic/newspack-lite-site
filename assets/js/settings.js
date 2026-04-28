/* global nlsAdmin */
( function () {
	const picker = document.getElementById( 'nls-primary-color-picker' );
	const resetBtn = document.getElementById( 'nls-reset-color' );

	if ( ! picker || ! resetBtn ) {
		return;
	}

	resetBtn.addEventListener( 'click', function () {
		picker.value = nlsAdmin.defaultColor;
	} );
} )();
