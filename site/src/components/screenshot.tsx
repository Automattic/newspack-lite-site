const ASPECTS = {
	'16/10': 'aspect-[16/10]',
	'4/3': 'aspect-[4/3]',
	'390/844': 'aspect-[390/844] max-w-xs',
} as const;

export function Screenshot( {
	label,
	src,
	width,
	height,
	aspect = '16/10',
}: {
	label: string;
	src?: string;
	width?: number;
	height?: number;
	aspect?: keyof typeof ASPECTS;
} ) {
	if ( src ) {
		return (
			<figure className="not-prose my-(--newspack-ui-spacer-5)">
				<img
					src={ src }
					alt={ label }
					width={ width }
					height={ height }
					loading="lazy"
					decoding="async"
					className="h-auto w-full rounded-(--newspack-ui-radius-surface) border border-border"
				/>
			</figure>
		);
	}

	return (
		<figure
			role="img"
			aria-label={ `Screenshot placeholder: ${ label }` }
			className={ `not-prose my-(--newspack-ui-spacer-5) flex w-full items-center justify-center rounded-(--newspack-ui-radius-surface) border border-dashed border-border bg-(--newspack-ui-color-surface-subtle) p-(--newspack-ui-spacer-5) text-center type-small text-muted-foreground ${ ASPECTS[ aspect ] }` }
		>
			<span aria-hidden="true">Screenshot: { label }</span>
		</figure>
	);
}
