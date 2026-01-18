/**
 * WordPress Scripts Configuration
 *
 * Custom webpack config to handle multiple entry points.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		// Main blocks bundle.
		index: path.resolve( __dirname, 'src', 'index.js' ),
		// Countdown view script (frontend).
		'countdown-view': path.resolve( __dirname, 'src', 'blocks', 'countdown-timer', 'view.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
