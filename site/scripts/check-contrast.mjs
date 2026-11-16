import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// The kit's stylesheets come first, as global.css imports them before its own rules.
const css = [ 'newspack-ui-site.css', 'newspack-ui-fumadocs.css', 'global.css' ]
	.map( ( file ) => readFileSync( fileURLToPath( new URL( `../src/styles/${ file }`, import.meta.url ) ), 'utf8' ) )
	.join( '\n' );

function topLevelBlocks( selector ) {
	const declarations = {};
	const pattern = new RegExp( `(?:^|\\n)${ selector.replace( '.', '\\.' ) } \\{\\n([^}]*)\\}`, 'g' );
	for ( const [ , body ] of css.matchAll( pattern ) ) {
		for ( const [ , name, value ] of body.matchAll( /(--[\w-]+):\s*([^;]+);/g ) ) {
			declarations[ name ] = value.trim();
		}
	}
	return declarations;
}

const light = topLevelBlocks( ':root' );
const dark = { ...light, ...topLevelBlocks( '.dark' ) };
const modes = {
	light: { page: light, sidebar: light },
	dark: { page: dark, sidebar: { ...dark, ...topLevelBlocks( '.dark #nd-sidebar' ) } },
};

function hexToRgb( hex ) {
	const full = hex.length === 4 ? [ ...hex.slice( 1 ) ].map( ( c ) => c + c ).join( '' ) : hex.slice( 1 );
	return [ 0, 2, 4 ].map( ( i ) => parseInt( full.slice( i, i + 2 ), 16 ) / 255 );
}

const toLinear = ( c ) => ( c <= 0.04045 ? c / 12.92 : ( ( c + 0.055 ) / 1.055 ) ** 2.4 );
const toGamma = ( c ) => ( c <= 0.0031308 ? 12.92 * c : 1.055 * c ** ( 1 / 2.4 ) - 0.055 );

function rgbToOklab( [ r, g, b ] ) {
	const [ lr, lg, lb ] = [ r, g, b ].map( toLinear );
	const l = Math.cbrt( 0.4122214708 * lr + 0.5363325363 * lg + 0.0514459929 * lb );
	const m = Math.cbrt( 0.2119034982 * lr + 0.6806995451 * lg + 0.1073969566 * lb );
	const s = Math.cbrt( 0.0883024619 * lr + 0.2817188376 * lg + 0.6299787005 * lb );
	return [
		0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s,
		1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s,
		0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s,
	];
}

function oklabToRgb( [ L, A, B ] ) {
	const l = ( L + 0.3963377774 * A + 0.2158037573 * B ) ** 3;
	const m = ( L - 0.1055613458 * A - 0.0638541728 * B ) ** 3;
	const s = ( L - 0.0894841775 * A - 1.291485548 * B ) ** 3;
	return [
		4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
		-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
		-0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s,
	].map( ( c ) => Math.min( 1, Math.max( 0, toGamma( c ) ) ) );
}

function resolvePercent( value, map ) {
	const ref = value.match( /^var\((--[\w-]+)\)$/ );
	return ref ? String( map[ ref[ 1 ] ] ).replace( '%', '' ) : value.replace( '%', '' );
}

function resolve( value, map, depth = 0 ) {
	if ( depth > 20 ) {
		throw new Error( `check-contrast: can't resolve "${ value }"` );
	}
	const trimmed = value.trim();
	const ref = trimmed.match( /^var\((--[\w-]+)(?:,\s*(.+))?\)$/ );
	if ( ref ) {
		const next = map[ ref[ 1 ] ] ?? ref[ 2 ];
		if ( ! next ) {
			throw new Error( `check-contrast: ${ ref[ 1 ] } isn't set` );
		}
		return resolve( next, map, depth + 1 );
	}
	const mix = trimmed.match( /^color-mix\(in oklab, (.+?) (var\(--[\w-]+\)|[\d.]+%), (.+)\)$/ );
	if ( mix ) {
		const a = rgbToOklab( resolve( mix[ 1 ], map, depth + 1 ) );
		const b = rgbToOklab( resolve( mix[ 3 ], map, depth + 1 ) );
		const t = Number( resolvePercent( mix[ 2 ], map ) ) / 100;
		return oklabToRgb( a.map( ( channel, i ) => channel * t + b[ i ] * ( 1 - t ) ) );
	}
	if ( /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test( trimmed ) ) {
		return hexToRgb( trimmed );
	}
	throw new Error( `check-contrast: can't parse color "${ trimmed }"` );
}

function luminance( rgb ) {
	const [ r, g, b ] = rgb.map( ( c ) => c ** 2.4 );
	const y = 0.2126729 * r + 0.7151522 * g + 0.072175 * b;
	return y < 0.022 ? y + ( 0.022 - y ) ** 1.414 : y;
}

