/*
 * This script runs all the various node post install things we need for the plugin stuff.
 * Copies files into place
 */

const { cp } = require( '@npmcli/fs' );

console.log('Building and merging JS and CSS...');

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
	console.log('JS files have been moved.');

	// CSS Themes
	await cp('inc/dist/css/style.min.css', 'style.min.css');
	await cp('inc/dist/css/style-editor.min.css', 'style-editor.min.css');

	// CSS Plugins
	await cp('node_modules/tablesorter/dist/css/theme.bootstrap_4.min.css', 'plugins/lwtv-plugin/assets/css/theme.bootstrap_4.min.css');
	console.log('CSS files have been moved.');
})();
