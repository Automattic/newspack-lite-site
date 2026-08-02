module.exports = {
	extends: [ './node_modules/newspack-scripts/.eslintrc.js' ],
	ignorePatterns: [ '*/dist/', '*/node_modules/', '*/release' ],
	rules: {
		'@wordpress/i18n-no-flanking-whitespace': 'off',
	},
};
