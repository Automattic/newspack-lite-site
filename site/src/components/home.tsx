import { SiteLayout, type SitePageProps } from './site-layout';

export const SITE_DESCRIPTION = 'A text-only version of your site that loads fast on slow connections, during traffic spikes and in emergencies.';

export function Home( props: SitePageProps ) {
	return (
		<SiteLayout { ...props }>
			<h1 className="site-container type-display">Lite Site</h1>
		</SiteLayout>
	);
}
