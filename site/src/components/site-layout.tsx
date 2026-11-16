import type { ReactNode } from 'react';
import type { AstroProviderProps } from 'fumadocs-core/framework/astro';
import { AstroDocsSiteHeader } from '@/components/docs/astro-docs';
import { Footer, Wordmark } from './brand';

export type SitePageProps = {
	pathname: string;
	params: AstroProviderProps[ 'params' ];
};

export function SiteLayout( { pathname, params, children }: SitePageProps & { children: ReactNode } ) {
	return (
		<>
			<AstroDocsSiteHeader pathname={ pathname } params={ params } wordmark={ <Wordmark /> } />
			<main id="main" className="flex-1">
				{ children }
			</main>
			<Footer />
		</>
	);
}
