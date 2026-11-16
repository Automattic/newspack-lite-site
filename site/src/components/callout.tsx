import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { ErrorIcon, InfoIcon, SuccessIcon, WarningIcon } from '@/lib/newspack-ui-icons';

const VARIANTS = {
	info: { variant: 'info', Icon: InfoIcon },
	tip: { variant: 'info', Icon: InfoIcon },
	idea: { variant: 'info', Icon: InfoIcon },
	success: { variant: 'success', Icon: SuccessIcon },
	warn: { variant: 'warning', Icon: WarningIcon },
	warning: { variant: 'warning', Icon: WarningIcon },
	error: { variant: 'error', Icon: ErrorIcon },
} as const;

export function Callout( {
	type = 'info',
	title,
	children,
}: {
	type?: keyof typeof VARIANTS;
	title?: ReactNode;
	children?: ReactNode;
} ) {
	const { variant, Icon } = VARIANTS[ type ] ?? VARIANTS.info;
	return (
		<Alert role="note" variant={ variant } className="not-prose my-(--newspack-ui-spacer-5)">
			<Icon aria-hidden="true" />
			{ title && <AlertTitle>{ title }</AlertTitle> }
			<AlertDescription className="[&_a]:text-link [&_p:not(:last-child)]:mb-(--newspack-ui-spacer-2) [&_strong]:font-(weight:--newspack-ui-font-weight-strong)">
				{ children }
			</AlertDescription>
		</Alert>
	);
}
