/*
 * This script runs all the various node post install things we need for the plugin stuff.
 * Copies files into place
 */

const { cp } = require( '@npmcli/fs' );

// Move JS
(async () => {
	await cp('node_modules/chart.js/dist/chart.umd.js', 'inc/js/chart.min.js');
	await cp('node_modules/chart.js/dist/chart.umd.js.map', 'inc/js/chart.min.js.map');
	await cp('node_modules/chartjs-plugin-annotation/dist/chartjs-plugin-annotation.min.js', 'inc/js/chartjs-plugin-annotation.min.js');
	await cp('node_modules/tablesorter/dist/js/jquery.tablesorter.min.js', 'inc/js/jquery.tablesorter.min.js');
	console.log('JS files have been moved...');
})();

// Move CSS
(async () => {
	await cp('node_modules/tablesorter/dist/css/theme.bootstrap_4.min.css', 'inc/css/theme.bootstrap_4.min.css');
	console.log('CSS files have been moved...');
})();
