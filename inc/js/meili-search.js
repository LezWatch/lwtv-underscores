/**
 * Meilisearch header search modal (issue #749).
 *
 * Script module (see functions.php): runs after the DOM is parsed. It only
 * starts when #lwtv-meili-config is on the page, so importing it elsewhere
 * (for example to check the pure helpers in the console) has no side effects.
 *
 * Index text only ever reaches the page through textContent or setAttribute.
 * If Meilisearch fails or is slow, the SearchWP modal takes over.
 */

/**
 * Fields each row needs, per index type.
 */
const FIELDS = {
	shows: [
		'id',
		'name',
		'qualifier',
		'years',
		'characters',
		'alt_names',
		'url',
		'thumb',
	],
	characters: [ 'id', 'name', 'shows', 'actors', 'url', 'thumb' ],
	actors: [ 'id', 'name', 'characters', 'character_count', 'url', 'thumb' ],
	content: [ 'id', 'type', 'title', 'date', 'url', 'thumb' ],
};

const SEP = ' · ';

/**
 * Letters WordPress remove_accents() folds that don't decompose under NFD,
 * lowercase only (fold() lowercases first). From remove_accents()'s table.
 */
const FOLD_MAP = {
	æ: 'ae',
	ð: 'd',
	ø: 'o',
	þ: 'th',
	ß: 's',
	đ: 'd',
	ħ: 'h',
	ı: 'i',
	ĳ: 'ij',
	ĸ: 'k',
	ŀ: 'l',
	ł: 'l',
	ŉ: 'n',
	ŋ: 'n',
	œ: 'oe',
	ŧ: 't',
	ſ: 's',
};
const FOLD_LETTERS = new RegExp(
	'[' + Object.keys( FOLD_MAP ).join( '' ) + ']',
	'g'
);

/**
 * Lowercase and strip accents the way remove_accents() does, for comparing
 * names with a search term: combining marks go (é → e), letters that don't
 * decompose are mapped (ø → o, ł → l, æ → ae), and spacing marks such as
 * the Japanese long-vowel mark stay.
 *
 * @param {string} s Text.
 * @return {string} Folded text.
 */
