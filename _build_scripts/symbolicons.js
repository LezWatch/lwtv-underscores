/*
 * This script grabs the symbolicons from the private repo and puts them in the right place.
 */

// Check the variable passed in from the command line
const branch = process.env.LWTV_BRANCH;

const simpleGit = require('simple-git');
const fs        = require('fs');
const path      = require('path');

(async () => {
	const repoPath = path.join( __dirname, 'tmp-icons' );
	if (!fs.existsSync(repoPath)) {
		await git.clone('https://github.com/LezWatch/symbolicons-private', repoPath, ['--branch', branch]);
	} else {
		await simpleGit(repoPath).checkout(branch);
	}

	// Git Pull to get the latest changes
	await simpleGit(repoPath).pull();

	fs.copyFileSync(
		path.join( repoPath, '/symbolicons/output/symbolicons.scss' ),
		path.join( __dirname, '../scss/partials/_symbolicons.scss' )
	);

	fs.copyFileSync(
		path.join( repoPath, '/symbolicons/output/symbolicons-map.scss' ),
		path.join( __dirname, '../scss/partials/_symbolicons-map.scss' )
	);

	fs.copyFileSync(
		path.join( repoPath, '/symbolicons/output/symbolicons.json' ),
		path.join( __dirname, '../symbolicons/symbolicons.json' )
	);

	// Note: This file is NOT included in the Github repo, but is used by the theme.
	fs.copyFileSync(
		path.join( repoPath, '/symbolicons/output/sprite.css.svg' ),
		path.join( __dirname, '../symbolicons/sprite.css.svg' )
	);

	// Note: This file is NOT included in the Github repo, but is used by the theme.
	fs.copyFileSync(
		path.join( repoPath, '/symbolicons/output/sprite.symbol.svg' ),
		path.join( __dirname, '../symbolicons/sprite.symbol.svg' )
	);
})();
