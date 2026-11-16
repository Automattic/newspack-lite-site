import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath( new URL( '../content/docs/', import.meta.url ) );
const publicDir = fileURLToPath( new URL( '../public/', import.meta.url ) );
const failures = [];

const REQUIRED_PAGES = [
	'index.mdx',
	'getting-started/index.mdx',
	'getting-started/install-the-plugin.mdx',
	'getting-started/turn-on-lite-site.mdx',
	'getting-started/add-an-rss-feed.mdx',
	'guides/index.mdx',
	'guides/settings.mdx',
	'guides/appearance.mdx',
	'guides/lite-homepage.mdx',
	'guides/lite-articles.mdx',
	'guides/rss-feeds.mdx',
	'guides/rss-entries.mdx',
	'guides/faq.mdx',
	'guides/troubleshooting.mdx',
	'developers/index.mdx',
	'developers/hooks.mdx',
	'developers/data.mdx',
	'developers/build-from-source.mdx',
];

const SIDEBAR_ORDER = {
	'meta.json': [ 'getting-started', 'guides', 'developers' ],
	'getting-started/meta.json': [ 'index', 'install-the-plugin', 'turn-on-lite-site', 'add-an-rss-feed' ],
	'guides/meta.json': [
		'index',
		'settings',
		'appearance',
		'lite-homepage',
		'lite-articles',
		'rss-feeds',
		'rss-entries',
		'faq',
		'troubleshooting',
	],
};

const SCREENSHOT_SIZE = { width: 3024, height: 1964 };

function webpSize( path ) {
	const bytes = readFileSync( path );
	if ( bytes.toString( 'ascii', 0, 4 ) !== 'RIFF' || bytes.toString( 'ascii', 8, 12 ) !== 'WEBP' ) {
		return null;
	}
	const chunk = bytes.toString( 'ascii', 12, 16 );
	if ( chunk === 'VP8 ' ) {
		return { width: bytes.readUInt16LE( 26 ) & 0x3fff, height: bytes.readUInt16LE( 28 ) & 0x3fff };
	}
	if ( chunk === 'VP8L' ) {
		const bits = bytes.readUInt32LE( 21 );
		return { width: ( bits & 0x3fff ) + 1, height: ( ( bits >> 14 ) & 0x3fff ) + 1 };
	}
	if ( chunk === 'VP8X' ) {
		return { width: bytes.readUIntLE( 24, 3 ) + 1, height: bytes.readUIntLE( 27, 3 ) + 1 };
	}
	return null;
}

const TECHNICAL_WORDS = [ 'hook', 'hooks', 'endpoint', 'endpoints', 'attribute', 'attributes', 'meta', 'cron' ];
const TECHNICAL_ACRONYMS = [ 'REST', 'API', 'PHP', 'JSON' ];
const ALLOWED = {
	'getting-started/install-the-plugin.mdx': [ 'PHP' ],
	'guides/troubleshooting.mdx': [ 'cron', 'WP-Cron' ],
};

function technicalWord( line, allowed = [] ) {
	const technical = TECHNICAL_WORDS.filter( ( word ) => ! allowed.includes( word ) );
	const words = line.match( new RegExp( `\\b(${ technical.join( '|' ) })\\b`, 'i' ) );
	if ( words ) {
		return words[ 1 ];
	}
	const acronyms = TECHNICAL_ACRONYMS.filter( ( acronym ) => ! allowed.includes( acronym ) );
	const match = line.match( new RegExp( `\\b(${ acronyms.join( '|' ) })\\b` ) );
	return match ? match[ 1 ] : null;
}

function walk( dir ) {
	return readdirSync( dir ).flatMap( ( name ) => {
		const path = join( dir, name );
		return statSync( path ).isDirectory() ? walk( path ) : [ path ];
	} );
}

for ( const page of REQUIRED_PAGES ) {
	if ( ! existsSync( join( root, page ) ) ) {
		failures.push( `missing page content/docs/${ page }` );
	}
}

for ( const [ file, expected ] of Object.entries( SIDEBAR_ORDER ) ) {
	const path = join( root, file );
	const pages = existsSync( path ) ? JSON.parse( readFileSync( path, 'utf8' ) ).pages : undefined;
	if ( JSON.stringify( pages ) !== JSON.stringify( expected ) ) {
		failures.push( `content/docs/${ file } pages must be ${ JSON.stringify( expected ) }, got ${ JSON.stringify( pages ) }` );
	}
}

