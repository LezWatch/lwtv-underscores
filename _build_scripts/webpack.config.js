/**
 * External dependencies
 */
const path = require('path');

/**
 * WordPress dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

/**
 * Internal dependencies / Project-specific
 */
const themePackage = require('../package.json'); // Reads package.json from the theme root
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");

// --- Dynamic Variables for SASS ---
const dynamicThemeVersion = themePackage.version;

const spriteUrls = {
	local: 'https://lwtv.local/wp-content/themes/lwtv-underscores/symbolicons/sprite.css.svg',
	staging: 'https://lezwatchtvcom.stage.site/wp-content/themes/lwtv-underscores/symbolicons/sprite.css.svg',
	production: 'https://lezwatchtv.com/wp-content/themes/lwtv-underscores/symbolicons/sprite.css.svg',
};
const spriteEnv = process.env.SVG_SPRITE_ENV || 'production';
const spriteUrl = spriteUrls[spriteEnv] || spriteUrls.production;

const sassAdditionalData = `
$pkg-version: "${dynamicThemeVersion}";
$svg-sprite-url: "${spriteUrl}?v=${dynamicThemeVersion}";
`;

// Write the dynamic variables to a file (erase the file if it exists)
const fs = require('fs');

if (fs.existsSync(path.join(__dirname, '../scss/_dynamic.scss'))) {
	fs.unlinkSync(path.join(__dirname, '../scss/_dynamic.scss'));
}
fs.writeFileSync(path.join(__dirname, '../scss/_dynamic.scss'), sassAdditionalData);
// --- End Dynamic Variables ---

// --- Plugin Configuration ---
const basePlugins = defaultConfig.plugins.filter(
	plugin => plugin.constructor.name !== 'MiniCssExtractPlugin'
);
const finalPlugins = [
	...basePlugins,
	new MiniCssExtractPlugin({
		filename: '[name].min.css'
	})
];
// --- End Plugin Configuration ---

module.exports = {
	...defaultConfig,

	module: {
		...defaultConfig.module,
		rules: defaultConfig.module.rules.map(rule => {
			if (rule.test && (rule.test.toString().includes('s[ac]ss') || rule.test.toString().includes('scss'))) {
				if (Array.isArray(rule.use)) {
					return {
						...rule,
						use: rule.use.map(loaderConfig => {
							const loaderName = typeof loaderConfig === 'string' ? loaderConfig : loaderConfig.loader;

							if (loaderName && loaderName.includes('sass-loader')) {
								const existingOptions = (typeof loaderConfig === 'object' ? loaderConfig.options : {});
								return {
									...(typeof loaderConfig === 'string' ? { loader: loaderConfig } : loaderConfig),
									options: {
										...existingOptions,
										additionalData: (content, loaderContext) => {
											let prependedData = sassAdditionalData;
											if (typeof existingOptions.additionalData === 'function') {
												prependedData += existingOptions.additionalData(content, loaderContext);
											} else if (typeof existingOptions.additionalData === 'string') {
												prependedData += existingOptions.additionalData;
											}
											return prependedData;
										},
									},
								};
							}
							return loaderConfig;
						}),
					};
				}
			}
			return rule;
		}),
	},

	entry: {
		'js/yikes-theme-scripts.min': [
			'./inc/js/navigation.js',
			'./inc/js/skip-link-focus-fix.js',
			'./inc/js/lwtv-theme-scripts.js',
			'./inc/js/a11y.js',
			'./inc/js/searchbox.js'
		],
		'js/customizer.min': ['./inc/js/customizer.js'],
		'js/bootstrap-color-mode.min': ['./inc/js/bootstrap-color-mode.js'],
		'css/style': './style.scss',
		'css/style-editor': './style-editor.scss',
	},

	output: {
		path: path.join(__dirname, 'webpackdist'),
	},

	optimization: {
		minimize: true,
		minimizer: [new TerserPlugin(), new CssMinimizerPlugin()],
	},

	performance: { hints: false },

	plugins: finalPlugins,
};
