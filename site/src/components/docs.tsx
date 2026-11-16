import type { ComponentProps } from 'react';
import { AstroDocs } from '@/components/docs/astro-docs';
import { Wordmark } from './brand';

/** The docs page island: the kit's `AstroDocs` with the site's wordmark. */
export function Docs( props: Omit< ComponentProps< typeof AstroDocs >, 'wordmark' > ) {
	return <AstroDocs { ...props } wordmark={ <Wordmark /> } />;
}
