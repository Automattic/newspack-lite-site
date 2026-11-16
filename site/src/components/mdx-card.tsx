import type { ReactNode } from 'react';
import { Card as UiCard, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function Cards( { className, children }: { className?: string; children?: ReactNode } ) {
	return (
		<div className={ cn( 'not-prose my-(--newspack-ui-spacer-5) grid gap-(--newspack-ui-spacer-2) sm:grid-cols-2', className ) }>{ children }</div>
	);
}

export function Card( {
	title,
	description,
	href,
	children,
}: {
	title: ReactNode;
	description?: ReactNode;
	href?: string;
	children?: ReactNode;
} ) {
	const card = (
		<UiCard className="h-full transition-colors duration-(--newspack-ui-duration-color) group-hover:border-(color:--newspack-ui-color-border-hover)">
			<CardHeader>
				<CardTitle>
					<h3 className="m-0 [font:inherit] [letter-spacing:inherit]">{ title }</h3>
				</CardTitle>
				{ description && <CardDescription>{ description }</CardDescription> }
			</CardHeader>
			{ children && <CardContent className="type-small text-muted-foreground">{ children }</CardContent> }
		</UiCard>
	);

	if ( ! href ) {
		return card;
	}

	return (
		<a href={ href } className="group block h-full rounded-(--newspack-ui-radius-surface) text-foreground no-underline">
			{ card }
		</a>
	);
}
