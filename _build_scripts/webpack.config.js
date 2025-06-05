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
const themePackage = require('../package.json');
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");

/**
 * Dynamic Variables for SASS
 */
const dynamicThemeVersion = themePackage.version;

const spriteUrls = {
	local: 'https://lwtv.local/wp-content/uploads/lezpress-icons/sprite.css.svg',
	staging: 'https://lezwatchtvcom.stage.site/wp-content/uploads/lezpress-icons/sprite.css.svg',
	production: 'https://lezwatchtv.com/wp-content/uploads/lezpress-icons/sprite.css.svg',
};

const spriteEnv = process.env.SVG_SPRITE_ENV || 'production';
const spriteUrl = spriteUrls[spriteEnv] || spriteUrls.production;

// This string will be prepended to all SCSS files
const sassAdditionalData = `
  $pkg-version: "${dynamicThemeVersion}";
  $svg-sprite-url: "${spriteUrl}";
`;

// Add any new entry points by extending the webpack config.
module.exports = {
	...defaultConfig,
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules,
			{
				test: /\.css$/,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					'sass-loader',
					{
						loader: 'sass-loader',
						options: {
							additionalData: `$svg-sprite-url: "${spriteUrl}";`,
						},
					},
				],
			},
		],
	},
	...{
		entry: {
			// 'destination': [ 'source1', 'source2', ... ]
			'js/yikes-theme-scripts.min': [
				'./inc/js/navigation.js',
				'./inc/js/skip-link-focus-fix.js',
				'./inc/js/lwtv-theme-scripts.js',
				'./inc/js/a11y.js',
				'./inc/js/searchbox.js'
			],
			'js/customizer.min': [ './inc/js/customizer.js' ],
			'js/bootstrap-color-mode.min': [ './inc/js/bootstrap-color-mode.js' ],
			'css/style': './style.scss',
			'css/style-editor': './style-editor.scss',
		},
		output: {
			path: path.join( __dirname, 'webpackdist' ),
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
