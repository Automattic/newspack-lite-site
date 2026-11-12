/**
 * Reveals the RSS Entries item in the Lite Site admin submenu once a feed exists.
 *
 * The server hides the item while there are no feeds and no entries. Core echoes the
 * submenu class on both the list item and its link, so both are updated. The item moves
 * back to the top of the submenu, where the server puts it when visible.
 */
export function revealEntriesMenu() {
	const link = document.querySelector< HTMLAnchorElement >( '#adminmenu .wp-submenu a[href*="edit.php?post_type=nls_rss_entry"]' );
	const item = link?.closest( 'li' );
	const list = item?.parentElement;
	if ( ! link || ! item || ! list || ! item.classList.contains( 'hidden' ) ) {
		return;
	}
	item.classList.remove( 'hidden' );
	link.classList.remove( 'hidden' );

	const first = list.querySelector( 'li:not(.wp-submenu-head)' );
	if ( first && first !== item ) {
		first.classList.remove( 'wp-first-item' );
		first.querySelector( 'a' )?.classList.remove( 'wp-first-item' );
		item.classList.add( 'wp-first-item' );
		link.classList.add( 'wp-first-item' );
		list.insertBefore( item, first );
	}
}
