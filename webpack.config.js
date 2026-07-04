/**
 * Extends the default @wordpress/scripts webpack config to build the
 * workflow builder app from assets/builder/src to assets/builder/build,
 * instead of the default ./src -> ./build (this plugin's repo root already
 * uses src/ for the PHP PSR-4 autoload root).
 *
 * package.json intentionally pins @wordpress/scripts to ^27.x. Starting
 * with v28, wp-scripts switched to the automatic JSX runtime, which adds a
 * hard build dependency on the `react-jsx-runtime` script handle — only
 * registered by WordPress core since 6.6. This plugin declares
 * "Requires at least: 5.8", so staying on the classic pragma-based
 * transform (which only needs the long-standing `wp-element` handle) keeps
 * the builder working on every WordPress version this plugin supports. If
 * the plugin's minimum WordPress version is ever raised to 6.6+, wp-scripts
 * can be upgraded to latest.
 */
const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve(__dirname, 'assets/builder/src', 'index.js'),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(__dirname, 'assets/builder/build'),
	},
};
