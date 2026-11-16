const SIZE = 256;
const SHARE = 0.78;
const MARK_UNITS = 24;

/**
 * The listing icon: a full-bleed brand square with the mark in white, drawn
 * at 78% of the square and centred.
 */
export function iconSvg( { path } ) {
	const scale = ( SIZE * SHARE ) / MARK_UNITS;
	const offset = Number( ( ( SIZE - MARK_UNITS * scale ) / 2 ).toFixed( 4 ) );
	return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${ SIZE } ${ SIZE }" width="${ SIZE }" height="${ SIZE }"><rect width="${ SIZE }" height="${ SIZE }" fill="#003da5"/><path transform="translate(${ offset } ${ offset }) scale(${ scale })" fill="#fff" d="${ path }"/></svg>\n`;
}