for ( const metaPath of walk( root ).filter( ( file ) => file.endsWith( 'meta.json' ) ) ) {
	const folder = dirname( metaPath );
	const listed = JSON.parse( readFileSync( metaPath, 'utf8' ) ).pages ?? [];
	for ( const page of listed ) {
		if ( ! existsSync( join( folder, `${ page }.mdx` ) ) && ! existsSync( join( folder, page, 'index.mdx' ) ) ) {
			failures.push( `${ relative( root, metaPath ) } lists "${ page }", which has no page` );
		}
	}
	for ( const name of readdirSync( folder ) ) {
		const slug = name.replace( /\.mdx$/, '' );
		const isPage = name.endsWith( '.mdx' ) || existsSync( join( folder, name, 'index.mdx' ) );
		if ( isPage && ! ( relative( root, folder ) === '' && slug === 'index' ) && ! listed.includes( slug ) ) {
			failures.push( `${ relative( root, folder ) || 'docs' }/${ name } is missing from ${ relative( root, metaPath ) }` );
		}
	}
}

for ( const path of walk( root ).filter( ( file ) => file.endsWith( '.mdx' ) ) ) {
	const name = relative( root, path );
	const source = readFileSync( path, 'utf8' );
	const frontmatter = source.match( /^---\n([\s\S]*?)\n---/ );
	const isDeveloperPage = name.split( sep )[ 0 ] === 'developers';

	for ( const field of [ 'title', 'description' ] ) {
		if ( ! frontmatter || ! new RegExp( `^${ field }:\\s*\\S`, 'm' ).test( frontmatter[ 1 ] ) ) {
			failures.push( `${ name }: frontmatter needs a ${ field }` );
		}
	}

	source.split( '\n' ).forEach( ( line, index ) => {
		const at = `${ name }:${ index + 1 }`;
		if ( line.includes( '—' ) ) {
			failures.push( `${ at }: em dash` );
		}
		if ( /\bLite Sites\b/.test( line ) ) {
			failures.push( `${ at }: "Lite Sites", the brand is the singular "Lite Site"` );
		}
		const hook = ! isDeveloperPage && line.match( /\b(apply_filters|do_action|newspack_lite_site_\w*)/ );
		if ( hook ) {
			failures.push( `${ at }: hook identifier "${ hook[ 1 ] }" outside the Developers section` );
		}
		const word = ! isDeveloperPage && technicalWord( line, ALLOWED[ name ] );
		if ( word ) {
			failures.push( `${ at }: technical word "${ word }" outside the Developers section` );
		}
	} );

	for ( const [ tag ] of source.matchAll( /<Screenshot\b[^>]*>/g ) ) {
		if ( ! /\blabel="[^"]+"/.test( tag ) ) {
			failures.push( `${ name }: ${ tag } needs a non-empty label` );
		}
		const src = tag.match( /\bsrc="([^"]+)"/ )?.[ 1 ];
		if ( ! src ) {
			failures.push( `${ name }: ${ tag } needs a src, or it ships a placeholder box` );
		} else {
			if ( ! src.startsWith( '/' ) || ! existsSync( join( publicDir, src ) ) ) {
				failures.push( `${ name }: screenshot ${ src } doesn't exist in site/public` );
			}
			const { width, height } = SCREENSHOT_SIZE;
			const file = join( publicDir, src );
			if ( ! src.endsWith( '.webp' ) ) {
				failures.push( `${ name }: screenshot ${ src } isn't a WebP image` );
			} else if ( existsSync( file ) ) {
				const size = webpSize( file );
				if ( ! size ) {
					failures.push( `${ name }: screenshot ${ src } has a WebP header this check can't read` );
				} else if ( size.width !== width || size.height !== height ) {
					failures.push( `${ name }: screenshot ${ src } is ${ size.width }×${ size.height }, not the house ${ width }×${ height }` );
				}
			}
			if ( ! tag.includes( `width={${ width }}` ) || ! tag.includes( `height={${ height }}` ) ) {
				failures.push( `${ name }: screenshot ${ src } needs width={${ width }} height={${ height }}` );
			}
		}
	}
}

if ( failures.length ) {
	console.error( failures.map( ( failure ) => `check-content: ${ failure }` ).join( '\n' ) );
	process.exit( 1 );
}

console.log( 'check-content: OK' );
