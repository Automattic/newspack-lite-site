const getWebpackConfig = require( 'newspack-scripts/config/getWebpackConfig' );

module.exports = getWebpackConfig( {
	entry: {
		index: './assets/js/index.js',
		'settings-style': './assets/scss/index.scss',
	},
} );
