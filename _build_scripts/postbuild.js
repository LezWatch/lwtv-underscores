/*
 * This script runs all the various node post install things we need for the plugin stuff.
 * Copies files into place
 */

const { cp } = require( 'fs/promises' );
var fs = require( 'fs' );

console.log( 'Building and merging assets ...' );

// Set the current working directory to the root of the project
process.chdir( __dirname + '/../' );
const root = process.cwd();

// Get the version from package.json
const themePackage = require('../package.json');
const dynamicThemeVersion = themePackage.version;

(async () => {
	// JS Files - Theme
	await cp( root + '/_build_scripts/webpackdist/js/yikes-theme-scripts.min.js', root + '/inc/js/yikes-theme-scripts.min.js');
	await cp( root + '/_build_scripts/webpackdist/js/bootstrap-color-mode.min.js', root + '/inc/js/bootstrap-color-mode.min.js');
	await cp( root + '/_build_scripts/webpackdist/js/customizer.min.js', root + '/inc/js/customizer.min.js');

	// JS Files - Plugins
	await cp( root + '/node_modules/chart.js/dist/chart.umd.js', root + '/plugins/lwtv-plugin/assets/js/chart.min.js');
	await cp( root + '/node_modules/chart.js/dist/chart.umd.js.map', root + '/plugins/lwtv-plugin/assets/js/chart.min.js.map');
	await cp( root + '/node_modules/chartjs-plugin-annotation/dist/chartjs-plugin-annotation.min.js', root + '/plugins/lwtv-plugin/assets/js/chartjs-plugin-annotation.min.js');
	await cp( root + '/node_modules/tablesorter/dist/js/jquery.tablesorter.min.js', root + '/plugins/lwtv-plugin/assets/js/jquery.tablesorter.min.js');
	console.log('JS files have been moved!');

	// CSS Files
	console.log( 'Combining CSS files for style.css...' );
	fs.writeFileSync( root + '/style.css', '' );

	async function combineStyleCSS() {
		try {
			const header = await fs.promises.readFile( root + '/scss/_header.scss', 'utf8');

			// Replace the version in const header with the dynamic version
			const updatedHeader = header.replace( 'THEME_VERSION', dynamicThemeVersion );

			const content = await fs.promises.readFile( root + '/_build_scripts/webpackdist/css/style.min.css', 'utf8');

			const combinedCSS = updatedHeader + content;

			await fs.promises.writeFile( root + '/style.css', combinedCSS );
			console.log( 'style.css combined successfully!' );
		} catch (err) {
			console.error( 'Error combining files for style.css:', err );
		}
	}
	combineStyleCSS();

	await cp( root + '/_build_scripts/webpackdist/css/style.min.css', root + '/style.min.css');
	await cp( root + '/_build_scripts/webpackdist/css/style-editor.min.css', root + '/style-editor.min.css' );
	await cp( root + '/_build_scripts/webpackdist/css/style-editor.min.css', root + '/style-editor.css' );

	// CSS Plugins
	await cp( root + '/node_modules/tablesorter/dist/css/theme.bootstrap_4.min.css', root + '/plugins/lwtv-plugin/assets/css/theme.bootstrap.min.css');
	console.log('CSS files have been moved!');

	// Call the versioning script
	require( './versioning' );
})();
