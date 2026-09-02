#!/usr/bin/env node
/**
 * Regenerate the file trees in docs/*-structure.txt from the real filesystem.
 *
 * Every hand-written description is carried across, and the existing order of
 * entries inside a directory is preserved (the docs group related files
 * deliberately, so we do not re-alphabetize). New files are appended to their
 * directory with a TODO marker; files that no longer exist are dropped and
 * reported. The diff always shows what changed and what needs a description.
 *
 *   node _build_scripts/structure-docs.js            # rewrite the docs
 *   node _build_scripts/structure-docs.js --check     # report only, exit 1 on drift
 *
 * Non-tree content (headings, prose, notes) passes through untouched, as do
 * blocks marked `manual` in structure-docs.config.js.
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '..' );
const CONFIG = require( './structure-docs.config.js' );

const TODO = 'TODO: describe';
const CHECK_ONLY = process.argv.includes( '--check' );

/* -------------------------------------------------------------------------- */
/* Helpers                                                                    */
/* -------------------------------------------------------------------------- */

function globToRe( glob ) {
	const escaped = glob.replace( /[.+^${}()|[\]\\]/g, '\\$&' ).replace( /\*/g, '[^/]*' );
	return new RegExp( `^${ escaped }$` );
}

function matchesAny( value, globs ) {
	return ( globs || [] ).some( ( glob ) => globToRe( glob ).test( value ) );
}

function join( parts ) {
	return parts.filter( ( part ) => part && part !== '.' ).join( '/' );
}

/** Resolve the configured mode for a directory path, honouring `*` segments. */
function dirMode( relPath ) {
	const modes = CONFIG.dirModes || {};
	if ( modes[ relPath ] ) {
		return modes[ relPath ];
	}
	for ( const [ pattern, mode ] of Object.entries( modes ) ) {
		if ( ! pattern.includes( '*' ) ) {
			continue;
		}
		const re = new RegExp(
			'^' +
				pattern
					.split( '/' )
					.map( ( seg ) =>
						seg === '*' ? '[^/]+' : seg.replace( /[.+^${}()|[\]\\]/g, '\\$&' )
					)
					.join( '/' ) +
				'$'
		);
		if ( re.test( relPath ) ) {
			return mode;
		}
	}
	return 'expand';
}

/* -------------------------------------------------------------------------- */
/* Doc parsing                                                                */
/* -------------------------------------------------------------------------- */

const INDENT = '(?:│ {3}| {4})';
const TREE_RE = new RegExp( `^(${ INDENT }*)(├── |└── )(.*)$` );
// Any indented line inside a tree that is not a branch line is a note attached
// to the entry above it (the shortcode listings under class-shortcodes.php).
const NOTE_RE = new RegExp( `^(${ INDENT }+)(?!├── |└── )(\\S.*)$` );

/**
 * Split a doc into passthrough text chunks and tree chunks. Each tree chunk
 * records both the nearest preceding markdown heading and the nearest preceding
 * non-empty line, either of which a configured block label may match.
 */
function parseDoc( text ) {
	const lines = text.split( '\n' );
	const chunks = [];
	let buffer = [];
	let tree = null;
	let heading = '';
	let label = '';

	const flushText = () => {
		if ( buffer.length ) {
			chunks.push( { type: 'text', lines: buffer } );
			buffer = [];
		}
	};
	const flushTree = () => {
		if ( tree ) {
			chunks.push( { type: 'tree', heading, label, lines: tree } );
			tree = null;
		}
	};

	for ( const line of lines ) {
		if ( TREE_RE.test( line ) || NOTE_RE.test( line ) ) {
			if ( ! tree ) {
				flushText();
				tree = [];
			}
			tree.push( line );
			continue;
		}
		flushTree();
		if ( line.trim() !== '' ) {
			label = line.trim();
			if ( line.startsWith( '#' ) ) {
				heading = line.trim();
			}
		}
		buffer.push( line );
	}
	flushTree();
	flushText();

	return chunks;
}

/**
 * Read a tree chunk into a map of repo-relative path => { desc, notes } plus the
 * order in which each directory's children were listed.
 */
