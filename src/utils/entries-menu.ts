/**
 * Shows or hides the RSS Entries item in the Lite Site admin submenu.
 *
 * Hidden only while there are no feeds and no entries, matching the server rule.
 */
export function syncEntriesMenu( feedCount: number ) {
	const link = document.querySelector( '#adminmenu a[href*="edit.php?post_type=nls_rss_entry"]' );
	const item = link?.closest( 'li' );
	if ( ! item ) {
		return;
	}
	item.classList.toggle( 'hidden', 0 === feedCount && ! window.newspackLiteSite?.hasEntries );
}
