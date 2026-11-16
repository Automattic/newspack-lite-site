import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { PARTNERS } from './partner-logos';
import { SiteLayout, type SitePageProps } from './site-layout';

export const ABOUT_DESCRIPTION =
	'Why Newspack built Lite Site, a free, open-source WordPress plugin that keeps a newsroom’s reporting reachable when networks are weak or overloaded.';

const EMERGENCY_MODE_URL = 'https://emergencymode.news/';
const ROLLING_COVERAGE_URL = 'https://rolling-coverage.space.fast/';
const REPOSITORY_URL = 'https://github.com/Automattic/newspack-lite-site';

const linkClassName = 'text-link';

function Section( { id, title, children }: { id: string; title: string; children: ReactNode } ) {
	return (
		<section aria-labelledby={ id } className="mt-(--newspack-ui-spacer-9)">
			<h2 id={ id } className="type-section text-foreground">
				{ title }
			</h2>
			<div className="mt-(--newspack-ui-spacer-2) space-y-(--newspack-ui-spacer-2) type-prose text-muted-foreground">
				{ children }
			</div>
		</section>
	);
}

export function About( props: SitePageProps ) {
	return (
		<SiteLayout { ...props }>
			<article className="site-container pt-[calc(var(--section-space)/2)]">
				<div className="max-w-(--newspack-ui-width-content)">
					<p className="type-small font-(weight:--newspack-ui-font-weight-medium) text-muted-foreground">About</p>
					<h1 className="mt-(--newspack-ui-spacer-2) type-headline text-foreground">Why we built Lite Site</h1>
					<p className="mt-(--newspack-ui-spacer-3) type-lead text-muted-foreground">
						Lite Site is a free, open-source WordPress plugin that serves a fast, text-only copy of a site. Newspack
						builds it as part of{ ' ' }
						<a href={ EMERGENCY_MODE_URL } className={ linkClassName }>
							Emergency Mode for News
						</a>
						.
					</p>

					<Section id="about-networks" title="Reporting that stays reachable">
						<p>
							During a storm, a fire, or a power cut, networks slow down or fail. Readers still look for
							local news, often on a weak mobile connection, and a full page with images and scripts may
							never finish loading.
						</p>
						<p>
							Lite Site gives those readers the words. It serves each article as plain text, so the page
							loads on a poor connection, and the newsroom’s reporting stays within reach.
						</p>
					</Section>

					<Section id="about-goals" title="What we aim for">
						<ul className="list-disc space-y-(--newspack-ui-spacer-1) pl-(--newspack-ui-spacer-4)">
							<li>Keep readers informed when local networks fail.</li>
							<li>Add as little work as possible for the newsroom.</li>
							<li>Put essential information before visual design.</li>
							<li>Stay fast when traffic is heavy.</li>
							<li>Stay open source and free for any WordPress site.</li>
						</ul>
						<p>
							The size of the saving depends on the site. In our tests, a typical article fell from about
							3 MB to under 50 KB.
						</p>
					</Section>

					<Section id="about-emergency-mode" title="Part of Emergency Mode for News">
						<p>
							<a href={ EMERGENCY_MODE_URL } className={ linkClassName }>
								Emergency Mode for News
							</a>{ ' ' }
							offers resources, tools, and training for journalists and newsrooms before, during, and after
							a climate disaster. OpenNews runs it, Newspack provides the technology, and NC Local is a
							partner.
						</p>
						<p>
							Lite Site sits alongside{ ' ' }
							<a href={ ROLLING_COVERAGE_URL } className={ linkClassName }>
								Rolling Coverage
							</a>
							, the liveblog tool from the same project. Rolling Coverage covers a story as it unfolds. Lite
							Site keeps the site readable while it does.
						</p>
						<p>
							The code is on{ ' ' }
							<a href={ REPOSITORY_URL } className={ linkClassName }>
								GitHub
							</a>
							.
						</p>
					</Section>

					<section aria-labelledby="about-partners" className="mt-(--newspack-ui-spacer-9)">
						<h2 id="about-partners" className="type-section text-foreground">
							Partners
						</h2>
						<ul className="mt-(--newspack-ui-spacer-4) flex flex-wrap items-center gap-x-(--newspack-ui-spacer-8) gap-y-(--newspack-ui-spacer-4)">
							{ PARTNERS.map( ( partner ) => (
								<li key={ partner.url }>
									<a
										href={ partner.url }
										aria-label={ partner.name }
										className="flex text-muted-foreground transition-colors duration-(--newspack-ui-duration-color) hover:text-foreground"
									>
										<svg aria-hidden="true" viewBox={ partner.viewBox } className={ `w-auto ${ partner.className }` }>
											<path fill="currentColor" fillRule={ partner.evenOdd ? 'evenodd' : undefined } d={ partner.path } />
										</svg>
									</a>
								</li>
							) ) }
						</ul>
					</section>

					<div className="mt-(--newspack-ui-spacer-9) flex flex-col gap-(--newspack-ui-spacer-1) sm:flex-row">
						<Button asChild variant="brand">
							<a href="/docs/getting-started/">Get started</a>
						</Button>
						<Button asChild variant="outline">
							<a href="/docs/">Browse the docs</a>
						</Button>
					</div>
				</div>
			</article>
		</SiteLayout>
	);
}
