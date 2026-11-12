import { type CategoryData } from './settings';
import { type RssEntriesData } from './rss-entries';
import { type RssData } from './rss';

declare global {
	interface Window {
		newspackLiteSite?: {
			defaultColor: string;
			homeUrl: string;
			categories: Array< CategoryData >;
			cronDisabled: boolean;
			intervals: Array< { value: string; label: string } >;
			authors: Array< { value: string; label: string } >;
			rss?: RssData | null;
			rssEntries?: RssEntriesData;
		};
	}
}

export {};
