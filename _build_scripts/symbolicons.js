/*
 * This script grabs the symbolicons from the private repo and puts them in the right place.
 */

// Check the variable passed in from the command line
const branch = process.env.LWTV_BRANCH;

const { simpleGit } = require('simple-git');
const fs        = require('fs');
const path      = require('path');
const { execFileSync } = require('child_process');

// The generator emits space indents (width varies per file) and quoted url()s;
// convert to tabs and let stylelint fix the rest so the theme copies match what we commit.
const copyScss = ( from, to ) => {
	const scss    = fs.readFileSync( from, 'utf8' );
	const indents = ( scss.match( /^ +/gm ) || [] ).map( ( indent ) => indent.length );
	const width   = indents.length ? Math.min( ...indents ) : 1;
	fs.writeFileSync( to, scss.replace( /^ +/gm, ( indent ) => '\t'.repeat( Math.round( indent.length / width ) ) ) );
};

(async () => {
	const repoPath = path.join( __dirname, 'tmp-icons' );
	if (!fs.existsSync(repoPath)) {
		await simpleGit().clone('https://github.com/LezWatch/symbolicons-private', repoPath, ['--branch', branch]);
	} else {
		// tmp-icons is a disposable cache: discard any local changes so they can't block the checkout.
		await simpleGit(repoPath).reset(['--hard']);
		await simpleGit(repoPath).clean('f', ['-d']);
		await simpleGit(repoPath).checkout(branch);
	}

	// Git Pull to get the latest changes
	await simpleGit(repoPath).pull();

	const scssFiles = [
		path.join( __dirname, '../scss/partials/_symbolicons.scss' ),
		path.join( __dirname, '../scss/partials/_symbolicons-map.scss' ),
	];

	copyScss( path.join( repoPath, '/symbolicons/output/symbolicons.scss' ), scssFiles[0] );
	copyScss( path.join( repoPath, '/symbolicons/output/symbolicons-map.scss' ), scssFiles[1] );

	execFileSync( 'npx', [ 'wp-scripts', 'lint-style', ...scssFiles, '--fix' ], { stdio: 'inherit' } );

	fs.copyFileSync(
		path.join( repoPath, '/symbolicons/output/symbolicons.json' ),
		path.join( __dirname, '../symbolicons/symbolicons.json' )
	);
})();
