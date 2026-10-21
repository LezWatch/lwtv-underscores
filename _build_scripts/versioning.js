/*
 * This script updates the version numbers of the theme and plugins in functions.php
 */

const { cp } = require( 'fs/promises' );
var fs = require( 'fs' );

console.log( 'Updating version numbers...' );

// Set the current working directory to the root of the project
process.chdir( __dirname + '/../' );
const root = process.cwd();

// Get the version from package.json
const themePackage = require('../package.json');
const dynamicThemeVersion = themePackage.version;

async function updateVersionNumbers() {

	// Update version numbers of libraries in functions.php
	const functionsPhp = await fs.promises.readFile( root + '/functions.php', 'utf8');
	const currentThemeVersion = functionsPhp.match(/\$versions\['lwtv-underscores'\]\s*=\s*'(\d+\.\d+\.\d+)';/)[1];
	const currentBootstrapVersion = functionsPhp.match(/\$versions\['bootstrap'\]\s*=\s*'([\w.-]+)';/)[1];
	const currentLwtvBlocksVersion = functionsPhp.match(/\$versions\['lwtv-blocks'\]\s*=\s*'(\d+\.\d+\.\d+)';/)[1];

	// Replace Theme Version if needed
	if ( currentThemeVersion !== dynamicThemeVersion ) {
		const updateThemeVersion = functionsPhp.replace(
			/\$versions\['lwtv-underscores'\]\s*=\s*'(\d+\.\d+\.\d+)';/,
			`$versions['lwtv-underscores'] = '${dynamicThemeVersion}';`
		);
		await fs.promises.writeFile( root + '/functions.php', updateThemeVersion );
		console.log( 'Theme version updated in functions.php!' );
	}

	// Bootstrap Version (from the installed npm package; may be a prerelease like 6.0.0-alpha.1)
	const boostrapCSSVersion = require( root + '/node_modules/bootstrap/package.json' ).version;

	if ( currentBootstrapVersion !== boostrapCSSVersion ) {
		// Re-read so we don't clobber the theme version written above.
		const latestFunctionsPhp = await fs.promises.readFile( root + '/functions.php', 'utf8');
		const updateBootstrapVersion = latestFunctionsPhp.replace(
			/\$versions\['bootstrap'\]\s*=\s*'([\w.-]+)';/,
			`$versions['bootstrap'] = '${boostrapCSSVersion}';`
		);
		await fs.promises.writeFile( root + '/functions.php', updateBootstrapVersion );
		console.log( 'Bootstrap version updated in functions.php!' );
	}

	// LWTV Blocks Version
	const lwtvBlocksFile = require( root + '/plugins/lwtv-plugin/php/blocks/package.json');
	const lwtvBlocksVersion = lwtvBlocksFile.version;

	if ( currentLwtvBlocksVersion !== lwtvBlocksVersion ) {
		// Re-read so we don't clobber the theme/Bootstrap versions written above.
		const latestFunctionsPhp = await fs.promises.readFile( root + '/functions.php', 'utf8');
		const updateLwtvBlocksVersion = latestFunctionsPhp.replace(
			/\$versions\['lwtv-blocks'\]\s*=\s*'(\d+\.\d+\.\d+)';/,
			`$versions['lwtv-blocks'] = '${lwtvBlocksVersion}';`
		);
		await fs.promises.writeFile( root + '/functions.php', updateLwtvBlocksVersion );
		console.log( 'LWTV Blocks version updated in functions.php!' );
	}

	console.log( 'Version numbers updated!' );
}

// Exported so postbuild.js can await it and report failures.
module.exports = updateVersionNumbers();
