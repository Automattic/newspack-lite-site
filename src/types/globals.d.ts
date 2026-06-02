import { type CategoryData } from './settings';

declare global {
	interface Window {
		newspackLiteSite?: {
			defaultColor: string;
			categories: Array< CategoryData >;
			cronDisabled: boolean;
			intervals: Array< { value: string; label: string } >;
			authors: Array< { value: string; label: string } >;
		};
	}
}

export {};
