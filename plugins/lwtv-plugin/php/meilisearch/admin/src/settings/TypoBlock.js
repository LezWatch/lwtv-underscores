/**
 * One index's typo settings: the settings card (with Save and apply and
 * Reset), and a Test a search card.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, Notice } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { isRunning, jobText } from '../shared/jobText';
import WordChips from './WordChips';
import { REST } from './config';

const TEST_PATH = 'lwtv/v1/meili-rules/test';

// The six settings, in the order the "unsaved changes" count reads them.
const FIELDS = [
	'enabled',
	'oneTypo',
	'twoTypos',
	'disableOnNumbers',
	'disableOnAttributes',
	'disableOnWords',
];

/**
 * The form's draft from a saved block: numbers as text, so a half-typed
 * value can be edited and the server refuses anything that isn't valid.
 *
 * @param {Object} settings A saved block.
 * @return {Object} Draft.
 */
const toDraft = ( settings ) => ( {
	...settings,
	oneTypo: String( settings.oneTypo ),
	twoTypos: String( settings.twoTypos ),
} );

/**
 * Send what was typed; the server refuses anything that isn't a whole number in range.
 *
 * @param {string} value Typed number.
 * @return {number|string} Number when it's whole digits.
 */
const asNumber = ( value ) =>
	/^\d+$/.test( value ) ? Number( value ) : value;

/**
 * A block in one comparable shape: numbers as numbers, lists sorted, so
 * ticking fields in another order doesn't count as an edit.
 *
 * @param {Object} block Draft or saved block.
 * @return {Object} Comparable block.
 */
const normal = ( block ) => ( {
	...block,
	oneTypo: asNumber( String( block.oneTypo ) ),
	twoTypos: asNumber( String( block.twoTypos ) ),
	disableOnWords: [ ...block.disableOnWords ].sort(),
	disableOnAttributes: [ ...block.disableOnAttributes ].sort(),
} );

/**
 * Whether two blocks hold the same settings (lists in any order).
 *
 * @param {Object} a Draft or saved block.
 * @param {Object} b Draft or saved block.
 * @return {boolean} Same.
 */
const same = ( a, b ) =>
	JSON.stringify( normal( a ) ) === JSON.stringify( normal( b ) );

/**
 * How many of the six settings differ.
 *
 * @param {Object} draft Draft.
 * @param {Object} saved Saved block.
 * @return {number} Changed settings.
 */
const changes = ( draft, saved ) => {
	const a = normal( draft );
	const b = normal( saved );
	return FIELDS.filter(
		( f ) => JSON.stringify( a[ f ] ) !== JSON.stringify( b[ f ] )
	).length;
};

/**
 * A word the way the server stores it: spaces collapsed, trimmed, lowercased.
 *
 * @param {string} w Word.
 * @return {string} Word.
 */
const cleanWord = ( w ) => w.replace( /\s+/gu, ' ' ).trim().toLowerCase();

/**
 * The server's checks on the two lengths, run as you type.
 *
 * @param {Object} draft  Draft.
 * @param {Object} limits Limits from the API.
 * @return {Object} Errors by field.
 */
export function checkLengths( draft, limits ) {
	const errors = {};
	const one = /^\d+$/.test( draft.oneTypo ) ? Number( draft.oneTypo ) : NaN;
	const two = /^\d+$/.test( draft.twoTypos ) ? Number( draft.twoTypos ) : NaN;
	const oneOk = one >= limits.one_min && one <= limits.one_max;
	if ( ! oneOk ) {
		errors.oneTypo = sprintf(
			/* translators: 1: fewest letters, 2: most letters. */
			__(
				'One typo needs a whole number of letters from %1$d to %2$d.',
				'lwtv-underscores'
			),
			limits.one_min,
			limits.one_max
		);
	}
	if (
		! ( two > limits.one_min && two <= limits.two_max ) ||
		( oneOk && two <= one )
	) {
		errors.twoTypos = sprintf(
			/* translators: %d: most letters. */
			__(
				'Two typos needs a whole number of letters above the one-typo length, up to %d.',
				'lwtv-underscores'
			),
			limits.two_max
		);
	}
	return errors;
}

/**
 * The example line, from the two lengths.
 *
 * @param {number} one Letters for one typo.
 * @param {number} two Letters for two typos.
 * @return {string} Example.
 */
