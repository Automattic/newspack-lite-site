import test from 'node:test';
import assert from 'node:assert/strict';
import { iconSvg } from './icon.mjs';

test( 'iconSvg draws the mark in white on a full-bleed brand square', () => {
	const svg = iconSvg( { path: 'M0 0L1 1Z' } );
	assert.match( svg, /viewBox="0 0 256 256"/ );
	assert.match( svg, /<rect[^>]*width="256"[^>]*height="256"[^>]*fill="#003da5"/ );
	assert.match( svg, /d="M0 0L1 1Z"/ );
	assert.match( svg, /fill="#fff"/ );
	assert.match( svg, /transform="translate\(28\.16 28\.16\) scale\(8\.32\)"/ );
} );