function apca( text, background ) {
	const [ yText, yBg ] = [ luminance( text ), luminance( background ) ];
	if ( Math.abs( yBg - yText ) < 0.0005 ) {
		return 0;
	}
	const sapc = yBg > yText ? ( yBg ** 0.56 - yText ** 0.57 ) * 1.14 : ( yBg ** 0.65 - yText ** 0.62 ) * 1.14;
	return Math.abs( sapc ) < 0.1 ? 0 : Math.abs( sapc * 100 ) - 2.7;
}

const TEXT = 60;
const MARK = 25;
const surface = '--newspack-ui-color-surface';

const PAIRS = [
	[ 'body text', '--newspack-ui-color-text', surface, TEXT ],
	[ 'muted text', '--newspack-ui-color-text-muted', surface, TEXT ],
	[ 'placeholder text', '--newspack-ui-color-text-subtle', surface, TEXT ],
	[ 'links and the active sidebar and TOC item', '--color-fd-primary', surface, TEXT ],
	[ 'muted text on the code surfaces', '--color-fd-muted-foreground', '--color-fd-muted', TEXT ],
	[ 'muted text on the docs sidebar', '--color-fd-muted-foreground', '--color-fd-muted', TEXT, 'sidebar' ],
	[ 'muted text on the docs sidebar hover', '--color-fd-muted-foreground', '--color-fd-secondary', TEXT, 'sidebar' ],
	[ 'button text', '--newspack-ui-color-btn-ink', '--newspack-ui-color-btn', TEXT ],
	[ 'brand button text', '--newspack-ui-color-btn-brand-on', '--newspack-ui-color-btn-brand', TEXT ],
	[ 'brand button text, hover', '--newspack-ui-color-btn-brand-on', '--newspack-ui-color-btn-brand-hover', TEXT ],
	[ 'brand button text, pressed', '--newspack-ui-color-btn-brand-on', '--newspack-ui-color-btn-brand-pressed', TEXT ],
	[ 'brand button fill', '--newspack-ui-color-btn-brand', surface, MARK ],
	[ 'brand button fill, hover', '--newspack-ui-color-btn-brand-hover', surface, MARK ],
	[ 'brand button fill, pressed', '--newspack-ui-color-btn-brand-pressed', surface, MARK ],
	[ 'brand outline button text', '--newspack-ui-color-btn-brand-text', surface, TEXT ],
	[ 'brand outline button text, hover', '--newspack-ui-color-btn-brand-text', '--newspack-ui-color-neutral-fill', TEXT ],
	[ 'brand outline button text, pressed', '--newspack-ui-color-btn-brand-text', '--newspack-ui-color-neutral-fill-hover', TEXT ],
	[ 'badge text', '--newspack-ui-color-text', '--newspack-ui-color-neutral-fill', TEXT ],
	[ 'brand headline word', '--newspack-ui-color-brand-text', surface, TEXT ],
	[ 'callout text, info', '--newspack-ui-color-text', '--newspack-ui-color-surface-subtle', TEXT ],
	[ 'callout text, warning', '--newspack-ui-color-text', '--newspack-ui-color-warning-tint', TEXT ],
	[ 'callout text, error', '--newspack-ui-color-text', '--newspack-ui-color-error-tint', TEXT ],
	[ 'logo mark', '--newspack-ui-color-brand-text', surface, MARK ],
	[ 'focus ring', '--newspack-ui-color-focus', surface, MARK ],
	[ 'field border', '--newspack-ui-color-input-border', surface, MARK ],
	[ 'error color', '--newspack-ui-color-error', surface, MARK ],
	[ 'callout icon, warning', '--newspack-ui-color-warning-dot', '--newspack-ui-color-warning-tint', MARK ],
	[ 'callout icon, info', '--newspack-ui-color-text-muted', '--newspack-ui-color-surface-subtle', MARK ],
];

// Dark hover and pressed mix toward the surface as newspack-ui does on WordPress, so their label dips below Lc 60 only while the pointer is on the button.
const TRANSIENT = new Set( [ 'dark: brand button text, hover', 'dark: brand button text, pressed' ] );

const failures = [];

for ( const [ mode, maps ] of Object.entries( modes ) ) {
	for ( const [ label, text, background, min, scope = 'page' ] of PAIRS ) {
		const map = maps[ scope ];
		const lc = apca( resolve( map[ text ] ?? `var(${ text })`, map ), resolve( map[ background ] ?? `var(${ background })`, map ) );
		const transient = TRANSIENT.has( `${ mode }: ${ label }` );
		console.log( `${ mode }: ${ label } = Lc ${ lc.toFixed( 1 ) } (${ transient ? 'transient state, not checked' : `needs ${ min }` })` );
		if ( lc < min && ! transient ) {
			failures.push( `${ mode }: ${ label } is Lc ${ lc.toFixed( 1 ) }, needs ${ min }` );
		}
	}
}

if ( failures.length ) {
	console.error( failures.map( ( failure ) => `check-contrast: ${ failure }` ).join( '\n' ) );
	process.exit( 1 );
}

console.log( 'check-contrast: OK' );
