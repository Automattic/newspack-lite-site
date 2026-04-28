const getWebpackConfig = require( 'newspack-scripts/config/getWebpackConfig' );

module.exports = getWebpackConfig( {
	entry: {
		settings: './assets/js/settings.js',
		'settings-style': './assets/scss/settings.scss',
	},
} );
