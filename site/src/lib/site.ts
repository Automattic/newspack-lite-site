export const SITE_NAME = 'Lite Site';

/**
 * A link in the header, the mobile menu or the footer.
 *
 * - `section`: the path this link marks as current. The link whose `section` is the longest
 *   prefix of the current path is the current one; `'/'` matches only the home page.
 * - `drawer`: in the mobile menu, `'tree'` marks the link that holds the docs page tree as a
 *   folder (its row links to `href` and has its own toggle), and `false` leaves out a link that
 *   is already a section of that tree.
 * - `external`: the link leaves the site. Menu rows stay plain links in the same tab.
 */
export type NavLink = {
	href: string;
	text: string;
	section?: string;
	drawer?: 'tree' | false;
	external?: boolean;
};

export const NAV_LINKS: NavLink[] = [
	{ href: '/', text: 'Home', section: '/' },
	{ href: '/docs/', text: 'Docs', section: '/docs/', drawer: 'tree' },
	{ href: '/about/', text: 'About', section: '/about/' },
	{ href: 'https://github.com/Automattic/newspack-lite-site', text: 'GitHub', external: true },
];

export const FOOTER_LINKS: NavLink[] = [];

/**
 * Whether the site has a dark color scheme. With `false` the site is light only: the header has
 * no theme toggle, and the head script never sets `dark`, whatever the system setting or a
 * choice stored earlier.
 */
export const DARK_MODE = true;

/**
 * Below this width the header shows the menu button and the site follows the system color
 * scheme. Keep it equal to Tailwind's `md` breakpoint, which the header's classes use.
 */
export const MOBILE_MENU_QUERY = '(width < 48rem)';

/** The static search index the docs search reads. */
export const SEARCH_INDEX = '/api/search';

/** The line the search overlay shows when nothing matches, such as the names worth searching for. */
export const SEARCH_EMPTY_HINT = 'No pages match. Try another word, such as RSS or appearance.';
