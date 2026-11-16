import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { PARTNERS } from '@/components/partner-logos';
import { SiteLayout, type SitePageProps } from '@/components/site-layout';

const LEAD =
	'Lite Site adds a text-only copy of your WordPress site at its own address, so readers on weak or overloaded connections can still load your reporting when a disaster hits.';

export const SITE_DESCRIPTION = LEAD;

const EMERGENCY_MODE_URL = 'https://emergencymode.news/';

type DemoItem = { title: string; sticky?: boolean };

const DEMO_PAGES: DemoItem[][] = [
	[
		{ title: 'Kettle River flood: what’s closed and where to get help', sticky: true },
		{ title: 'Kettle River flood warning remains in effect' },
		{ title: 'Fell Creek flood warning lifted' },
		{ title: 'All schools open as normal tomorrow except Northgate High' },
		{ title: 'Mill Race Footbridge closes as the Kettle rises' },
		{ title: 'Kettle River crests at 21.3 feet and starts to fall' },
		{ title: 'Fairgrounds shelter open on Route 9' },
		{ title: 'Kettleford Lock: the river has stopped rising upstream' },
		{ title: 'Friday night games moved as the Kettle River rises' },
		{ title: 'All schools close at noon today' },
	],
	[
		{ title: 'Free sandbags at the Depot Street public works yard' },
		{ title: 'Buses cross the river on the Depot Street Bridge' },
		{ title: 'Why we cover the river the way we do' },
		{ title: 'What to do before the Kettle River rises' },
		{ title: 'How Riverbend Memorial gets ready for the river to rise' },
		{ title: 'New creek gauges buy Northgate a head start on flash floods' },
		{ title: 'Fire department adds a second swift-water rescue boat' },
		{ title: 'City commits $8.2 million to replace Fell Street culverts' },
		{ title: 'County well-check line adds weekly calls year round' },
		{ title: 'The rain is arriving harder, and the county is redoing its math' },
	],
	[
		{ title: 'The Kettle River is coming back, one mussel at a time' },
		{ title: 'Kettle River Bridge closes for two weeks of repairs' },
		{ title: 'Grove Avenue water main work starts Monday' },
	],
];

function Arrow( { className = '' }: { className?: string } ) {
	return (
		<svg
			aria-hidden="true"
			focusable="false"
			viewBox="0 0 16 16"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
			strokeLinejoin="round"
			className={ `size-4 shrink-0 ${ className }` }
		>
			<path d="M3 8h10M9 4l4 4-4 4" />
		</svg>
	);
}

const arrowMotion = 'transition-transform duration-200 ease-out-strong group-hover:translate-x-0.5 motion-reduce:transition-none';

function Actions() {
	return (
		<div className="flex flex-col gap-(--newspack-ui-spacer-1) sm:flex-row">
			<Button asChild variant="brand" className="group">
				<a href="/docs/getting-started/">
					Get started
					<Arrow className={ arrowMotion } />
				</a>
			</Button>
			<Button asChild variant="outline">
				<a href="/docs/">Browse the docs</a>
			</Button>
		</div>
	);
}

const demoLink = 'underline decoration-1 underline-offset-[0.15em]';

const pagerButton = `${ demoLink } cursor-pointer px-1 rounded-[2px] outline-offset-2 focus-visible:outline-2 focus-visible:outline-black`;

