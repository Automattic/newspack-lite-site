/**
 * Data formatting utilities.
 */

/**
 * WordPress dependencies.
 */
import { dateI18n, getSettings } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { type LastResult } from '../types/rss-feed-import';
import { type CategoryData } from '../types/settings';

/**
 * Convert a Unix timestamp to a date/time string in the site's date format, time format and timezone.
 *
 * @param ts Unix timestamp in seconds.
 */
export function formatUnixTimestamp( ts: number ): string {
	const { formats } = getSettings();
	return dateI18n( `${ formats.date } ${ formats.time }`, ts * 1000 );
}

/**
 * Format a last_result object into a human-readable summary string.
 * Returns an empty string when result is null (no RSS feed import has run yet).
 *
 * @param result The last_result object from the RSS feed record, or null.
 */
export function formatLastResult( result: LastResult | null ): string {
	if ( ! result ) {
		return '';
	}
	if ( result.error ) {
		return sprintf(
			/* translators: %s: error message from the last import. */
			__( 'Error: %s', 'newspack-lite-site' ),
			result.error
		);
	}

	const imported = result.imported ?? 0;
	const failed = result.failed ?? 0;
	const upToDate = result.up_to_date ?? false;

	if ( result.empty && 0 === imported ) {
		return __( 'The RSS feed contains no items', 'newspack-lite-site' );
	}
	if ( failed > 0 ) {
		if ( upToDate && 0 === imported ) {
			return sprintf(
				/* translators: %d: number of entries that failed */
				_n( 'Up to date, %d failed', 'Up to date, %d failed', failed, 'newspack-lite-site' ),
				failed
			);
		}
		return sprintf(
			/* translators: 1: number of entries imported, such as "3 imported", 2: number of entries that failed, such as "1 failed" */
			__( '%1$s, %2$s', 'newspack-lite-site' ),
			sprintf(
				/* translators: %d: number of entries imported */
				_n( '%d imported', '%d imported', imported, 'newspack-lite-site' ),
				imported
			),
			sprintf(
				/* translators: %d: number of entries that failed */
				_n( '%d failed', '%d failed', failed, 'newspack-lite-site' ),
				failed
			)
		);
	}
	if ( upToDate && 0 === imported ) {
		return __( 'Up to date', 'newspack-lite-site' );
	}
	if ( upToDate ) {
		return sprintf(
			/* translators: %d: number of entries imported */
			_n( '%d imported, up to date', '%d imported, up to date', imported, 'newspack-lite-site' ),
			imported
		);
	}
	return sprintf(
		/* translators: %d: number of entries imported */
		_n( '%d imported', '%d imported', imported, 'newspack-lite-site' ),
		imported
	);
}

/**
 * Return a display label for a category, indented by depth.
 *
 * @param cat Category data object.
 */
export function getCategoryLabel( cat: CategoryData ): string {
	const prefix = '—'.repeat( cat.depth );
	return prefix ? prefix + ' ' + cat.name : cat.name;
}
