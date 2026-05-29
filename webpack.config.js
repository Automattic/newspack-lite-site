const getWebpackConfig = require( 'newspack-scripts/config/getWebpackConfig' );

module.exports = getWebpackConfig( {
	entry: {
		index: './src/settings/index.tsx',
		'admin-header': './src/admin-header/index.tsx',
		style: './src/style.scss',
		'rss-feed-import': './src/rss-feed-import/index.tsx',
	},
} );
