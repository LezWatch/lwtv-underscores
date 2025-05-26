/*
 * This script grabs the symbolicons from the private repo and puts them in the right place.
 */

const simpleGit = require('simple-git');
const fs        = require('fs');
const path      = require('path');

(async () => {
	const repoPath = path.join( __dirname, 'tmp-icons' );
	if (!fs.existsSync(repoPath)) {
		await git.clone('https://github.com/LezWatch/symbolicons-private', repoPath, ['--branch', 'development']);
	} else {
		await simpleGit(repoPath).checkout('development');
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
})();