/** Headlines are not links because the demo articles have no address here. */
function LiteHomepage() {
	const [ page, setPage ] = useState( 0 );
	const navRef = useRef<HTMLDivElement>( null );
	const listRef = useRef<HTMLUListElement>( null );
	const [ listHeight, setListHeight ] = useState<number>();
	const moved = useRef( false );
	const last = DEMO_PAGES.length - 1;

	useEffect( () => {
		if ( ! moved.current ) {
			return;
		}
		moved.current = false;
		const nav = navRef.current;
		if ( nav && ! nav.contains( document.activeElement ) ) {
			nav.querySelector<HTMLElement>( '[aria-current="page"]' )?.focus();
		}
	}, [ page ] );

	const go = ( next: number ) => {
		moved.current = true;
		const height = listRef.current?.offsetHeight ?? 0;
		setListHeight( ( current ) => Math.max( current ?? 0, height ) );
		setPage( next );
	};

	return (
		<div className="bg-white px-4 py-8 font-serif text-[1.125rem] leading-[1.6] text-black [color-scheme:light]">
			<div className="mx-auto max-w-[39.5rem]">
				<p className="mb-2 text-base leading-normal">
					<span className={ demoLink }>View full site</span>
				</p>
				<p className="mb-8 text-[1.75rem] leading-[1.25] font-bold">The Riverbend Gazette</p>
				<div aria-hidden="true" className="my-8 border-[0.125rem] border-[#117644]" />
				<ul ref={ listRef } style={ listHeight ? { minHeight: listHeight } : undefined } className="my-4 list-none pl-0" aria-label={ `Headlines, page ${ page + 1 } of ${ DEMO_PAGES.length }` }>
					{ DEMO_PAGES[ page ].map( ( item ) => (
						<li key={ item.title } className="mb-4">
							{ item.sticky ? (
								<span className={ `my-[1em] block text-[1.17em] font-bold ${ demoLink }` }>{ item.title }</span>
							) : (
								<span className={ demoLink }>{ item.title }</span>
							) }
						</li>
					) ) }
				</ul>
				<div ref={ navRef } role="group" aria-label="Demo page links" className="mt-8 flex flex-wrap justify-center gap-2 text-base">
					{ page > 0 && (
						<button type="button" className={ pagerButton } onClick={ () => go( page - 1 ) }>
							« Previous
						</button>
					) }
					{ DEMO_PAGES.map( ( _, index ) =>
						index === page ? (
							<span key={ index } aria-current="page" tabIndex={ -1 } className="rounded-[2px] px-1 outline-offset-2 focus-visible:outline-2 focus-visible:outline-black">
								{ index + 1 }
							</span>
						) : (
							<button key={ index } type="button" className={ pagerButton } onClick={ () => go( index ) }>
								{ index + 1 }
							</button>
						)
					) }
					{ page < last && (
						<button type="button" className={ pagerButton } onClick={ () => go( page + 1 ) }>
							Next »
						</button>
					) }
				</div>
				<div aria-hidden="true" className="my-8 border-[0.125rem] border-[#117644]" />
				<div className="mt-8 space-y-4 text-base leading-normal">
					<p>The Riverbend Gazette, 118 Water Street, Riverbend. Newsroom line: (555) 555-0140.</p>
					<p>Riverbend&apos;s newsroom since 1897. Reader-funded, and nothing here sits behind a paywall.</p>
				</div>
			</div>
		</div>
	);
}

function DemoFrame() {
	return (
		<figure aria-labelledby="home-demo-label">
			<div className="overflow-hidden rounded-(--newspack-ui-radius-surface) border border-border shadow-md">
				<div className="flex flex-wrap items-center justify-between gap-x-(--newspack-ui-spacer-3) gap-y-(--newspack-ui-spacer-1) border-b border-border bg-(--newspack-ui-color-surface-subtle) px-(--newspack-ui-spacer-2) py-(--newspack-ui-spacer-1)">
					<span className="inline-flex items-center rounded-(--newspack-ui-radius-pill) border border-border bg-(--newspack-ui-color-surface) px-(--newspack-ui-spacer-3) py-(--newspack-ui-spacer-half) type-small text-foreground">
						<span className="sr-only">Address: </span>/lite/
					</span>
					<span id="home-demo-label" className="type-small text-muted-foreground">
						A demo newsroom, The Riverbend Gazette
					</span>
				</div>
				<LiteHomepage />
			</div>
			<figcaption className="mt-(--newspack-ui-spacer-2) max-w-[62ch] type-small text-pretty text-muted-foreground">
				The Lite Site homepage during a river flood. The sticky post is in bold. “Kettle River flood warning
				remains in effect” came in from the county’s RSS feed and is listed with the posts. Try the page links.
			</figcaption>
		</figure>
	);
}