export function exampleText( one, two ) {
	if ( one <= 1 ) {
		return sprintf(
			/* translators: %d: a word length that can have two typos. */
			__(
				'Example: any word can have one typo, and a %d-letter word can have two.',
				'lwtv-underscores'
			),
			two + 1
		);
	}
	return sprintf(
		/* translators: 1: a length that must be exact, 2: a length with one typo, 3: a length with two typos. */
		__(
			'Example: a %1$d-letter word must be exact, a %2$d-letter word can have one typo, and a %3$d-letter word can have two.',
			'lwtv-underscores'
		),
		one - 1,
		Math.floor( ( one + two ) / 2 ),
		two + 1
	);
}

/**
 * What happened to a save, in words.
 *
 * @param {string} applied started, job_running, not_set_up, not_applied or unreachable.
 * @param {string} label   Index label.
 * @param {string} reason  Why it wasn't applied (not_applied only).
 * @return {{status: string, message: string}} Notice.
 */
function appliedNotice( applied, label, reason ) {
	switch ( applied ) {
		case 'started':
			return {
				status: 'success',
				message: sprintf(
					/* translators: %s: index label. */
					__(
						'Saved. Applying to %s in the background.',
						'lwtv-underscores'
					),
					label
				),
			};
		case 'job_running':
			return {
				status: 'warning',
				message: __(
					'Saved, but not applied: a job is running on this index. Use Apply settings on the Status tab when it finishes.',
					'lwtv-underscores'
				),
			};
		case 'not_set_up':
			return {
				status: 'info',
				message: __(
					'Saved. It applies when this index is set up.',
					'lwtv-underscores'
				),
			};
		case 'not_applied':
			return {
				status: 'warning',
				message: sprintf(
					/* translators: %s: why the settings weren't applied. */
					__(
						'Saved, but not applied: %s Use Apply settings on the Status tab.',
						'lwtv-underscores'
					),
					reason
				),
			};
		default:
			return {
				status: 'warning',
				message: __(
					'Saved, but not applied: couldn’t reach the search server. Use Apply settings on the Status tab once it’s back.',
					'lwtv-underscores'
				),
			};
	}
}

/**
 * The pill for the last job: what it's doing, or how it ended.
 *
 * @param {?Object} job Last job.
 * @return {?{className: string, text: string}} Pill, or null.
 */
function pill( job ) {
	if ( isRunning( job ) ) {
		const text =
			{
				reindex: __( 'Reindexing…', 'lwtv-underscores' ),
				setup: __( 'Setting up…', 'lwtv-underscores' ),
			}[ job.kind ] || __( 'Applying…', 'lwtv-underscores' );
		return { className: 'is-pending', text };
	}
	if ( 'failed' === job?.state ) {
		return {
			className: 'is-failed',
			text: __( 'Failed', 'lwtv-underscores' ),
		};
	}
	if ( 'done' === job?.state ) {
		return {
			className: 'is-applied',
			text: __( 'Applied', 'lwtv-underscores' ),
		};
	}
	return null;
}

