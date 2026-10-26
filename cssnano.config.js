/**
 * cssnano config, used by both minification passes: @wordpress/scripts'
 * postcss-loader (which skips its own fallback config when this file exists)
 * and css-minimizer-webpack-plugin.
 *
 * - discardComments.removeAll matches the @wordpress/scripts fallback.
 * - calc: false turns off postcss-calc, which cannot parse CSS relative color
 *   syntax such as `oklch(from var(--x) calc(l * 0.9) c h)` (used by the
 *   Bootstrap 5 compatibility shim) and warned on every occurrence. The output
 *   was already correct; only calc() simplification is skipped.
 */
module.exports = {
	preset: [
		'default',
		{
			discardComments: {
				removeAll: true,
			},
			calc: false,
		},
	],
};