const features: { title: string; href: string; body: string; labels: string[] }[] = [
	{
		title: 'One switch',
		href: '/docs/getting-started/turn-on-lite-site/',
		body: 'Lite Site is off until you check one box. Readers find it at /lite/, or at a path you choose, and your posts, pages and theme stay as they are.',
		labels: [ 'Enable Lite Site', 'Lite Site Path' ],
	},
	{
		title: 'Newest first',
		href: '/docs/guides/lite-homepage/',
		body: 'The homepage lists posts and RSS entries together. A sticky post shows in bold, and page links lead to older posts.',
		labels: [ 'Posts per Page' ],
	},
	{
		title: 'Images on request',
		href: '/docs/guides/lite-articles/',
		body: 'Each image waits as a box with its alt text and caption, until the reader asks for it.',
		labels: [ 'Load image' ],
	},
	{
		title: 'Headlines from RSS feeds',
		href: '/docs/guides/rss-feeds/',
		body: 'Import a feed from another site, such as a county alerts page, on a schedule. Each new item is published on the Lite Site.',
		labels: [ 'Add RSS Feed', 'Every 10 Minutes' ],
	},
	{
		title: 'Your color and font',
		href: '/docs/guides/appearance/',
		body: 'Pick a color for links and divider lines, and a font if you need one. Appearance is limited on purpose, to keep pages small.',
		labels: [ 'Primary Color', 'Body Font' ],
	},
	{
		title: 'Search stays on your main site',
		href: '/docs/guides/faq/',
		body: 'Lite Site pages ask search engines not to list them, and each article points them to its full version.',
		labels: [],
	},
];

const label = 'font-(weight:--newspack-ui-font-weight-medium) text-foreground';

const steps: { title: string; href: string; body: ReactNode }[] = [
	{
		title: 'Install the plugin',
		href: '/docs/getting-started/install-the-plugin/',
		body: 'Download the zip file, upload it in Plugins and activate it.',
	},
	{
		title: 'Turn on Lite Site',
		href: '/docs/getting-started/turn-on-lite-site/',
		body: (
			<>
				Check <strong className={ label }>Enable Lite Site</strong>, save, and open it as a reader would.
			</>
		),
	},
	{
		title: 'Add an RSS feed',
		href: '/docs/getting-started/add-an-rss-feed/',
		body: 'Optional. Bring in another site’s alerts, listed with your posts.',
	},
];

function Shot( { src, alt, caption }: { src: string; alt: string; caption: ReactNode } ) {
	return (
		<figure>
			<img
				src={ src }
				alt={ alt }
				width={ 3024 }
				height={ 1964 }
				loading="lazy"
				decoding="async"
				className="h-auto w-full rounded-(--newspack-ui-radius-surface) border border-border"
			/>
			<figcaption className="mt-(--newspack-ui-spacer-2) type-small text-pretty text-muted-foreground">{ caption }</figcaption>
		</figure>
	);
}

function Weight() {
	const rows = [
		{ label: 'Full version', value: 'about 3 MB', width: '100%' },
		{ label: 'Lite Site', value: 'under 50 KB', width: '1.7%' },
	];
	return (
		<figure className="mt-(--newspack-ui-spacer-3) max-w-[46ch]">
			<Card className="gap-(--newspack-ui-spacer-4) p-(--newspack-ui-spacer-5)">
				{ rows.map( ( row ) => (
					<div key={ row.label }>
						<div className="flex items-baseline justify-between gap-(--newspack-ui-spacer-2) type-small">
							<span className="text-muted-foreground">{ row.label }</span>
							<span className="font-(weight:--newspack-ui-font-weight-strong) text-foreground tabular-nums">
								{ row.value }
							</span>
						</div>
						<div
							aria-hidden="true"
							className="mt-(--newspack-ui-spacer-1) h-(--newspack-ui-spacer-2) rounded-(--newspack-ui-border-radius-s) bg-(--newspack-ui-color-surface-sunken)"
						>
							<div
								className="h-full min-w-1 rounded-(--newspack-ui-border-radius-s) bg-foreground"
								style={ { width: row.width } }
							/>
						</div>
					</div>
				) ) }
			</Card>
			<figcaption className="mt-(--newspack-ui-spacer-1) type-small text-muted-foreground">
				In our tests, a typical article fell from about 3 MB to under 50 KB. Your own pages will differ with the
				theme, the number of images and the scripts the site loads.
			</figcaption>
		</figure>
	);
}

