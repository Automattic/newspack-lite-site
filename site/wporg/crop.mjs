/**
 * Crop rectangle in image pixels, derived from recorded element boxes.
 */
export function cropRect( boxes, spec, scale = 2 ) {
	const box = ( selector ) => {
		const b = boxes[ selector ];
		if ( ! b || ! b.width ) {
			throw new Error( `crop: no box for ${ selector }` );
		}
		return b;
	};
	const margin = ( spec.margin || 0 ) * scale;
	const left = box( spec.left ).x * scale - margin;
	const right = ( box( spec.right ).x + box( spec.right ).width ) * scale + margin;
	const width = Math.round( right - left );
	const factor = spec.drawWidth / width;
	const height = Math.round( spec.frameHeight / factor );
	let top = 0;
	if ( spec.top !== 'page' ) {
		const a = box( spec.anchor );
		top = Math.round( ( a.y + a.height / 2 ) * scale - spec.anchorY / factor );
	}
	return { left: Math.round( left ), top, width, height };
}
