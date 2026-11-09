const getWebpackConfig = require( 'newspack-scripts/config/getWebpackConfig' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const FORCE_BUNDLE = new Set( [ '@wordpress/theme', '@wordpress/style-runtime', '@wordpress/react-inert-value', '@wordpress/kebab-case' ] );

const config = getWebpackConfig( {
	entry: {
		index: './src/app/index.tsx',
	},
} );

config.module.rules.push( {
	test: /\.js$/,
	include: /node_modules[\\/]newspack-icons[\\/]/,
	use: {
		loader: 'babel-loader',
		options: {
			presets: [ '@babel/preset-react' ],
		},
	},
} );

config.plugins = config.plugins
	.filter( plugin => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin' )
	.concat(
		new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				return FORCE_BUNDLE.has( request ) ? null : undefined;
			},
		} )
	);

module.exports = config;
