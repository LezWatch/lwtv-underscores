/*
 * This script runs all the various node post install things we need for the plugin stuff.
 * Copies files into place
 */

const { cp } = require( '@npmcli/fs' );
var fs = require( 'fs' );

console.log('Building and merging assets ...');

(async () => {
	// JS Themes
	await cp('inc/dist/js/yikes-theme-scripts.min.js', 'inc/js/yikes-theme-scripts.min.js');
	await cp('inc/dist/js/bootstrap-color-mode.min.js', 'inc/js/bootstrap-color-mode.min.js');
	await cp('inc/dist/js/customizer.min.js', 'inc/js/customizer.min.js');

	// JS Plugins
	await cp('node_modules/chart.js/dist/chart.umd.js', 'plugins/lwtv-plugin/assets/js/chart.min.js');
	await cp('node_modules/chart.js/dist/chart.umd.js.map', 'plugins/lwtv-plugin/assets/js/chart.min.js.map');
	await cp('node_modules/chartjs-plugin-annotation/dist/chartjs-plugin-annotation.min.js', 'plugins/lwtv-plugin/assets/js/chartjs-plugin-annotation.min.js');
	await cp('node_modules/tablesorter/dist/js/jquery.tablesorter.min.js', 'plugins/lwtv-plugin/assets/js/jquery.tablesorter.min.js');
	console.log('JS files have been moved!');

	// CSS Themes
	console.log( 'Combining CSS files for style.css...' );
	fs.writeFileSync( 'style.css', '' );

	async function combineStyleCSS() {
		try {
			const header = await fs.promises.readFile( 'scss/_header.scss', 'utf8');
			const content = await fs.promises.readFile( 'inc/dist/css/style.min.css', 'utf8');

			const combinedCSS = header + content;

			await fs.promises.writeFile( 'style.css', combinedCSS );
			console.log( 'style.css combined successfully!' );
		} catch (err) {
			console.error( 'Error combining files for style.css:', err );
		}
	}
	combineStyleCSS();

	await cp( 'inc/dist/css/style.min.css', 'style.min.css');
	await cp( 'inc/dist/css/style-editor.min.css', 'style-editor.min.css' );
	await cp( 'inc/dist/css/style-editor.css', 'style-editor.css' );

	// CSS Plugins
	await cp('node_modules/tablesorter/dist/css/theme.bootstrap_4.min.css', 'plugins/lwtv-plugin/assets/css/theme.bootstrap_4.min.css');
	console.log('CSS files have been moved!');
})();
