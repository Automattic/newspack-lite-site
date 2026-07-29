const getWebpackConfig = require( 'newspack-scripts/config/getWebpackConfig' );

module.exports = getWebpackConfig( {
	entry: {
		index: './src/app/index.tsx',
	},
} );