export function fold( s ) {
	return String( s )
		.toLowerCase()
		.normalize( 'NFD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.replace( FOLD_LETTERS, ( c ) => FOLD_MAP[ c ] );
}

/**
 * Alternate names that contain every word of the term.
 *
 * Port of LWTV\Plugins\SearchWP\Build\Alt_Names::matching() with an accent
 * fold: case-, accent- and word-order-insensitive substring matches. Blank
 * names are skipped and names are listed once, in their original order and
 * spelling.
 *
 * @param {string[]} names Alternate names.
 * @param {string}   term  Search term.
 * @return {string[]} Matching names.
 */
export function altNamesMatching( names, term ) {
	const words = [
		...new Set( fold( term ).trim().split( /\s+/u ).filter( Boolean ) ),
	];
	if ( ! words.length || ! Array.isArray( names ) ) {
		return [];
	}

	const found = new Map();
	names.forEach( ( raw ) => {
		const name = String( raw ?? '' ).trim();
		if ( '' === name ) {
			return;
		}
		const haystack = fold( name ).trim();
		if ( found.has( haystack ) ) {
			return;
		}
		if ( words.every( ( word ) => haystack.includes( word ) ) ) {
			found.set( haystack, name );
		}
	} );

	return [ ...found.values() ];
}

/**
 * The tab whose top hit ranks highest.
 *
 * Tabs with no hits are never picked. Ties go to the earlier tab in order.
 *
 * @param {Array<{type: string, hits: Array, total: number}>} results Per-tab results.
 * @param {string[]}                                          order   Tab order.
 * @return {string|null} Tab type, or null when nothing matched.
 */
export function strongestTab( results, order ) {
	let best = null;
	let bestScore = -Infinity;

	order.forEach( ( type ) => {
		const result = results.find( ( r ) => r.type === type );
		if ( ! result || ! ( result.total > 0 ) || ! result.hits?.length ) {
			return;
		}
		const score = Number( result.hits[ 0 ]._rankingScore ?? 0 );
		if ( score > bestScore ) {
			best = type;
			bestScore = score;
		}
	} );

	return best;
}

/**
 * Replace the first %d or %s in a translated pattern.
 *
 * @param {string}        pattern Pattern from the config.
 * @param {string|number} value   Value.
 * @return {string} Text.
 */
function fmt( pattern, value ) {
	return String( pattern ).replace( /%[ds]/, String( value ) );
}

/**
 * Singular or plural pattern for a count, filled in.
 *
 * @param {number} n    Count.
 * @param {string} one  Singular pattern.
 * @param {string} many Plural pattern.
 * @return {string} Text.
 */
function plural( n, one, many ) {
	return fmt( 1 === n ? one : many, n );
}

/**
 * The status message for one completed search.
 *
 * "1 show, 14 characters, 9 actors, 23 posts. Showing Characters." or
 * "No results for supergirl".
 *
 * @param {Array<{type: string, total: number, label: string, one: string, many: string}>} results      Per-tab results, in tab order.
 * @param {string|null}                                                                    selectedType Selected tab.
 * @param {Object}                                                                         strings      Flat strings from the config.
 * @param {string}                                                                         query        Search term.
 * @return {string} Message.
 */
export function statusText( results, selectedType, strings, query ) {
	if ( ! results.some( ( r ) => r.total > 0 ) ) {
		return fmt( strings.noResults, query );
	}

	const counts = results
		.map( ( r ) => plural( r.total, r.one, r.many ) )
		.join( ', ' );
	const selected = results.find( ( r ) => r.type === selectedType );

	return selected
		? counts + '. ' + fmt( strings.showing, selected.label )
		: counts + '.';
}

/**
 * Start the modal.
 *
 * @param {Object} cfg Config from #lwtv-meili-config.
 */
function start( cfg ) {
	const dialog = document.getElementById( 'lwtv-meili-dialog' );
	const input = document.getElementById( 'lwtv-meili-input' );
	const tablist = document.getElementById( 'lwtv-meili-tablist' );
	const status = document.getElementById( 'lwtv-meili-status' );
	if (
		! dialog ||
		! input ||
		! tablist ||
		! status ||
		'function' !== typeof dialog.showModal
	) {
		return false;
	}

	const strings = cfg.strings || {};
	const tabs = new Map();
	cfg.tabs.forEach( ( t ) => {
		const tab = document.getElementById( 'lwtv-meili-tab-' + t.type );
		const panel = document.getElementById( 'lwtv-meili-panel-' + t.type );
		if ( tab && panel ) {
			tabs.set( t.type, {
				...t,
				tab,
				panel,
				count: tab.querySelector( '.lwtv-meili-count' ),
			} );
		}
	} );
	if ( ! tabs.size ) {
		return false;
	}

	// Only tabs whose markup exists; the query list is built from the same map.
	const order = [ ...tabs.keys() ];
	let selected = order[ 0 ];
	let userPicked = false;
	let fellBack = false;
	let opener = null;
	let timer = null;
	let seq = 0;
	let controller = null;

	/**
	 * Where "See all results" goes.
	 *
	 * @param {string} q Search term.
	 * @return {string} URL.
	 */
	const searchUrl = ( q ) => cfg.searchPage + '?s=' + encodeURIComponent( q );

	/**
	 * The hidden SearchWP trigger, when its modal is on the page.
	 *
	 * @return {Element|null} Trigger.
	 */
	const fallbackTrigger = () =>
		document.querySelector(
			'[data-lwtv-meili-fallback] [data-searchwp-modal-trigger]'
		);

	/**
	 * Select a tab. Moves focus only when asked to (a user action).
	 *
	 * @param {string}  type  Tab type.
	 * @param {boolean} focus Move focus to the tab.
	 */
	function select( type, focus ) {
		if ( ! tabs.has( type ) ) {
			return;
		}
		selected = type;
		tabs.forEach( ( t, key ) => {
			const on = key === type;
			t.tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			t.tab.tabIndex = on ? 0 : -1;
			t.panel.hidden = ! on;
		} );
		if ( focus ) {
			tabs.get( type ).tab.focus();
		}
	}

	/**
	 * Set a tab's count and accessible name.
	 *
	 * @param {Object} t     Tab entry.
	 * @param {number} total Hit count.
	 */
	function setCount( t, total ) {
		if ( t.count ) {
			t.count.textContent = String( total );
		}
		t.tab.setAttribute(
			'aria-label',
			t.label +
				', ' +
				plural( total, strings.resultOne, strings.resultMany )
		);
	}

	/**
	 * Fill a panel with a message (and optionally a link).
	 *
	 * @param {Object} t        Tab entry.
	 * @param {string} message  Text.
	 * @param {string} linkText Link text, or '' for none.
	 * @param {string} href     Link URL.
	 */
	function panelMessage( t, message, linkText, href ) {
		const p = document.createElement( 'p' );
		p.className = 'lwtv-meili-message';
		p.textContent = message;
		if ( linkText ) {
			const a = document.createElement( 'a' );
			a.href = href;
			a.textContent = linkText;
			p.append( ' ', a );
		}
		t.panel.replaceChildren( p );
		t.panel.tabIndex = 0;
	}

	/**
	 * Empty every panel and zero every count.
	 */
	function clear() {
		tabs.forEach( ( t ) => {
			t.panel.replaceChildren();
			t.panel.tabIndex = 0;
			setCount( t, 0 );
		} );
		status.textContent = '';
	}

	/**
	 * The detail line for a hit.
	 *
	 * @param {string} type Tab type.
	 * @param {Object} hit  Document.
	 * @return {string} Text.
	 */
	function detail( type, hit ) {
		const list = ( v ) => ( Array.isArray( v ) ? v.filter( Boolean ) : [] );
		const parts = [];

		if ( 'shows' === type ) {
			if ( hit.years ) {
				parts.push( String( hit.years ) );
			}
			const chars = list( hit.characters ).length;
			parts.push(
				plural(
					chars,
					tabs.get( 'characters' )?.one || '%d',
					tabs.get( 'characters' )?.many || '%d'
				)
			);
		} else if ( 'characters' === type ) {
			const shows = list( hit.shows ).slice( 0, 2 );
			if ( shows.length ) {
				parts.push( shows.join( ', ' ) );
			}
			const actor = list( hit.actors )[ 0 ];
			if ( actor ) {
				parts.push( String( actor ) );
			}
		} else if ( 'actors' === type ) {
			const chars = list( hit.characters ).slice( 0, 2 );
			if ( chars.length ) {
				parts.push( chars.join( ', ' ) );
			}
			const n = Number( hit.character_count ) || 0;
			parts.push(
				plural(
					n,
					tabs.get( 'characters' )?.one || '%d',
					tabs.get( 'characters' )?.many || '%d'
				)
			);
		} else if ( 'content' === type ) {
			parts.push( 'page' === hit.type ? strings.page : strings.news );
			const date = Number( hit.date );
			if ( date > 0 ) {
				parts.push(
					new Date( date * 1000 ).toLocaleDateString(
						document.documentElement.lang || undefined,
						{ month: 'short', year: 'numeric' }
					)
				);
			}
		}

		return parts.join( SEP );
	}

	/**
	 * The icon for a hit, cloned from its <template>.
	 *
	 * @param {string} type Tab type.
	 * @param {Object} hit  Document.
	 * @return {Node|null} Icon fragment.
	 */
	function icon( type, hit ) {
		const id = 'content' === type && 'page' === hit.type ? 'page' : type;
		const template = document.getElementById( 'lwtv-meili-icon-' + id );
		return template ? template.content.cloneNode( true ) : null;
	}

	/**
	 * One result row.
	 *
	 * @param {string} type Tab type.
	 * @param {Object} hit  Document.
	 * @param {string} q    Search term.
	 * @return {Element} List item.
	 */
	function row( type, hit, q ) {
		const li = document.createElement( 'li' );
		const a = document.createElement( 'a' );
		a.className = 'lwtv-meili-row';
		a.setAttribute( 'href', String( hit.url || '#' ) );

		if ( hit.thumb ) {
			const img = document.createElement( 'img' );
			img.className = 'lwtv-meili-thumb';
			img.setAttribute( 'src', String( hit.thumb ) );
			img.setAttribute( 'alt', '' );
			img.setAttribute( 'loading', 'lazy' );
			a.append( img );
		} else {
			const blank = document.createElement( 'span' );
			blank.className = 'lwtv-meili-thumb lwtv-meili-thumb--none';
			blank.setAttribute( 'aria-hidden', 'true' );
			a.append( blank );
		}

		const text = document.createElement( 'span' );
		text.className = 'lwtv-meili-text';

		const title = document.createElement( 'span' );
		title.className = 'lwtv-meili-title';
		const glyph = icon( type, hit );
		if ( glyph ) {
			const wrap = document.createElement( 'span' );
			wrap.className = 'lwtv-meili-icon';
			wrap.append( glyph );
			title.append( wrap );
		}
		let name = String(
			( 'content' === type ? hit.title : hit.name ) || ''
		);
		if ( 'shows' === type && hit.qualifier ) {
			name += ' (' + hit.qualifier + ')';
		}
		title.append( document.createTextNode( name ) );
		text.append( title );

		const line = detail( type, hit );
		if ( line ) {
			const d = document.createElement( 'span' );
			d.className = 'lwtv-meili-detail';
			d.textContent = line;
			text.append( d );
		}

		if ( 'shows' === type ) {
			const aka = altNamesMatching( hit.alt_names, q );
			if ( aka.length ) {
				const p = document.createElement( 'span' );
				p.className = 'lwtv-meili-aka';
				p.textContent = fmt( strings.aka, aka.join( ', ' ) );
				text.append( p );
			}
		}

		a.append( text );
		li.append( a );
		return li;
	}

	/**
	 * Draw one tab's hits.
	 *
	 * @param {Object} t      Tab entry.
	 * @param {Object} result Meilisearch result.
	 * @param {string} q      Search term.
	 */
	function drawPanel( t, result, q ) {
		if ( ! result.total ) {
			panelMessage( t, fmt( strings.noResults, q ), '', '' );
			return;
		}

		const ul = document.createElement( 'ul' );
		ul.className = 'lwtv-meili-results';
		result.hits.forEach( ( hit ) => ul.append( row( t.type, hit, q ) ) );

		const more = document.createElement( 'p' );
		more.className = 'lwtv-meili-seeall';
		const link = document.createElement( 'a' );
		link.href = searchUrl( q );
		link.textContent = strings.seeAll;
		more.append( link );

		t.panel.replaceChildren( ul, more );
		// The links are focusable, so the panel needn't be a tab stop.
		t.panel.removeAttribute( 'tabindex' );
	}

	/**
	 * Draw a completed search.
	 *
	 * @param {Array}  raw Meilisearch results, in tab order.
	 * @param {string} q   Search term.
	 */
	function draw( raw, q ) {
		// Focus in the tabs or results means the visitor is driving: don't
		// auto-select, and put focus back on the same row after redrawing.
		const active = dialog.ownerDocument.activeElement;
		const driving =
			!! active && active !== input && dialog.contains( active );
		let rowType = null;
		let rowIndex = -1;
		tabs.forEach( ( t ) => {
			const at = [
				...t.panel.querySelectorAll( '.lwtv-meili-row' ),
			].indexOf( active );
			if ( -1 !== at ) {
				rowType = t.type;
				rowIndex = at;
			}
		} );

		const results = [ ...tabs.values() ].map( ( t, i ) => {
			const r = raw[ i ] || {};
			const hits = Array.isArray( r.hits ) ? r.hits : [];
			return {
				type: t.type,
				label: t.label,
				one: t.one,
				many: t.many,
				hits,
				total:
					Number(
						r.estimatedTotalHits ?? r.totalHits ?? hits.length
					) || 0,
			};
		} );

		results.forEach( ( r ) => {
			const t = tabs.get( r.type );
			setCount( t, r.total );
			drawPanel( t, r, q );
		} );

		if ( ! userPicked && ! driving ) {
			const best = strongestTab( results, order );
			if ( best ) {
				select( best, false );
			}
		}

		if ( rowType ) {
			const rows = tabs
				.get( rowType )
				.panel.querySelectorAll( '.lwtv-meili-row' );
			( rows[ Math.min( rowIndex, rows.length - 1 ) ] || input ).focus();
		}

		status.textContent = statusText( results, selected, strings, q );
	}

	/**
	 * Open the SearchWP modal with the query, or say search is unavailable.
	 *
	 * @param {string} q Search term.
	 */
	function openFallback( q ) {
		const trigger = fallbackTrigger();

		if ( ! trigger ) {
			tabs.forEach( ( t ) =>
				panelMessage(
					t,
					strings.unavailable,
					strings.searchPage,
					searchUrl( q )
				)
			);
			status.textContent = strings.unavailable;
			if ( ! dialog.open ) {
				dialog.showModal();
				input.focus();
			}
			return;
		}

		if ( dialog.open ) {
			dialog.close();
		}
		// SearchWP returns focus to whatever had it when its modal opened.
		if ( opener && opener.isConnected ) {
			opener.focus();
		}
		trigger.click();
		const announcer = document.getElementById( 'lwtv-meili-announce' );
		if ( announcer ) {
			announcer.textContent = strings.fallback;
		}

		const modalId = trigger.getAttribute( 'data-searchwp-modal-trigger' );
		const started = Date.now();
		const fill = () => {
			const modal = modalId ? document.getElementById( modalId ) : null;
			const field = modal?.querySelector( 'input[data-swplive]' );
			const shown =
				modal && 'true' !== modal.getAttribute( 'aria-hidden' );
			if ( field && shown ) {
				if ( q ) {
					field.value = q;
					field.dispatchEvent(
						new Event( 'input', { bubbles: true } )
					);
					// SearchWP Live Search listens for keyup.
					field.dispatchEvent(
						new KeyboardEvent( 'keyup', { bubbles: true } )
					);
				}
				return;
			}
			if ( Date.now() - started < 1000 ) {
				window.setTimeout( fill, 50 );
			}
		};
		fill();
	}

	/**
	 * Meilisearch failed: log (never the key) and hand over to SearchWP.
	 *
	 * @param {string|number} reason HTTP status or error name.
	 * @param {string}        q      Search term.
	 */
	function fail( reason, q ) {
		// eslint-disable-next-line no-console
		console.warn( 'LWTV search: falling back to SearchWP', reason );
		fellBack = true;
		openFallback( q );
	}

	/**
	 * Run a search for the input's current value.
	 */
	async function search() {
		const q = input.value.trim();
		const mine = ++seq;
		if ( controller ) {
			controller.abort();
			controller = null;
		}

		if ( '' === q ) {
			clear();
			return;
		}

		const ctrl = new AbortController();
		controller = ctrl;
		let timedOut = false;
		let timeout = 0;

		try {
			timeout = window.setTimeout( () => {
				timedOut = true;
				ctrl.abort();
			}, cfg.timeout );
			const res = await fetch( cfg.url + '/multi-search', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					Authorization: 'Bearer ' + cfg.key,
				},
				body: JSON.stringify( {
					queries: [ ...tabs.values() ].map( ( t ) => ( {
						indexUid: t.uid,
						q,
						limit: cfg.limit,
						matchingStrategy: 'frequency',
						showRankingScore: true,
						attributesToRetrieve: FIELDS[ t.type ],
					} ) ),
				} ),
				signal: ctrl.signal,
			} );
			if ( mine !== seq ) {
				return;
			}
			if ( ! res.ok ) {
				fail( res.status, q );
				return;
			}
			const data = await res.json();
			if ( mine !== seq ) {
				return;
			}
			draw( Array.isArray( data.results ) ? data.results : [], q );
		} catch ( err ) {
			// A newer search (or closing the modal) aborted this one.
			if ( mine !== seq ) {
				return;
			}
			fail( timedOut ? 'timeout' : err?.name || 'error', q );
		} finally {
			window.clearTimeout( timeout );
			if ( controller === ctrl ) {
				controller = null;
			}
		}
	}

	/**
	 * Open the modal from a trigger.
	 *
	 * @param {Element} trigger The button that was used.
	 */
	function open( trigger ) {
		if ( fellBack ) {
			openFallback( input.value.trim() );
			return;
		}
		opener = trigger;
		userPicked = false;
		dialog.showModal();
		input.focus();
		if ( input.value.trim() ) {
			input.select();
			search();
		}
	}

	document.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest?.( '[data-lwtv-meili-open]' );
		if ( trigger ) {
			event.preventDefault();
			open( trigger );
		}
	} );

	dialog.addEventListener( 'close', () => {
		window.clearTimeout( timer );
		seq++;
		if ( controller ) {
			controller.abort();
			controller = null;
		}
		if ( opener && opener.isConnected && ! fellBack ) {
			opener.focus();
		}
	} );

	// A click on the backdrop (the dialog itself, outside its box) closes it.
	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );

	dialog
		.querySelectorAll( '[data-lwtv-meili-close]' )
		.forEach( ( btn ) =>
			btn.addEventListener( 'click', () => dialog.close() )
		);

	input.addEventListener( 'input', () => {
		window.clearTimeout( timer );
		if ( fellBack ) {
			// Keep the "Try the search page" links on the current query.
			const href = searchUrl( input.value.trim() );
			tabs.forEach( ( t ) =>
				t.panel
					.querySelectorAll( '.lwtv-meili-message a' )
					.forEach( ( a ) => a.setAttribute( 'href', href ) )
			);
			return;
		}
		timer = window.setTimeout( search, cfg.debounce );
	} );

	input.addEventListener( 'keydown', ( event ) => {
		// Keys during IME composition belong to the IME (Japanese, Korean
		// and Chinese titles): Escape cancels the conversion, Down picks a
		// candidate.
		if ( event.isComposing || 229 === event.keyCode ) {
			return;
		}
		// A filled search field spends the first Escape clearing itself;
		// Escape here closes the modal, as everywhere else in it.
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			dialog.close();
			return;
		}
		if ( 'ArrowDown' !== event.key ) {
			return;
		}
		const first = tabs.get( selected )?.panel.querySelector( 'a' );
		if ( first ) {
			event.preventDefault();
			first.focus();
		}
	} );

	tabs.forEach( ( t ) => {
		t.tab.addEventListener( 'click', () => {
			userPicked = true;
			select( t.type, true );
		} );

		t.panel.addEventListener( 'keydown', ( event ) => {
			if ( 'ArrowDown' !== event.key && 'ArrowUp' !== event.key ) {
				return;
			}
			const links = [ ...t.panel.querySelectorAll( '.lwtv-meili-row' ) ];
			const at = links.indexOf( t.panel.ownerDocument.activeElement );
			if ( -1 === at ) {
				return;
			}
			event.preventDefault();
			if ( 'ArrowUp' === event.key ) {
				( 0 === at ? input : links[ at - 1 ] ).focus();
			} else if ( at < links.length - 1 ) {
				links[ at + 1 ].focus();
			}
		} );
	} );

	tablist.addEventListener( 'keydown', ( event ) => {
		const at = order.indexOf( selected );
		let next = null;
		if ( 'ArrowRight' === event.key ) {
			next = order[ ( at + 1 ) % order.length ];
		} else if ( 'ArrowLeft' === event.key ) {
			next = order[ ( at - 1 + order.length ) % order.length ];
		} else if ( 'Home' === event.key ) {
			next = order[ 0 ];
		} else if ( 'End' === event.key ) {
			next = order[ order.length - 1 ];
		}
		if ( next ) {
			event.preventDefault();
			userPicked = true;
			select( next, true );
		}
	} );

	select( selected, false );
	clear();
	return true;
}

const configEl = document.getElementById( 'lwtv-meili-config' );
if ( configEl ) {
	let cfg = null;
	try {
		cfg = JSON.parse( configEl.textContent );
	} catch ( err ) {
		// eslint-disable-next-line no-console
		console.warn( 'LWTV search: bad config', err?.name );
	}
	try {
		if ( cfg && Array.isArray( cfg.tabs ) && start( cfg ) ) {
			// Tells the inline guard in searchbox-meili.php to stand down.
			document.documentElement.dataset.lwtvMeiliReady = '1';
		}
	} catch ( err ) {
		// eslint-disable-next-line no-console
		console.warn( 'LWTV search: could not start', err?.name );
	}
}
