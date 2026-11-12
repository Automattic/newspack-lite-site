import { type CategoryData } from './settings';
import { type RssEntriesData } from './rss-entries';

declare global {
	interface Window {
		newspackLiteSite?: {
			defaultColor: string;
			homeUrl: string;
			categories: Array< CategoryData >;
			cronDisabled: boolean;
			intervals: Array< { value: string; label: string } >;
			authors: Array< { value: string; label: string } >;
			hasEntries: boolean;
			rssEntries?: RssEntriesData;
		};
	}
}

export {};
