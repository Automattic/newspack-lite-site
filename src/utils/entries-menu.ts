/**
 * Shows or hides the RSS Entries item in the Lite Site admin submenu.
 *
 * Hidden only while there are no feeds and no entries, matching the server rule.
 * Revealing moves it back to the top of the submenu, where the server puts it.
 */
export function syncEntriesMenu( feedCount: number ) {
	const item = document.querySelector( '#adminmenu .wp-submenu a[href*="edit.php?post_type=nls_rss_entry"]' )?.closest( 'li' );
	const list = item?.parentElement;
	if ( ! item || ! list ) {
		return;
	}
	const shouldHide = 0 === feedCount && ! window.newspackLiteSite?.hasEntries;
	item.classList.toggle( 'hidden', shouldHide );
	if ( ! shouldHide && list.querySelector( 'li:not(.wp-submenu-head)' ) !== item ) {
		list.querySelector( 'li:not(.wp-submenu-head)' )?.classList.remove( 'wp-first-item' );
		item.classList.add( 'wp-first-item' );
		list.insertBefore( item, list.querySelector( 'li:not(.wp-submenu-head)' ) );
	}
}