function readTreeChunk( lines, root ) {
	const descriptions = new Map();
	const order = new Map();
	const stack = [];
	let last = null;

	for ( const line of lines ) {
		const branch = TREE_RE.exec( line );
		if ( branch ) {
			const depth = branch[ 1 ].length / 4;
			const body = branch[ 3 ];
			const sep = body.indexOf( ' - ' );
			const name = ( sep === -1 ? body : body.slice( 0, sep ) ).trim().replace( /\/$/, '' );
			const desc = sep === -1 ? '' : body.slice( sep + 3 ).trim();

			stack.length = depth;
			stack[ depth ] = name;

			const parent = join( [ root, ...stack.slice( 0, depth ) ] ) || '.';
			const full = join( [ root, ...stack.slice( 0, depth + 1 ) ] );

			descriptions.set( full, { desc, notes: [] } );
			if ( ! order.has( parent ) ) {
				order.set( parent, [] );
			}
			order.get( parent ).push( name );
			last = { path: full, depth };
			continue;
		}

		const note = NOTE_RE.exec( line );
		if ( note && last && descriptions.has( last.path ) ) {
			// Preserve indentation relative to the entry's own child prefix.
			const extra = Math.max( 0, note[ 1 ].length - ( last.depth + 1 ) * 4 );
			descriptions.get( last.path ).notes.push( { extra, text: note[ 2 ] } );
		}
	}

	return { descriptions, order };
}

/* -------------------------------------------------------------------------- */
/* Filesystem                                                                 */
/* -------------------------------------------------------------------------- */

function readDir( relPath, block ) {
	let entries;
	try {
		entries = fs.readdirSync( path.join( ROOT, relPath === '.' ? '' : relPath ), {
			withFileTypes: true,
		} );
	} catch {
		return [];
	}

	return entries
		.filter( ( entry ) => {
			if ( matchesAny( entry.name, block && block.unignore ) ) {
				return true;
			}
			if ( entry.name.startsWith( '.' ) ) {
				return false;
			}
			return ! matchesAny( entry.name, CONFIG.ignore );
		} )
		.map( ( entry ) => ( { name: entry.name, isDir: entry.isDirectory() } ) );
}

/* -------------------------------------------------------------------------- */
/* Tree building                                                              */
/* -------------------------------------------------------------------------- */

const report = { added: [], removed: [], todo: [] };

function buildNodes( relPath, block, prior, depth ) {
	if ( block.maxDepth && depth >= block.maxDepth ) {
		return [];
	}

	const entries = readDir( relPath, block );

	// Re-add pinned children (build output, dependency trees) that are ignored
	// on disk but documented on purpose.
	for ( const [ pinPath, pinDesc ] of Object.entries( CONFIG.pin || {} ) ) {
		if ( path.posix.dirname( pinPath ) !== ( relPath || '.' ) ) {
			continue;
		}
		const name = path.posix.basename( pinPath );
		const existing = entries.find( ( entry ) => entry.name === name );
		if ( existing ) {
			existing.pinDesc = pinDesc;
		} else {
			entries.push( { name, isDir: true, pinDesc } );
		}
	}

	const visible = entries.filter( ( entry ) => {
		if ( entry.isDir && block.filesOnly ) {
			return false;
		}
		if ( ! block.include ) {
			return true;
		}
		// Directories survive an include filter so the tree can be walked,
		// unless the block only wants files.
		return entry.isDir || matchesAny( entry.name, block.include );
	} );

	const docOrder = prior.order.get( relPath || '.' ) || [];
	const remaining = new Map( visible.map( ( entry ) => [ entry.name, entry ] ) );
	const ordered = [];

	for ( const name of docOrder ) {
		if ( remaining.has( name ) ) {
			ordered.push( remaining.get( name ) );
			remaining.delete( name );
		} else {
			report.removed.push( join( [ relPath, name ] ) );
		}
	}

	const newcomers = [ ...remaining.values() ].sort( ( a, b ) => a.name.localeCompare( b.name ) );
	ordered.push( ...newcomers );

	// Bare listings (acf-json/, images/) carry no descriptions by design, so new
	// files there should not sprout TODO markers.
	const dirIsDescribed =
		docOrder.length === 0 ||
		docOrder.some( ( name ) => {
			const known = prior.descriptions.get( join( [ relPath, name ] ) );
			return known && known.desc;
		} );

	return ordered.map( ( entry ) => {
		const childPath = join( [ relPath, entry.name ] );
		const known = prior.descriptions.get( childPath );
		const isNew = ! known;
		const mode = entry.isDir ? dirMode( childPath ) : 'expand';

		let desc = known ? known.desc : '';
		let children = [];

		if ( entry.isDir && mode === 'list' ) {
			desc = readDir( childPath, block )
				.map( ( child ) => child.name.replace( /\.php$/, '' ) )
				.sort( ( a, b ) => a.localeCompare( b ) )
				.join( ', ' );
		} else if ( entry.isDir && mode === 'expand' && ! entry.pinDesc ) {
			children = buildNodes( childPath, block, prior, depth + 1 );
		}

		if ( isNew ) {
			report.added.push( childPath );
			if ( ! desc && entry.pinDesc ) {
				desc = entry.pinDesc;
			}
			if ( ! desc && dirIsDescribed && mode !== 'list' ) {
				desc = TODO;
				report.todo.push( childPath );
			}
		}

		return {
			name: entry.isDir ? `${ entry.name }/` : entry.name,
			desc,
			notes: known ? known.notes : [],
			children,
		};
	} );
}

