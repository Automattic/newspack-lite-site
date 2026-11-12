/**
 * TypeScript types for the RSS page.
 */

/**
 * External dependencies.
 */
import { type ReactNode } from 'react';

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
 * The parts of the RSS page header that a tab sets.
 */
export interface RssTabHeader {
	count?: number;
	countLabel?: string;
	actions?: ReactNode;
}

/**
 * Props the RSS page passes to each tab.
 */
export interface RssTabProps {
	isActive?: boolean;
	onHeaderChange?: ( header: RssTabHeader ) => void;
}
