/**
 * Search Compare: Meilisearch (browser, search key) next to SearchWP (REST).
 *
 * Throwaway prototype for issue #749.
 */
( function () {
	const cfg = window.lwtvMeiliCompare;
	const input = document.getElementById( 'lwtv-meili-q' );
	if ( ! cfg || ! input ) {
		return;
	}

	const meiliList = document.getElementById( 'lwtv-meili-results' );
	const meiliTime = document.getElementById( 'lwtv-meili-time' );
	const swpList = document.getElementById( 'lwtv-swp-results' );
	const swpTime = document.getElementById( 'lwtv-swp-time' );
	let timer = null;
	let latest = 0;

	/**
	 * Append text with Meilisearch's <em> highlights, without innerHTML.
	 *
	 * @param {Element} parent Element to fill.
	 * @param {string}  text   Highlighted text from _formatted.
	 */
	function appendHighlighted( parent, text ) {
		String( text )
			.split( /(<em>|<\/em>)/ )
			.reduce( ( inEm, part ) => {
				if ( '<em>' === part ) {
					return true;
				}
				if ( '</em>' === part ) {
					return false;
				}
				if ( part ) {
					const node = inEm
						? document.createElement( 'mark' )
						: document.createTextNode( part );
					if ( inEm ) {
						node.textContent = part;
					}
					parent.appendChild( node );
				}
				return inEm;
			}, false );
	}

	/**
	 * Render one list of hits.
	 *
	 * @param {Element} list Target list.
	 * @param {Array}   hits Documents, optionally with _formatted.
	 */
	function render( list, hits ) {
		list.replaceChildren();
		if ( ! hits.length ) {
			const li = document.createElement( 'li' );
			li.textContent = cfg.labels.noResults;
			list.appendChild( li );
			return;
		}

		hits.forEach( ( hit ) => {
			const shown = hit._formatted || hit;
			const li = document.createElement( 'li' );
			const link = document.createElement( 'a' );
			link.href = hit.url;
			link.target = '_blank';
			link.rel = 'noopener';
			appendHighlighted( link, shown.name );
			li.appendChild( link );

			const meta = document.createElement( 'span' );
			meta.append( ' — ' );
			appendHighlighted( meta, ( shown.shows || [] ).join( ', ' ) );
			if ( hit.role ) {
				meta.append( ' (' + hit.role + ')' );
			}
			li.appendChild( meta );
			list.appendChild( li );
		} );
	}

	/**
	 * Show an error in place of results.
	 *
	 * @param {Element} list  Target list.
	 * @param {Error}   error The failure.
	 */
	function fail( list, error ) {
		list.replaceChildren();
		const li = document.createElement( 'li' );
		li.textContent = cfg.labels.error + ' ' + error.message;
		list.appendChild( li );
	}

	async function searchMeili( q, run ) {
		const started = performance.now();
		const response = await fetch(
			cfg.url + '/indexes/' + encodeURIComponent( cfg.index ) + '/search',
			{
				method: 'POST',
				headers: {
					Authorization: 'Bearer ' + cfg.searchKey,
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( {
					q,
					// wp_localize_script() sends every value as a string; Meilisearch wants an integer.
					limit: parseInt( cfg.limit, 10 ) || 20,
					attributesToHighlight: [ 'name', 'shows', 'actors' ],
					// Drop the most common query word first, so "kara danvers" keeps the
					// rarer "danvers" (Alex Danvers) instead of every Kara.
					matchingStrategy: 'frequency',
				} ),
			}
		);
		if ( ! response.ok ) {
			const error = await response.json().catch( () => ( {} ) );
			throw new Error(
				'HTTP ' +
					response.status +
					( error.code
						? ': ' + error.code + ' — ' + error.message
						: '' )
			);
		}
		const data = await response.json();
		if ( run === latest ) {
			showResults(
				meiliList,
				meiliTime,
				data.hits || [],
				performance.now() - started,
				data.processingTimeMs
			);
		}
	}

	/**
	 * Write a column's timing line and results.
	 *
	 * @param {Element} list     Target list.
	 * @param {Element} time     Timing element.
	 * @param {Array}   hits     Results.
	 * @param {number}  roundMs  Round trip, in ms.
	 * @param {number}  serverMs Server time, in ms.
	 */
	function showResults( list, time, hits, roundMs, serverMs ) {
		time.textContent =
			Math.round( roundMs ) +
			' ' +
			cfg.labels.ms +
			', ' +
			serverMs +
			' ' +
			cfg.labels.server;
		render( list, hits );
	}

	async function searchSwp( q, run ) {
		const started = performance.now();
		const data = await window.wp.apiFetch( {
			path: cfg.route + '?q=' + encodeURIComponent( q ),
		} );
		if ( run === latest ) {
			showResults(
				swpList,
				swpTime,
				data.hits || [],
				performance.now() - started,
				data.ms
			);
			if ( data.note ) {
				swpTime.textContent = data.note;
			}
		}
	}

	input.addEventListener( 'input', () => {
		clearTimeout( timer );
		timer = setTimeout( () => {
			const q = input.value.trim();
			const run = ++latest;
			if ( ! q ) {
				meiliList.replaceChildren();
				swpList.replaceChildren();
				meiliTime.textContent = '';
				swpTime.textContent = '';
				return;
			}
			searchMeili( q, run ).catch( ( e ) => fail( meiliList, e ) );
			searchSwp( q, run ).catch( ( e ) => fail( swpList, e ) );
		}, 250 );
	} );
} )();
