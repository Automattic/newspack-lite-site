/**
 * Data formatting utilities for the RSS Feed Import page.
 */

/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type LastResult } from '../types/rss-feed-import';

/**
 * Convert a Unix timestamp to a locale-formatted date/time string.
 *
 * @param ts Unix timestamp in seconds.
 */
export function formatUnixTimestamp( ts: number ): string {
	return new Date( ts * 1000 ).toLocaleString();
}

/**
 * Format a last_result object into a human-readable summary string.
 * Returns an empty string when result is null (no import has run yet).
 *
 * @param result The last_result object from the feed record, or null.
 */
export function formatLastResult( result: LastResult | null ): string {
	if ( ! result ) {
		return '';
	}
	if ( result.error ) {
		return ` — ${ __( 'Error', 'newspack-lite-site' ) }: ${ result.error }`;
	}

	const imported = result.imported ?? 0;
	const failed = result.failed ?? 0;
	const upToDate = result.up_to_date ?? false;

	if ( upToDate && 0 === imported ) {
		return ` — ${ __( 'Up to date', 'newspack-lite-site' ) }`;
	}
	if ( upToDate ) {
		return sprintf(
			/* translators: %d: number of posts imported */
			__( '— %d imported, up to date', 'newspack-lite-site' ),
			imported
		);
	}
	if ( failed > 0 ) {
		return sprintf(
			/* translators: 1: number of posts imported, 2: number of posts failed */
			__( '— %1$d imported, %2$d failed', 'newspack-lite-site' ),
			imported,
			failed
		);
	}
	return sprintf(
		/* translators: %d: number of posts imported */
		__( '— %d imported', 'newspack-lite-site' ),
		imported
	);
}
