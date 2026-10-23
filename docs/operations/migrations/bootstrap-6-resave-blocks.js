/* eslint-disable no-console -- a console tool: its output is the report. */
/**
 * Re-save Spoiler, Grade and Screener blocks through Gutenberg so their
 * stored HTML is the current (Bootstrap 6) save() output.
 *
 * Paste into the browser console on any block editor screen
 * (e.g. /wp-admin/post-new.php), logged in as an administrator, then run:
 *
 *     await lwtvResaveBlocks();                  // report only
 *     await lwtvResaveBlocks( { apply: true } ); // save
 *
 * Only these three blocks change: each one's raw segment is replaced with
 * Gutenberg's serialization of that block (old markup upgraded through the
 * block's deprecations). The rest of the post is left byte-for-byte. Posts
 * with a block that doesn't validate are skipped and listed.
 *
 * See bootstrap-6.md in this folder.
 *
 * @param {Object}   options
 * @param {boolean}  options.apply Save changes. Default false: report only.
 * @param {string[]} options.types REST bases to scan.
 * @return {Promise<Object>} What was found, skipped, saved and failed.
 */
window.lwtvResaveBlocks = async function lwtvResaveBlocks( {
	apply = false,
	types = [ 'posts', 'pages', 'blocks', 'show', 'character', 'actor' ],
} = {} ) {
	const NAMES = [ 'lez-library/spoilers', 'lwtv/grade', 'lwtv/screener' ];
	const SEGMENT =
		/<!-- wp:(lez-library\/spoilers|lwtv\/grade|lwtv\/screener)\b[\s\S]*?<!-- \/wp:\1 -->/g;
	const STATUSES = 'publish,draft,private,future,pending';

	// The new save() and its deprecations must be loaded, or this would
	// "upgrade" blocks with stale code.
	const screener = wp.blocks.getBlockType( 'lwtv/screener' );
	if ( ! screener || ( screener.deprecated || [] ).length < 3 ) {
		throw new Error(
			'lwtv/screener is not registered with its Bootstrap 6 deprecations. Run this on a block editor screen after deploying the blocks build.'
		);
	}

	const walk = ( blocks, cb ) =>
		blocks.forEach( ( b ) => {
			cb( b );
			walk( b.innerBlocks || [], cb );
		} );

	// Gutenberg logs every deprecation match; keep the console readable.
	const quiet = ( fn ) => {
		const saved = [ console.warn, console.error, console.info ];
		console.warn = console.error = console.info = () => {};
		try {
			return fn();
		} finally {
			[ console.warn, console.error, console.info ] = saved;
		}
	};

	const report = [];
	const skipped = [];

	for ( const type of types ) {
		let page = 1;
		let pages = 1;
		do {
			const res = await wp.apiFetch( {
				path: `/wp/v2/${ type }?per_page=100&page=${ page }&context=edit&status=${ STATUSES }&_fields=id,title,content`,
				parse: false,
			} );
			pages = Number( res.headers.get( 'X-WP-TotalPages' ) ) || 1;

			for ( const post of await res.json() ) {
				const raw = ( post.content && post.content.raw ) || '';
				const segments = raw.match( SEGMENT );
				if ( ! segments ) {
					continue;
				}

				const targets = [];
				quiet( () =>
					walk( wp.blocks.parse( raw ), ( b ) => {
						if ( NAMES.includes( b.name ) ) {
							targets.push( b );
						}
					} )
				);

				const title = ( post.title && post.title.raw ) || '';
				if ( targets.length !== segments.length ) {
					skipped.push( {
						type,
						id: post.id,
						title,
						reason: 'block count mismatch',
					} );
					continue;
				}
				if ( targets.some( ( b ) => ! b.isValid ) ) {
					skipped.push( {
						type,
						id: post.id,
						title,
						reason: 'invalid block',
					} );
					continue;
				}

				let i = 0;
				const next = raw.replace( SEGMENT, () =>
					quiet( () => wp.blocks.serialize( [ targets[ i++ ] ] ) )
				);
				if ( next === raw ) {
					continue; // Already current.
				}

				report.push( {
					type,
					id: post.id,
					title,
					blocks: targets.length,
					next,
				} );
			}
			page++;
		} while ( page <= pages );
	}

	console.table( report.map( ( { next, ...row } ) => row ) );
	if ( skipped.length ) {
		console.warn(
			`${ skipped.length } post(s) skipped; fix these in the editor:`
		);
		console.table( skipped );
	}

	if ( ! apply ) {
		console.log(
			`${ report.length } post(s) would be re-saved. Run lwtvResaveBlocks( { apply: true } ) to save.`
		);
		return { report, skipped };
	}

	let saved = 0;
	const failed = [];
	const normalized = [];
	for ( const row of report ) {
		try {
			await wp.apiFetch( {
				path: `/wp/v2/${ row.type }/${ row.id }`,
				method: 'POST',
				data: { content: row.next },
			} );
			saved++;
			// WordPress may normalize content on save; worth a look, not a failure.
			const check = await wp.apiFetch( {
				path: `/wp/v2/${ row.type }/${ row.id }?context=edit&_fields=content`,
			} );
			if ( check.content.raw !== row.next ) {
				normalized.push( {
					type: row.type,
					id: row.id,
					title: row.title,
				} );
			}
		} catch ( e ) {
			failed.push( {
				type: row.type,
				id: row.id,
				title: row.title,
				error: e.message,
			} );
		}
	}

	console.log( `Re-saved ${ saved } of ${ report.length } post(s).` );
	if ( normalized.length ) {
		console.warn(
			`${ normalized.length } saved, but WordPress changed the content on save; check these in the editor:`
		);
		console.table( normalized );
	}
	if ( failed.length ) {
		console.error( `${ failed.length } failed:` );
		console.table( failed );
	}
	return { report, skipped, saved, normalized, failed };
};
