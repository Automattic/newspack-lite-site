/**
 * TypeScript types for the RSS page.
 */

/**
 * A tab on the RSS page.
 */
export type RssTab = 'entries' | 'feeds';

/**
 * Data localized to the RSS page.
 */
export interface RssData {
	canManageFeeds: boolean;
	hasFeeds: boolean;
	hasEntries: boolean;
	defaultTab: RssTab;
}

/**
 * Props the RSS page passes to each tab.
 */
export interface RssTabProps {
	tabbedNavigation?: JSX.Element;
	isActive?: boolean;
}
