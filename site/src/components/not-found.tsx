import { Button } from '@/components/ui/button';
import { SiteLayout, type SitePageProps } from './site-layout';

export const NOT_FOUND_DESCRIPTION = 'The page may have moved, or the link may be out of date.';

export function NotFound( props: SitePageProps ) {
	return (
		<SiteLayout { ...props }>
			<div className="site-container pt-[calc(var(--section-space)/2)]">
				<p className="type-small font-(weight:--newspack-ui-font-weight-medium) text-muted-foreground">Page not found</p>
				<h1 className="mt-(--newspack-ui-spacer-3) max-w-[10.55em] type-display text-foreground">We can’t find this page</h1>
				<p className="mt-(--newspack-ui-spacer-3) max-w-[52ch] type-lead text-muted-foreground">
					The page may have moved, or the link may be out of date. Browse the docs, or start again from the homepage.
				</p>
				<div className="mt-(--newspack-ui-spacer-5) flex flex-col gap-(--newspack-ui-spacer-1) sm:flex-row">
					<Button asChild>
						<a href="/docs/">Browse the docs</a>
					</Button>
					<Button asChild variant="outline">
						<a href="/">Go to the homepage</a>
					</Button>
				</div>
			</div>
		</SiteLayout>
	);
}
