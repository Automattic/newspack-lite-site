import type { ComponentProps, CSSProperties, ReactNode } from 'react';
import type { Root } from 'fumadocs-core/page-tree';
import { DocsLayout } from 'fumadocs-ui/layouts/notebook';
import { DocsPage, type DocsPageProps } from 'fumadocs-ui/layouts/notebook/page';
import { useSidebar } from 'fumadocs-ui/layouts/notebook/slots/sidebar';
import { SkipLink } from '@/components/site/skip-link';
import type { ThemeToggleHandler } from '@/components/site/theme-toggle';
import { cn } from '@/lib/utils';
import { DocsSiteHeader } from './docs-header';
import { DrawerA11y } from './drawer-a11y';
import { DrawerSearch } from './drawer-search';
import { DRAWER_SIDEBAR_SLOT, DrawerClose } from './drawer-sidebar';
import { PAGE_SLOTS } from './page-chrome';

function SidebarBanner( { className, children, ...props }: ComponentProps< 'div' > ) {
	const { mode, open, setOpen } = useSidebar();
	return (
		<div { ...props } className={ cn( 'flex flex-col gap-3 p-4 pb-2 empty:hidden', className ) }>
			{ mode === 'drawer' ? (
				<>
					<DrawerClose />
					<DrawerSearch open={ open } onNavigate={ () => setOpen( false ) } />
				</>
			) : (
				children
			) }
		</div>
	);
}

/**
 * The Fumadocs notebook layout with the site header, the right-hand docs drawer (site links,
 * docs tree, inline search) and the page chrome. Render it inside a Fumadocs `RootProvider`
 * whose `search` option is `{ SearchDialog }` from `./search-dialog`. `onThemeToggle` works as on
 * `SiteHeader`.
 */
export function DocsShell( {
	tree,
	pathname,
	page,
	wordmark,
	onThemeToggle,
	children,
}: {
	tree: Root;
	pathname: string;
	page?: DocsPageProps;
	wordmark?: ReactNode;
	onThemeToggle?: ThemeToggleHandler;
	children: ReactNode;
} ) {
	return (
		<>
			<SkipLink href="#nd-page" />
			<DocsLayout
				tree={ tree }
				themeSwitch={ { enabled: false } }
				slots={ { sidebar: DRAWER_SIDEBAR_SLOT } }
				nav={ { component: <DocsSiteHeader docs pathname={ pathname } wordmark={ wordmark } onThemeToggle={ onThemeToggle } />, mode: 'top' } }
				sidebar={ { collapsible: false, 'aria-label': 'Documentation', banner: SidebarBanner } }
				containerProps={ { style: { '--fd-header-height': 'var(--site-header-height)' } as CSSProperties } }
			>
				<DrawerA11y />
				<DocsPage { ...page } slots={ PAGE_SLOTS }>
					{ children }
				</DocsPage>
			</DocsLayout>
		</>
	);
}
