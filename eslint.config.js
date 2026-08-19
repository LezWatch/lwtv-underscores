/**
 * ESLint flat config.
 *
 * Extends the default config that ships with @wordpress/scripts and adds the
 * project's exclusions, so `lint:js` and `fix:js` can share one glob without
 * either of them reaching into vendored or generated JavaScript. Mirrors
 * .stylelintignore and the exclusions in phpcs.xml.dist.
 */
const globals = require( 'globals' );
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	{
		ignores: [
			'node_modules/**',
			'vendor/**',
			'_build_scripts/**',
			'inc/dist/**',
			'inc/bootstrap/**',
			'plugins/lwtv-plugin/php/blocks/build/**',

			// Minified output is never edited by hand.
			'**/*.min.js',

			// Bundled third-party libraries.
			'plugins/lwtv-plugin/assets/js/jquery.tablesorter.js',
		],
	},
	...defaultConfig,

	{
		// Theme and plugin scripts are plain browser files loaded via
		// wp_enqueue_script, not ES modules, and they lean on globals provided
		// by other enqueued libraries.
		files: [ 'inc/js/**/*.js', 'plugins/lwtv-plugin/assets/js/**/*.js' ],
		languageOptions: {
			globals: {
				...globals.browser,
				jQuery: 'readonly',
				bootstrap: 'readonly',
				// FacetWP, on archive pages that use facets.
				FWP: 'readonly',
			},
		},
	},
];
