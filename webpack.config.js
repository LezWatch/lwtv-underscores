/**
 * External dependencies
 */
const path = require( 'path' );

/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * Internal dependencies
 */
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");

// Add any new entry points by extending the webpack config.
module.exports = {
	...defaultConfig,
	...{
		entry: {
			// 'destination': [ 'source1', 'source2', ... ]
			'/js/yikes-theme-scripts.min': [ './inc/js/navigation.js', './inc/js/skip-link-focus-fix.js' , './inc/js/lwtv-theme-scripts.js' , './inc/js/a11y.js', './inc/js/searchbox.js' ],
			'/js/customizer.min': [ './inc/js/customizer.js' ],
			'/js/bootstrap-color-mode.min': [ './inc/js/bootstrap-color-mode.js' ],
			'css/style': './style.scss',
			'css/style-editor': './style-editor.scss',
		},
		output: {
			path: path.join( __dirname, 'inc/dist' ),
		},
		optimization: {
			minimize: true,
			minimizer: [ new TerserPlugin(), new CssMinimizerPlugin() ],
		},
		plugins: [
			...defaultConfig.plugins,
			new MiniCssExtractPlugin({
				filename: '[name].min.css'
			})
		]
	}
};
