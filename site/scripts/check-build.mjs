import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const dist = fileURLToPath( new URL( '../dist/', import.meta.url ) );
const failures = [];

function walk( dir ) {
	return readdirSync( dir ).flatMap( ( name ) => {
		const path = join( dir, name );
		return statSync( path ).isDirectory() ? walk( path ) : [ path ];
	} );
}

if ( ! existsSync( dist ) ) {
	console.error( 'check-build: dist/ missing, run npm run build first' );
	process.exit( 1 );
}

for ( const file of [ 'index.html', 'docs/index.html', '404.html' ] ) {
	if ( ! existsSync( join( dist, file ) ) ) {
		failures.push( `dist/${ file } missing` );
	}
}

const searchIndex = [ 'api/search', 'api/search.json' ]
	.map( ( file ) => join( dist, file ) )
	.find( ( path ) => existsSync( path ) && statSync( path ).isFile() );

if ( ! searchIndex ) {
	failures.push( 'search index (dist/api/search) missing' );
} else {
	try {
		const data = JSON.parse( readFileSync( searchIndex, 'utf8' ) );
		const records = Object.values( data?.docs?.docs ?? {} );
		const troubleshooting = records.find(
			( record ) => record.type === 'page' && record.url.replace( /\/?$/, '/' ) === '/docs/guides/troubleshooting/',
		);
		if ( ! troubleshooting || ! /WP-Cron/.test( JSON.stringify( troubleshooting ) ) ) {
			failures.push( 'search index has no troubleshooting page record mentioning WP-Cron' );
		}
		const indexed = new Set(
			records
				.filter( ( record ) => record.type === 'page' )
				.map( ( record ) => record.url.replace( /\/?$/, '/' ) ),
		);
		const built = existsSync( join( dist, 'docs' ) )
			? walk( join( dist, 'docs' ) )
					.filter( ( path ) => path.endsWith( 'index.html' ) )
					.map( ( path ) => '/' + relative( dist, path ).slice( 0, -'index.html'.length ) )
			: [];
		const missing = built.filter( ( url ) => ! indexed.has( url ) );
		if ( indexed.size === 0 || missing.length ) {
			failures.push(
				`search index has ${ indexed.size } pages and lacks ${ missing.length ? missing.join( ', ' ) : 'all docs pages' }`,
			);
		}
	} catch {
		failures.push( 'search index is not valid JSON' );
	}
}

if ( existsSync( join( dist, 'server' ) ) ) {
	failures.push( 'dist/server exists: the build must be static' );
}

if ( existsSync( join( dist, 'docs' ) ) ) {
	for ( const page of walk( join( dist, 'docs' ) ).filter( ( path ) => path.endsWith( 'index.html' ) ) ) {
		if ( ! /<title>[^<]+<\/title>/.test( readFileSync( page, 'utf8' ) ) ) {
			failures.push( `${ relative( dist, page ) } has no <title>` );
		}
	}
}

const pages = walk( dist ).filter( ( path ) => path.endsWith( '.html' ) );
for ( const page of pages ) {
	for ( const [ , href, fragment ] of readFileSync( page, 'utf8' ).matchAll( /href="(\/[^"#?]*)[^"#]*(?:#([^"]*))?"/g ) ) {
		if ( href.startsWith( '/_astro/' ) || href === '/favicon.svg' ) {
			continue;
		}
		const target = join( dist, href.replace( /\/?$/, '/' ), 'index.html' );
		const file = existsSync( target ) ? target : join( dist, href );
		if ( ! existsSync( file ) || ! statSync( file ).isFile() ) {
			failures.push( `${ relative( dist, page ) } links to ${ href }, which doesn't exist` );
		} else if ( fragment && file.endsWith( '.html' ) ) {
			const id = decodeURIComponent( fragment );
			if ( ! readFileSync( file, 'utf8' ).includes( `id="${ id }"` ) ) {
				failures.push( `${ relative( dist, page ) } links to ${ href }#${ fragment }, which has no matching id` );
			}
		}
	}
}

if ( failures.length ) {
	console.error( failures.map( ( failure ) => `check-build: ${ failure }` ).join( '\n' ) );
	process.exit( 1 );
}

console.log( 'check-build: OK' );
