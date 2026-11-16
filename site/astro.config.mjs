// @ts-check
import { defineConfig } from 'astro/config';
import react from '@astrojs/react';
import mdx from '@astrojs/mdx';
import tailwindcss from '@tailwindcss/vite';
import { unified } from '@astrojs/markdown-remark';
import {
	rehypeCode,
	remarkCodeTab,
	remarkHeading,
	remarkNpm,
	remarkStructure,
} from 'fumadocs-core/mdx-plugins';

const remarkPlugins = [
	remarkHeading,
	remarkCodeTab,
	remarkNpm,
	[ remarkStructure, { exportAs: 'structuredData' } ],
];
const rehypePlugins = [ rehypeCode ];

export default defineConfig( {
	site: 'https://lite-site.space.fast',
	markdown: {
		processor: unified( {
			syntaxHighlight: false,
			remarkPlugins,
			rehypePlugins,
		} ),
	},
	integrations: [
		react(),
		mdx( {
			extendMarkdownConfig: true,
			syntaxHighlight: false,
		} ),
	],
	vite: {
		plugins: [ tailwindcss() ],
	},
} );