export function Home( props: SitePageProps ) {
	return (
		<SiteLayout { ...props }>
			<div className="relative mb-[calc(var(--section-space)/2)]">
				<div aria-hidden="true" className="absolute inset-x-0 top-[38%] bottom-0 bg-linear-to-b from-transparent to-(--newspack-ui-color-surface-subtle)" />
				<section
					aria-labelledby="home-title"
					className="site-container relative grid gap-(--newspack-ui-spacer-8) pt-[calc(var(--section-space)/2)] lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] lg:items-start lg:gap-(--newspack-ui-spacer-11)"
				>
					<div className="lg:sticky lg:top-[calc(var(--site-header-height)+var(--section-space)/2)]">
						<h1 id="home-title" className="type-display text-foreground lg:-mt-[0.17em] lg:text-(length:--newspack-ui-font-size-4xl) lg:leading-(--newspack-ui-line-height-4xl)">
							Keep your news readable when connections fail
						</h1>
						<p className="mt-(--newspack-ui-spacer-3) max-w-[46ch] type-lead text-muted-foreground">{ LEAD }</p>
						<Weight />
						<div className="mt-(--newspack-ui-spacer-5)">
							<Actions />
						</div>
					</div>
					<DemoFrame />
				</section>
				<section
					aria-labelledby="home-partners"
					className="site-container relative flex flex-col gap-(--newspack-ui-spacer-5) pt-(--newspack-ui-spacer-9) pb-[calc(var(--section-space)/2)] lg:flex-row lg:items-end lg:justify-between lg:gap-(--newspack-ui-spacer-11)"
				>
					<div className="max-w-[34ch]">
						<h2 id="home-partners" className="type-small font-(weight:--newspack-ui-font-weight-medium) text-foreground">
							Part of{ ' ' }
							<a href={ EMERGENCY_MODE_URL } className="text-link">
								Emergency Mode for News
							</a>
						</h2>
						<p className="mt-(--newspack-ui-spacer-half) type-small text-pretty text-muted-foreground">A collaboration between OpenNews, Newspack, and NC Local, supported by Press Forward.</p>
						<a href="/about/" className="mt-(--newspack-ui-spacer-1) inline-flex min-h-6 items-center py-1 type-small text-link">
							Why we built it
						</a>
					</div>
					<ul className="grid grid-cols-3 items-center gap-x-(--newspack-ui-spacer-3) sm:flex sm:flex-wrap sm:gap-x-(--newspack-ui-spacer-8) sm:gap-y-(--newspack-ui-spacer-4) lg:gap-x-(--newspack-ui-spacer-11)">
						{ PARTNERS.map( ( partner ) => (
							<li key={ partner.url } className="min-w-0">
								<a
									href={ partner.url }
									aria-label={ partner.name }
									className="flex text-muted-foreground transition-colors duration-(--newspack-ui-duration-color) hover:text-foreground"
								>
									<svg aria-hidden="true" viewBox={ partner.viewBox } className={ `w-auto max-w-full ${ partner.className }` }>
										<path fill="currentColor" fillRule={ partner.evenOdd ? 'evenodd' : undefined } d={ partner.path } />
									</svg>
								</a>
							</li>
						) ) }
					</ul>
				</section>
			</div>

			<section aria-labelledby="home-article" className="site-container section-space">
				<div className="grid gap-(--newspack-ui-spacer-6) lg:grid-cols-3 lg:items-start lg:gap-(--newspack-ui-spacer-5)">
					<div>
						<h2 id="home-article" className="type-section text-foreground">
							Each article, as plain text
						</h2>
						<p className="mt-(--newspack-ui-spacer-2) max-w-[46ch] type-lead text-muted-foreground">
							The title, the byline, the date and the words. Nothing from the theme and no ads.
						</p>
						<p className="mt-(--newspack-ui-spacer-3) max-w-[46ch] text-pretty text-muted-foreground">
							Images wait as a box with their alt text and caption until a reader selects{ ' ' }
							<strong className={ label }>Load image</strong>. <strong className={ label }>View full version</strong>,
							above the title, opens the same article on the main site.
						</p>
						<a href="/docs/guides/lite-articles/" className="group mt-(--newspack-ui-spacer-3) inline-flex min-h-6 items-center gap-(--newspack-ui-spacer-half) py-1 type-small text-link">
							How Lite Site articles look
							<Arrow className={ arrowMotion } />
						</a>
					</div>
					<div className="grid gap-(--newspack-ui-spacer-5) md:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)] md:items-start lg:contents">
						<Shot
							src="/screenshots/home/lite-article-text.webp"
							alt="The Lite Site article “Kettle River flood: what’s closed and where to get help”: the title, the byline, the date and the opening paragraph."
							caption="On the Lite Site: the article as text, with links back to the homepage and to the full version."
						/>
						<Shot
							src="/screenshots/home/full-article.webp"
							alt="The same article on the demo site’s main site, with the theme’s header, menus, a large photo and the byline."
							caption="The same article on the full site, with its theme, menus and photos."
						/>
					</div>
				</div>
			</section>

			<section aria-labelledby="home-features" className="site-container section-space">
				<h2 id="home-features" className="type-section text-foreground">
					What the newsroom gets
				</h2>
				<p className="mt-(--newspack-ui-spacer-2) max-w-[60ch] type-lead text-muted-foreground">
					One plugin on the site you already run. The guides cover each part.
				</p>
				<ul className="mt-(--newspack-ui-spacer-6) divide-y divide-border">
					{ features.map( ( feature ) => (
						<li
							key={ feature.href + feature.title }
							className="grid gap-(--newspack-ui-spacer-2) py-(--newspack-ui-spacer-5) first:pt-0 last:pb-0 md:grid-cols-[minmax(0,16rem)_minmax(0,1fr)] md:gap-x-(--newspack-ui-spacer-8) lg:grid-cols-[minmax(0,21rem)_minmax(0,1fr)_minmax(0,16rem)]"
						>
							<h3 className="type-title text-foreground">
								<a href={ feature.href } className="group inline-flex items-baseline gap-(--newspack-ui-spacer-half) rounded-(--newspack-ui-border-radius-xs) text-link">
									{ feature.title }
									<Arrow className={ `self-center ${ arrowMotion }` } />
								</a>
							</h3>
							<p className="max-w-[62ch] text-pretty text-muted-foreground">{ feature.body }</p>
							<div className="flex flex-wrap content-start gap-(--newspack-ui-spacer-1) md:col-start-2 lg:col-start-auto">
								{ feature.labels.map( ( name ) => (
									<Badge key={ name } variant="secondary">
										{ name }
									</Badge>
								) ) }
							</div>
						</li>
					) ) }
				</ul>
			</section>

			<section aria-labelledby="home-start" className="site-container section-space">
				<h2 id="home-start" className="type-section text-foreground">
					Set it up before you need it
				</h2>
				<p className="mt-(--newspack-ui-spacer-2) max-w-[60ch] type-lead text-muted-foreground">
					Three steps, done in a calm week.
				</p>
				<ol className="mt-(--newspack-ui-spacer-6) grid gap-(--newspack-ui-spacer-6) md:grid-cols-3 md:gap-(--newspack-ui-spacer-5)">
					{ steps.map( ( step, index ) => (
						<li key={ step.href } className="border-t-2 border-foreground pt-(--newspack-ui-spacer-3)">
							<span className="type-small font-(weight:--newspack-ui-font-weight-medium) text-muted-foreground tabular-nums">
								Step { index + 1 }
							</span>
							<h3 className="mt-(--newspack-ui-spacer-1) type-title text-foreground">{ step.title }</h3>
							<p className="mt-(--newspack-ui-spacer-1) max-w-[38ch] text-pretty text-muted-foreground">{ step.body }</p>
							<a href={ step.href } className="mt-(--newspack-ui-spacer-2) inline-flex min-h-6 items-center py-1 type-small text-link">
								Read the step<span className="sr-only">: { step.title }</span>
							</a>
						</li>
					) ) }
				</ol>
				<div className="mt-(--newspack-ui-spacer-8)">
					<Actions />
				</div>
			</section>
		</SiteLayout>
	);
}
