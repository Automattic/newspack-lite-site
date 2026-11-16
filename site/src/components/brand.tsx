import { SiteFooter } from '@/components/site/site-footer';
import { SITE_NAME } from '@/lib/site';
import { FeatherIcon } from './feather-icon';

/** The header wordmark: the feather and the site name. */
export function Wordmark() {
	return (
		<span className="inline-flex items-center gap-(--newspack-ui-spacer-half)">
			<FeatherIcon className="-ms-1 size-7 text-brand" />
			{ SITE_NAME }
		</span>
	);
}

/** The site footer with the credit line shared by every page. */
export function Footer() {
	return (
		<SiteFooter>
			<a
				href="/"
				className="inline-flex items-center gap-(--newspack-ui-spacer-half) rounded-(--newspack-ui-radius-control) text-(length:--newspack-ui-font-size-body) font-(weight:--newspack-ui-font-weight-strong) text-foreground"
			>
				<FeatherIcon className="-ms-1 size-7 text-brand" />
				{ SITE_NAME }
			</a>
			<span className="mt-(--newspack-ui-spacer-2) block max-w-[70ch]">
				{ SITE_NAME } is built by{ ' ' }
				<a href="https://newspack.com/" className="text-link">
					Newspack
				</a>{ ' ' }
				as part of{ ' ' }
				<a href="https://emergencymode.news/" className="text-link">
					Emergency Mode for News
				</a>
				, a program supported by{ ' ' }
				<a href="https://www.pressforward.news/" className="text-link">
					Press Forward
				</a>
				’s local news infrastructure initiative.
			</span>
		</SiteFooter>
	);
}
