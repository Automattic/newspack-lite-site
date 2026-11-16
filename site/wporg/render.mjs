import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';
import { chromium } from 'playwright-core';
import sharp from 'sharp';
import { bannerHtml } from './banner.mjs';
import { cropRect } from './crop.mjs';
import { iconSvg } from './icon.mjs';

const WIDTH = 772;
const HEIGHT = 250;

async function launch() {
	return chromium.launch( {
		headless: true,
		...( process.env.CHROME_PATH
			? { executablePath: process.env.CHROME_PATH }
			: { channel: 'chrome' } ),
	} );
}

async function screenshots( html, config, targets ) {
	const browser = await launch();
	try {
		for ( const { scale, file } of targets ) {
			const context = await browser.newContext( {
				viewport: { width: WIDTH, height: HEIGHT },
				deviceScaleFactor: scale,
			} );
			const page = await context.newPage();
			await page.setContent( html );
			await page.evaluate( () => document.fonts.ready );
			const loaded = await page.evaluate(
				( size ) =>
					document.fonts.check( `750 ${ size }px Inter` ) &&
					[ ...document.fonts ].some( ( face ) => face.family === 'Inter' && face.status === 'loaded' ),
				config.headlineSize
			);
			if ( ! loaded ) {
				throw new Error( 'render: Inter did not load, refusing to draw with a fallback face' );
			}
			await page.screenshot( { path: file, type: 'png' } );
			await context.close();
		}
	} finally {
		await browser.close();
	}
}

async function assertWhiteGround( png, rect ) {
	const { data, info } = await sharp( png ).removeAlpha().raw().toBuffer( { resolveWithObject: true } );
	for ( const y of [ 0, rect.height - 1 ] ) {
		const offset = ( y * info.width + 1 ) * info.channels;
		const rgb = [ data[ offset ], data[ offset + 1 ], data[ offset + 2 ] ];
		if ( rgb.some( ( channel ) => 255 - channel > 1 ) ) {
			const hex = rgb.map( ( channel ) => channel.toString( 16 ).padStart( 2, '0' ) ).join( '' );
			throw new Error( `render: capture ground is not white (#${ hex })` );
		}
	}
}

/**
 * Writes the banner and icon assets for `config` into `outDir` from a capture
 * PNG and the `<capture>.boxes.json` file next to it. Returns the written paths.
 */
export async function render( { config, capture, outDir } ) {
	const boxes = JSON.parse( await readFile( `${ capture }.boxes.json`, 'utf8' ) );
	const rect = cropRect( boxes, config.crop );
	const cropDrawHeight = Math.round( ( rect.height * config.crop.drawWidth ) / rect.width );
	const cropped = await sharp( capture ).extract( rect ).png().toBuffer();
	await assertWhiteGround( cropped, rect );
	const html = bannerHtml( config, {
		cropDataUri: `data:image/png;base64,${ cropped.toString( 'base64' ) }`,
		cropDrawHeight,
	} );

	await mkdir( outDir, { recursive: true } );
	const banners = [
		{ scale: 1, file: join( outDir, 'banner-772x250.png' ) },
		{ scale: 2, file: join( outDir, 'banner-1544x500.png' ) },
	];
	await screenshots( html, config, banners );

	const svg = iconSvg( { path: config.markPath } );
	const svgFile = join( outDir, 'icon.svg' );
	await writeFile( svgFile, svg );
	const icons = [];
	for ( const size of [ 128, 256 ] ) {
		const file = join( outDir, `icon-${ size }x${ size }.png` );
		await sharp( Buffer.from( svg ), { density: 384 } ).resize( size, size ).png().toFile( file );
		icons.push( file );
	}
	return [ ...banners.map( ( b ) => b.file ), svgFile, ...icons ];
}

if ( process.argv[ 1 ] && resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url ) ) {
	const { values } = parseArgs( {
		options: {
			capture: { type: 'string' },
			out: { type: 'string', default: '../.wordpress-org' },
		},
	} );
	if ( ! values.capture ) {
		console.error( 'usage: node wporg/render.mjs --capture <png> [--out ../.wordpress-org]' );
		process.exit( 1 );
	}
	const { default: config } = await import( './config.mjs' );
	try {
		const files = await render( {
			config,
			capture: resolve( values.capture ),
			outDir: resolve( values.out ),
		} );
		files.forEach( ( file ) => console.log( file ) );
	} catch ( error ) {
		console.error( error.message );
		process.exit( 1 );
	}
}