function renderNodes( nodes, prefix = '' ) {
	const out = [];
	nodes.forEach( ( node, index ) => {
		const isLast = index === nodes.length - 1;
		const childPrefix = prefix + ( isLast ? '    ' : '│   ' );

		out.push( `${ prefix }${ isLast ? '└── ' : '├── ' }${ node.name }${ node.desc ? ` - ${ node.desc }` : '' }` );
		for ( const note of node.notes ) {
			out.push( `${ childPrefix }${ ' '.repeat( note.extra ) }${ note.text }` );
		}
		out.push( ...renderNodes( node.children, childPrefix ) );
	} );
	return out;
}

/* -------------------------------------------------------------------------- */
/* Main                                                                       */
/* -------------------------------------------------------------------------- */

let drifted = false;

for ( const docConfig of CONFIG.docs ) {
	const docPath = path.join( ROOT, docConfig.file );
	const original = fs.readFileSync( docPath, 'utf8' );
	const chunks = parseDoc( original );
	const used = new Set();

	const rebuilt = chunks.map( ( chunk ) => {
		if ( chunk.type !== 'tree' ) {
			return chunk.lines.join( '\n' );
		}

		const block = docConfig.blocks.find(
			( candidate ) => candidate.label === chunk.heading || candidate.label === chunk.label
		);

		if ( ! block ) {
			console.warn( `  ! ${ docConfig.file }: unconfigured tree under "${ chunk.label }" - left as-is` );
			return chunk.lines.join( '\n' );
		}
		if ( block.manual ) {
			return chunk.lines.join( '\n' );
		}
		if ( used.has( block.label ) ) {
			console.warn( `  ! ${ docConfig.file }: extra tree under "${ chunk.label }" - left as-is` );
			return chunk.lines.join( '\n' );
		}
		used.add( block.label );

		const root = block.root === '.' ? '' : block.root;
		const prior = readTreeChunk( chunk.lines, root );
		return renderNodes( buildNodes( root, block, prior, 0 ) ).join( '\n' );
	} );

	for ( const block of docConfig.blocks ) {
		if ( ! block.manual && ! used.has( block.label ) ) {
			console.warn( `  ! ${ docConfig.file }: no tree found under "${ block.label }"` );
		}
	}

	const updated = rebuilt.join( '\n' );

	if ( updated === original ) {
		console.log( `  = ${ docConfig.file } up to date` );
		continue;
	}

	drifted = true;
	if ( CHECK_ONLY ) {
		console.log( `  ~ ${ docConfig.file } is out of date` );
	} else {
		fs.writeFileSync( docPath, updated );
		console.log( `  > ${ docConfig.file } updated` );
	}
}

const section = ( title, items ) => {
	const unique = [ ...new Set( items ) ].sort();
	if ( ! unique.length ) {
		return;
	}
	console.log( `\n${ title } (${ unique.length }):` );
	unique.forEach( ( item ) => console.log( `  ${ item }` ) );
};

section( 'New - needs a description', report.todo );
section( 'New - added, no description needed', report.added.filter( ( item ) => ! report.todo.includes( item ) ) );
section( 'Gone - dropped from the docs', report.removed );

if ( report.todo.length ) {
	console.log( `\nSearch the docs for "${ TODO }" to fill these in.` );
}

if ( CHECK_ONLY && drifted ) {
	console.log( '\nRun `npm run docs:structure` to update.' );
	process.exit( 1 );
}
