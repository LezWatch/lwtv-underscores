/**
 * Pure helpers for the Search Rules app: rows in, payloads out.
 */

let nextKey = 0;

/**
 * Whether a value can be a word: a string or a whole number, as the server
 * accepts. Anything else in an imported file is dropped, not turned into text.
 *
 * @param {unknown} value Value.
 * @return {boolean} Whether it's a word.
 */
const isWord = ( value ) =>
	typeof value === 'string' || Number.isInteger( value );

/**
 * A unique React key for a new row.
 *
 * @return {string} Key.
 */
export const newKey = () => `row-${ ++nextKey }`;

/**
 * Editor rows from saved synonyms.
 *
 * @param {Object} synonyms { two_way, one_way }.
 * @return {{twoWay: Array, oneWay: Array}} Rows.
 */
export function toRows( synonyms ) {
	const twoWay = Array.isArray( synonyms?.two_way ) ? synonyms.two_way : [];
	const oneWay = Array.isArray( synonyms?.one_way ) ? synonyms.one_way : [];

	return {
		twoWay: twoWay.map( ( words ) => ( {
			key: newKey(),
			words: Array.isArray( words )
				? words.filter( isWord ).map( String )
				: [],
		} ) ),
		oneWay: oneWay.map( ( entry ) => ( {
			key: newKey(),
			from: isWord( entry?.from ) ? String( entry.from ) : '',
			to: Array.isArray( entry?.to )
				? entry.to.filter( isWord ).map( String )
				: [],
		} ) ),
	};
}

/**
 * The synonyms payload, rows in the order they're shown, so the server's
 * row numbers match the rows on screen.
 *
 * @param {Array} twoWay Two-way rows.
 * @param {Array} oneWay One-way rows.
 * @return {Object} { two_way, one_way }.
 */
export function toSynonyms( twoWay, oneWay ) {
	return {
		two_way: twoWay.map( ( row ) => row.words ),
		one_way: oneWay.map( ( row ) => ( { from: row.from, to: row.to } ) ),
	};
}

/**
 * Parse an exported file. Accepts { version, synonyms } or bare synonyms.
 * The server validates everything again on save; this only checks shape.
 *
 * @param {string} text File contents.
 * @return {Object|null} Synonyms, or null when the file isn't a rules file.
 */
export function parseImport( text ) {
	let data;
	try {
		data = JSON.parse( text );
	} catch {
		return null;
	}

	const synonyms =
		data && typeof data === 'object' && data.synonyms
			? data.synonyms
			: data;

	if (
		! synonyms ||
		typeof synonyms !== 'object' ||
		Array.isArray( synonyms ) ||
		( synonyms.two_way !== undefined &&
			! Array.isArray( synonyms.two_way ) ) ||
		( synonyms.one_way !== undefined &&
			! Array.isArray( synonyms.one_way ) ) ||
		( synonyms.two_way === undefined && synonyms.one_way === undefined )
	) {
		return null;
	}

	return {
		two_way: synonyms.two_way || [],
		one_way: synonyms.one_way || [],
	};
}

/**
 * Messages for one row.
 *
 * @param {Array}  messages Errors or warnings from the API.
 * @param {string} kind     two_way or one_way.
 * @param {number} row      Row index.
 * @return {Array} Messages.
 */
export const forRow = ( messages, kind, row ) =>
	messages.filter( ( m ) => m.kind === kind && m.row === row );

/**
 * A summary of the applied status.
 *
 * @param {Object} applied { at, indexes: { type: { status, task } } }.
 * @return {{ok: number, setUp: number, applying: boolean, notSetUp: string[], failed: Array}} Summary.
 */
export function summarize( applied ) {
	const entries = Object.entries( applied?.indexes || {} );
	const notSetUp = [];
	const failed = [];
	let ok = 0;
	let applying = false;

	for ( const [ type, entry ] of entries ) {
		const status = entry?.status || '';
		if ( status === 'not set up' ) {
			notSetUp.push( type );
		} else if ( status === 'ok' ) {
			ok++;
		} else if ( status === 'applying' ) {
			applying = true;
		} else {
			failed.push( { type, status } );
		}
	}

	return {
		ok,
		setUp: entries.length - notSetUp.length,
		applying,
		notSetUp,
		failed,
	};
}
