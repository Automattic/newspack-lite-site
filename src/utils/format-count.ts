/**
 * Locale-aware count formatting.
 */

/**
 * Group a number for the site's locale rather than the browser's, so the count matches server-rendered figures.
 *
 * WordPress ships locales `Intl` rejects, such as `pt_PT_ao90`, so the tag is shortened a subtag at a time until one is accepted.
 *
 * @param count Number to format.
 * @return The grouped number.
 */
export const formatCount = ( count: number ): string => {
	const lang = ( typeof document !== 'undefined' && document.documentElement.lang ) || '';
	const subtags = lang.replace( /_/g, '-' ).split( '-' ).filter( Boolean );
	while ( subtags.length ) {
		try {
			return Number( count ).toLocaleString( subtags.join( '-' ) );
		} catch {
			subtags.pop();
		}
	}
	return Number( count ).toLocaleString();
};
