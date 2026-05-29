declare global {
	interface Window {
		NewspackLiteSiteAdminHeader?: {
			title: string;
			tabs: Array< {
				id: string;
				label: string;
				href: string;
				isActive: boolean;
			} >;
		};
		NewspackLiteSiteSettings?: {
			defaultColor: string;
			tab: string;
			categories: Array< {
				id: number;
				name: string;
				depth: number;
			} >;
		};
		NewspackLiteSiteRssImport?: {
			cronDisabled: boolean;
			intervals: Array< { value: string; label: string } >;
			authors: Array< { value: string; label: string } >;
		};
	}
}

export {};
