import test from 'node:test';
import assert from 'node:assert/strict';
import { cropRect } from './crop.mjs';

const boxes = {
	L: { x: 395, y: 0, width: 10, height: 10 },
	R: { x: 1150, y: 0, width: 10, height: 10 },
	A: { x: 0, y: 600, width: 10, height: 20 },
};
const spec = { left: 'L', right: 'R', anchor: 'A', anchorY: 112, drawWidth: 372, frameHeight: 250 };

test('crops horizontally from the boxes and anchors vertically', () => {
	const r = cropRect( boxes, spec );
	assert.equal( r.left, 790 );
	assert.equal( r.width, 1530 );
	assert.ok( Math.abs( ( 610 * 2 - r.top ) * ( 372 / 1530 ) - 112 ) <= 1 );
});

test('moves with the layout', () => {
	const shifted = Object.fromEntries( Object.entries( boxes ).map( ( [ k, b ] ) => [ k, { ...b, x: b.x + 40 } ] ) );
	const r = cropRect( shifted, spec );
	assert.equal( r.left, 870 );
	assert.equal( r.width, 1530 );
});

test('top page gives top 0', () => {
	const { anchor, anchorY, ...rest } = spec;
	assert.equal( cropRect( boxes, { ...rest, top: 'page' } ).top, 0 );
});

test('widens the crop by the margin on each side', () => {
	const r = cropRect( boxes, { ...spec, margin: 100 } );
	assert.equal( r.left, 790 - 200 );
	assert.equal( r.width, 1530 + 400 );
});

test('throws for a missing or zero-width box', () => {
	assert.throws( () => cropRect( { L: boxes.L, A: boxes.A }, spec ), /crop: no box for R/ );
	assert.throws( () => cropRect( { ...boxes, R: { x: 1150, y: 0, width: 0, height: 10 } }, spec ), /crop: no box for R/ );
});