export default function TypoBlock( {
	row,
	hidden,
	limits,
	say,
	onSaved,
	onDirty,
} ) {
	const saved = JSON.stringify( row.settings );
	const [ draft, setDraft ] = useState( () => toDraft( row.settings ) );
	const [ errors, setErrors ] = useState( {} );
	const [ wordError, setWordError ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ confirming, setConfirming ] = useState( false );
	const [ q, setQ ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const heading = useRef( null );
	const id = `lwtv-typo-${ row.type }`;

	// New saved values (a save here, or one made elsewhere and picked up by a
	// reload or poll): follow them, unless that would throw away edits.
	const [ outside, setOutside ] = useState( false );
	const latestDraft = useRef( draft );
	latestDraft.current = draft;
	const lastSaved = useRef( saved );
	useEffect( () => {
		const before = JSON.parse( lastSaved.current );
		const next = JSON.parse( saved );
		lastSaved.current = saved;
		const now = latestDraft.current;
		if ( same( now, next ) ) {
			// Already in step (our own save, or the same change).
			setOutside( false );
		} else if ( same( now, before ) ) {
			// Nothing edited: just follow.
			setDraft( toDraft( next ) );
			setOutside( false );
		} else {
			// Edits in progress: keep them, and say so.
			setOutside( true );
			say(
				sprintf(
					/* translators: %s: index label. */
					__(
						'The %s typo settings were saved somewhere else. Your unsaved changes are kept.',
						'lwtv-underscores'
					),
					row.label
				)
			);
		}
	}, [ saved, say, row.label ] );

	const loadSaved = () => {
		setDraft( toDraft( row.settings ) );
		setErrors( {} );
		setWordError( '' );
		setOutside( false );
		say(
			sprintf(
				/* translators: %s: index label. */
				__( 'Loaded the saved settings for %s.', 'lwtv-underscores' ),
				row.label
			)
		);
		heading.current?.focus();
	};

	const changed = changes( draft, row.settings );
	const isDirty = changed > 0;
	useEffect( () => {
		onDirty( row.type, isDirty );
	}, [ row.type, isDirty, onDirty ] );

	const running = isRunning( row.job );
	const locked = running || busy;
	const checks = checkLengths( draft, limits );
	const valid = 0 === Object.keys( checks ).length;
	// Server errors last until their field is edited; the length checks run live.
	const shown = { ...errors, ...checks };
	const badge = pill( row.job );
	const jobWord =
		{
			reindex: __( 'reindexing', 'lwtv-underscores' ),
			setup: __( 'setting up', 'lwtv-underscores' ),
		}[ row.job?.kind ] || __( 'applying', 'lwtv-underscores' );

	// Editing a field clears the server's error on it.
	const clearError = ( field ) =>
		setErrors( ( current ) => {
			if ( ! current[ field ] ) {
				return current;
			}
			const next = { ...current };
			delete next[ field ];
			return next;
		} );

	const set = ( field ) => ( value ) => {
		setDraft( ( current ) => ( { ...current, [ field ]: value } ) );
		clearError( field );
	};

	// The "Saved. Applying…" notice goes once that apply has finished.
	const wasRunning = useRef( running );
	useEffect( () => {
		if ( wasRunning.current && ! running ) {
			setNotice( ( current ) =>
				'success' === current?.status ? null : current
			);
		}
		wasRunning.current = running;
	}, [ running ] );

	const send = ( path, body ) => {
		setBusy( true );
		setErrors( {} );
		setNotice( null );
		apiFetch( { path, method: 'POST', data: body } )
			.then( ( next ) => {
				onSaved( next.index );
				// Old results no longer match what will be live.
				setResults( null );
				// Always start again from what was saved, even when the server
				// cleaned the draft into the values it already had.
				setDraft( toDraft( next.index.settings ) );
				setOutside( false );
				const n = appliedNotice( next.applied, row.label, next.reason );
				setNotice( n );
				say( n.message );
				// The control that had focus may be gone (a removed field): keep focus on the block.
				heading.current?.focus();
			} )
			.catch( ( e ) => {
				setErrors( e?.data?.errors || {} );
				const message =
					e?.message ||
					__(
						'Could not save the typo settings.',
						'lwtv-underscores'
					);
				setNotice( { status: 'error', message } );
				say( message );
			} )
			.finally( () => setBusy( false ) );
	};

	const save = () =>
		send( `/${ REST }/typo`, {
			type: row.type,
			settings: {
				...draft,
				oneTypo: asNumber( draft.oneTypo ),
				twoTypos: asNumber( draft.twoTypos ),
			},
		} );

	// Customised: the server saves and applies the defaults. Defaults with
	// only unsaved edits: just put the form back.
	const reset = () => {
		setConfirming( false );
		if ( row.is_default ) {
			setDraft( toDraft( row.settings ) );
			setOutside( false );
			setErrors( {} );
			setWordError( '' );
			say(
				sprintf(
					/* translators: %s: index label. */
					__(
						'Unsaved changes to %s discarded.',
						'lwtv-underscores'
					),
					row.label
				)
			);
			heading.current?.focus();
			return;
		}
		send( `/${ REST }/typo/reset`, { type: row.type } );
	};

	const test = () => {
		if ( ! q.trim() || running ) {
			return;
		}
		apiFetch( {
			path: `/${ TEST_PATH }?q=${ encodeURIComponent( q.trim() ) }`,
		} )
			.then( ( data ) => {
				const mine = ( data.results || [] ).find(
					( r ) => r.type === row.type
				);
				const hits = ( mine?.hits || [] ).slice( 0, 3 );
				setResults( hits );
				say(
					hits.length
						? hits.map( ( h ) => h.title ).join( ', ' )
						: __( 'No results', 'lwtv-underscores' )
				);
			} )
			.catch( () => {
				setResults( [] );
				say( __( 'The test search failed.', 'lwtv-underscores' ) );
			} );
	};

	const toggleAttribute = ( name, checked ) => {
		setDraft( ( current ) => ( {
			...current,
			disableOnAttributes: checked
				? [ ...current.disableOnAttributes, name ]
				: current.disableOnAttributes.filter( ( a ) => a !== name ),
		} ) );
		clearError( 'disableOnAttributes' );
	};

	// Cleaned the server's way, de-duplicated and limited; then say what happened.
	const setWords = ( list ) => {
		const words = list.map( cleanWord ).filter( Boolean );
		const fits = ( w ) => [ ...w ].length <= limits.word_max;
		const unique = [ ...new Set( words.filter( fits ) ) ];
		const kept = unique.slice( 0, limits.words_max );
		const before = draft.disableOnWords;
		const added = kept.filter( ( w ) => ! before.includes( w ) );

		let problem = '';
		if ( words.some( ( w ) => ! fits( w ) ) ) {
			problem = sprintf(
				/* translators: %d: longest word in characters. */
				__(
					'Not added: each word can be up to %d characters.',
					'lwtv-underscores'
				),
				limits.word_max
			);
		} else if ( unique.length > kept.length ) {
			problem = sprintf(
				/* translators: %d: most words. */
				__(
					'Not added: there can be up to %d words.',
					'lwtv-underscores'
				),
				limits.words_max
			);
		} else if ( ! added.length && kept.length >= before.length ) {
			problem = __( 'Already in the list.', 'lwtv-underscores' );
		}
		setWordError( problem );

		const said = [];
		if ( added.length ) {
			said.push(
				sprintf(
					/* translators: %s: words, comma-separated. */
					__( 'Added %s.', 'lwtv-underscores' ),
					added.join( ', ' )
				)
			);
		}
		if ( problem ) {
			said.push( problem );
		}
		if ( said.length ) {
			say( said.join( ' ' ) );
		}
		set( 'disableOnWords' )( kept );
	};

	const removedWord = ( word ) => {
		setWordError( '' );
		say(
			sprintf(
				/* translators: %s: a word. */
				__( 'Removed “%s”.', 'lwtv-underscores' ),
				word
			)
		);
	};

	const errorId = ( field ) => `${ id }-${ field }-error`;
	const error = ( field ) =>
		shown[ field ] ? (
			<p className="lwtv-settings__error" id={ errorId( field ) }>
				{ shown[ field ] }
			</p>
		) : null;
	const numberProps = ( field ) => ( {
		id: `${ id }-${ field }`,
		type: 'number',
		inputMode: 'numeric',
		className: 'lwtv-settings__typo-number',
		value: draft[ field ],
		onChange: ( e ) => set( field )( e.target.value ),
		// Off greys the lengths out, unless one is invalid: it must stay fixable.
		disabled: locked || ( ! draft.enabled && ! shown[ field ] ),
		'aria-invalid': shown[ field ] ? true : undefined,
		'aria-describedby': shown[ field ] ? errorId( field ) : undefined,
	} );

	let barText = __( 'No changes to save', 'lwtv-underscores' );
	if ( running ) {
		barText = sprintf(
			/* translators: %s: index label. */
			__( 'Applying settings to %s…', 'lwtv-underscores' ),
			row.label
		);
	} else if ( isDirty ) {
		barText = sprintf(
			/* translators: %d: number of changed settings. */
			_n(
				'%d unsaved change',
				'%d unsaved changes',
				changed,
				'lwtv-underscores'
			),
			changed
		);
	}

	let testHelp = __( 'Tests what is live on the index.', 'lwtv-underscores' );
	if ( running ) {
		testHelp = sprintf(
			/* translators: 1: index label, 2: applying, reindexing or setting up. */
			__( 'Available once %1$s finishes %2$s.', 'lwtv-underscores' ),
			row.label,
			jobWord
		);
	} else if ( isDirty ) {
		testHelp = __(
			'Tests the saved settings. Your unsaved changes are not included.',
			'lwtv-underscores'
		);
	}

	return (
		<div className="lwtv-settings__typo-block" hidden={ hidden }>
			{ outside && isDirty && (
				<Notice
					status="warning"
					isDismissible={ false }
					actions={ [
						{
							label: __(
								'Load the saved settings',
								'lwtv-underscores'
							),
							onClick: loadSaved,
							variant: 'secondary',
						},
					] }
				>
					{ sprintf(
						/* translators: %s: index label. */
						__(
							'The %s typo settings were saved somewhere else (another tab, or another admin) while you were editing. Your unsaved changes are kept; Save and apply would replace what was saved.',
							'lwtv-underscores'
						),
						row.label
					) }
				</Notice>
			) }
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<section
				className="lwtv-settings__card lwtv-settings__typo-card"
				aria-labelledby={ `${ id }-heading` }
				aria-busy={ running ? true : undefined }
			>
				<div className="lwtv-settings__card-head">
					<h2
						ref={ heading }
						tabIndex={ -1 }
						id={ `${ id }-heading` }
					>
						{ row.label }
						{ badge && (
							<span
								className={ `lwtv-settings__pill ${ badge.className }` }
							>
								{ badge.text }
							</span>
						) }
					</h2>
					<p className="lwtv-settings__total">
						{ row.is_default
							? __( 'Using the defaults', 'lwtv-underscores' )
							: __( 'Customised', 'lwtv-underscores' ) }
					</p>
				</div>

				{ 'failed' === row.job?.state && (
					<p className="lwtv-settings__job-failed">
						{ jobText( row.job ) }
					</p>
				) }

				{ shown.settings && (
					<div className="lwtv-settings__typo-section">
						{ error( 'settings' ) }
					</div>
				) }

				<div
					className={ `lwtv-settings__typo-form${
						running ? ' is-locked' : ''
					}` }
				>
					<div className="lwtv-settings__typo-section">
						<h3>{ __( 'Typos', 'lwtv-underscores' ) }</h3>
						<div className="lwtv-settings__typo-content">
							<Check
								id={ `${ id }-enabled` }
								label={ __(
									'Allow typos',
									'lwtv-underscores'
								) }
								help={
									draft.enabled
										? __(
												'Searches match words with small spelling mistakes.',
												'lwtv-underscores'
											)
										: __(
												'Off: only exact spellings match. The settings below are kept for when it’s back on.',
												'lwtv-underscores'
											)
								}
								checked={ draft.enabled }
								disabled={ locked }
								onChange={ set( 'enabled' ) }
							/>
							{ error( 'enabled' ) }

							<p className="lwtv-settings__typo-sentence">
								{ __( 'Allow', 'lwtv-underscores' ) }{ ' ' }
								<strong>
									{ __( 'one typo', 'lwtv-underscores' ) }
								</strong>{ ' ' }
								{ __(
									'in words of at least',
									'lwtv-underscores'
								) }{ ' ' }
								<input
									{ ...numberProps( 'oneTypo' ) }
									min={ limits.one_min }
									max={ limits.one_max }
									aria-label={ __(
										'Letters needed for one typo',
										'lwtv-underscores'
									) }
								/>{ ' ' }
								{ __( 'letters.', 'lwtv-underscores' ) }
							</p>
							{ error( 'oneTypo' ) }
							<p className="lwtv-settings__typo-sentence">
								{ __( 'Allow', 'lwtv-underscores' ) }{ ' ' }
								<strong>
									{ __( 'two typos', 'lwtv-underscores' ) }
								</strong>{ ' ' }
								{ __(
									'in words of at least',
									'lwtv-underscores'
								) }{ ' ' }
								<input
									{ ...numberProps( 'twoTypos' ) }
									min={ limits.one_min + 1 }
									max={ limits.two_max }
									aria-label={ __(
										'Letters needed for two typos',
										'lwtv-underscores'
									) }
								/>{ ' ' }
								{ __( 'letters.', 'lwtv-underscores' ) }
							</p>
							{ error( 'twoTypos' ) }
							<p className="lwtv-settings__help">
								{ __(
									'Shorter words must be spelled exactly. A wrong first letter counts as two typos.',
									'lwtv-underscores'
								) }
							</p>
							{ draft.enabled && valid && (
								<p className="lwtv-settings__example">
									{ exampleText(
										Number( draft.oneTypo ),
										Number( draft.twoTypos )
									) }
								</p>
							) }

							<Check
								id={ `${ id }-numbers` }
								label={ __(
									'No typos in numbers',
									'lwtv-underscores'
								) }
								help={ __(
									'Numbers such as years must match exactly.',
									'lwtv-underscores'
								) }
								checked={ draft.disableOnNumbers }
								disabled={ locked }
								onChange={ set( 'disableOnNumbers' ) }
							/>
							{ error( 'disableOnNumbers' ) }
						</div>
					</div>

					<div className="lwtv-settings__typo-section">
						<h3>{ __( 'Exact fields', 'lwtv-underscores' ) }</h3>
						<div className="lwtv-settings__typo-content">
							<fieldset
								aria-describedby={
									shown.disableOnAttributes
										? errorId( 'disableOnAttributes' )
										: undefined
								}
							>
								<legend>
									{ __(
										'Never allow typos in these fields',
										'lwtv-underscores'
									) }
								</legend>
								<div className="lwtv-settings__typo-fields">
									{ row.attributes.map( ( name ) => (
										<label
											key={ name }
											htmlFor={ `${ id }-field-${ name }` }
										>
											<input
												id={ `${ id }-field-${ name }` }
												type="checkbox"
												checked={ draft.disableOnAttributes.includes(
													name
												) }
												disabled={ locked }
												onChange={ ( e ) =>
													toggleAttribute(
														name,
														e.target.checked
													)
												}
											/>
											<code>{ name }</code>
										</label>
									) ) }
								</div>
							</fieldset>
							{ row.removed.length > 0 && (
								<p className="lwtv-settings__help">
									{ row.removed
										.map( ( name ) =>
											sprintf(
												/* translators: %s: field name. */
												__(
													'%s (removed: no longer searchable)',
													'lwtv-underscores'
												),
												name
											)
										)
										.join( ', ' ) }
								</p>
							) }
							{ error( 'disableOnAttributes' ) }
						</div>
					</div>

					<div className="lwtv-settings__typo-section">
						<h3>{ __( 'Exact words', 'lwtv-underscores' ) }</h3>
						<div className="lwtv-settings__typo-content">
							<WordChips
								id={ `${ id }-words` }
								label={ __(
									'Never allow typos in these words',
									'lwtv-underscores'
								) }
								placeholder={ __(
									'Type a word, then press Enter or a comma',
									'lwtv-underscores'
								) }
								words={ draft.disableOnWords }
								onChange={ setWords }
								onRemoved={ removedWord }
								disabled={ locked }
								full={
									draft.disableOnWords.length >=
									limits.words_max
								}
								describedBy={ [
									`${ id }-words-help`,
									wordError ? `${ id }-words-error` : '',
									shown.disableOnWords
										? errorId( 'disableOnWords' )
										: '',
								]
									.filter( Boolean )
									.join( ' ' ) }
							/>
							<div className="lwtv-settings__typo-words-foot">
								<p
									className="lwtv-settings__help"
									id={ `${ id }-words-help` }
								>
									{ sprintf(
										/* translators: %d: longest word in characters. */
										__(
											'For names that look like other words. Each word up to %d characters.',
											'lwtv-underscores'
										),
										limits.word_max
									) }
								</p>
								<p className="lwtv-settings__help lwtv-settings__num">
									{ sprintf(
										/* translators: 1: words added, 2: most words. */
										__(
											'%1$d of %2$d',
											'lwtv-underscores'
										),
										draft.disableOnWords.length,
										limits.words_max
									) }
								</p>
							</div>
							{ wordError && (
								<p
									className="lwtv-settings__error"
									id={ `${ id }-words-error` }
								>
									{ wordError }
								</p>
							) }
							{ error( 'disableOnWords' ) }
						</div>
					</div>
				</div>

				<div className="lwtv-settings__actionbar">
					<p
						className={ `lwtv-settings__actionbar-text${
							isDirty && ! running ? ' is-dirty' : ''
						}` }
						// While a job runs, say() already reports it.
						aria-live={ running ? 'off' : 'polite' }
					>
						{ barText }
					</p>
					<div className="lwtv-settings__actionbar-buttons">
						{ ( ! row.is_default || isDirty ) && (
							<Button
								variant="secondary"
								onClick={ () => setConfirming( true ) }
								disabled={ locked }
								accessibleWhenDisabled
							>
								{ __(
									'Reset to defaults',
									'lwtv-underscores'
								) }
							</Button>
						) }
						<Button
							variant="primary"
							onClick={ save }
							isBusy={ busy }
							disabled={ locked || ! isDirty || ! valid }
							accessibleWhenDisabled
						>
							{ __( 'Save and apply', 'lwtv-underscores' ) }
						</Button>
					</div>
				</div>
			</section>

			<section
				className="lwtv-settings__card lwtv-settings__typo-test"
				aria-labelledby={ `${ id }-test-heading` }
			>
				<div className="lwtv-settings__card-head">
					<h2 id={ `${ id }-test-heading` }>
						{ sprintf(
							/* translators: %s: index label. */
							__( 'Test a search in %s', 'lwtv-underscores' ),
							row.label
						) }
					</h2>
				</div>
				<div className="lwtv-settings__typo-section is-single">
					<div className="lwtv-settings__typo-test-row">
						<input
							type="search"
							value={ q }
							onChange={ ( e ) => setQ( e.target.value ) }
							onKeyDown={ ( e ) => {
								if ( 'Enter' === e.key ) {
									test();
								}
							} }
							disabled={ running }
							aria-label={ sprintf(
								/* translators: %s: index label. */
								__( 'Search %s', 'lwtv-underscores' ),
								row.label
							) }
							aria-describedby={ `${ id }-test-help` }
						/>
						<Button
							variant="secondary"
							onClick={ test }
							disabled={ running }
							accessibleWhenDisabled
						>
							{ __( 'Test', 'lwtv-underscores' ) }
						</Button>
					</div>
					{ results && (
						<ul className="lwtv-settings__typo-results">
							{ results.length === 0 && (
								<li>
									{ __( 'No results', 'lwtv-underscores' ) }
								</li>
							) }
							{ results.map( ( hit ) => (
								<li key={ hit.id }>{ hit.title }</li>
							) ) }
						</ul>
					) }
					<p
						className="lwtv-settings__help"
						id={ `${ id }-test-help` }
					>
						{ testHelp }
					</p>
				</div>
			</section>

			{ confirming && (
				<Modal
					title={ sprintf(
						/* translators: %s: index label. */
						__( 'Reset %s to the defaults?', 'lwtv-underscores' ),
						row.label
					) }
					onRequestClose={ () => setConfirming( false ) }
					size="small"
				>
					<p>
						{ row.is_default
							? __(
									'This discards your unsaved changes. The saved settings are already the defaults.',
									'lwtv-underscores'
								)
							: __(
									'This saves the default typo settings and applies them now. Your customised settings, and any unsaved changes, are lost.',
									'lwtv-underscores'
								) }
					</p>
					<div className="lwtv-settings__modal-buttons">
						<Button
							variant="tertiary"
							onClick={ () => setConfirming( false ) }
						>
							{ __( 'Cancel', 'lwtv-underscores' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ reset }
						>
							{ row.is_default
								? __( 'Discard changes', 'lwtv-underscores' )
								: __( 'Reset and apply', 'lwtv-underscores' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}

/**
 * A native checkbox with its label and help.
 *
 * @param {Object}                     props          Props.
 * @param {string}                     props.id       Input id.
 * @param {string}                     props.label    Label.
 * @param {string}                     props.help     Help text.
 * @param {boolean}                    props.checked  Checked.
 * @param {boolean}                    props.disabled Disabled.
 * @param {(checked: boolean) => void} props.onChange Change.
 * @return {Element} Checkbox.
 */
function Check( { id, label, help, checked, disabled, onChange } ) {
	return (
		<div className="lwtv-settings__check">
			<input
				type="checkbox"
				id={ id }
				checked={ checked }
				disabled={ disabled }
				onChange={ ( e ) => onChange( e.target.checked ) }
				aria-describedby={ `${ id }-help` }
			/>
			<div>
				<label htmlFor={ id }>{ label }</label>
				<p className="lwtv-settings__help" id={ `${ id }-help` }>
					{ help }
				</p>
			</div>
		</div>
	);
}
