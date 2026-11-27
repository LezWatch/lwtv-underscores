/**
 * Typo tolerance: an Indexes rail beside one block of typo settings per
 * index, saved then applied in the background. Loads each time the tab is
 * shown, and polls while a job runs.
 *
 * Design: docs/superpowers/specs/2026-11-27-meilisearch-typo-tab-design.md
 */
import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { isRunning } from '../shared/jobText';
import usePoll from '../shared/usePoll';
import TypoBlock from './TypoBlock';
import { POLL_MAX_MS, POLL_MS, REST } from './config';

// The rail's choice, kept in the URL so a reload comes back to it.
const PARAM = 'typo_index';

/**
 * The index named in the URL, if any.
 *
 * @return {string} Type, or ''.
 */
function fromUrl() {
	try {
		return new URL( window.location.href ).searchParams.get( PARAM ) || '';
	} catch {
		return '';
	}
}

/**
 * Put the chosen index in the URL without a reload.
 *
 * @param {string} type Index type.
 */
function toUrl( type ) {
	try {
		const url = new URL( window.location.href );
		url.searchParams.set( PARAM, type );
		window.history.replaceState( window.history.state, '', url );
	} catch {}
}

export default function TypoTab( { active, say } ) {
	const [ data, setData ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ selected, setSelected ] = useState( '' );
	const [ dirty, setDirty ] = useState( {} );
	const previous = useRef( {} );
	// Bumped by a save, so an older activation load can't land on top of it.
	const generation = useRef( 0 );

	const load = useCallback(
		() => apiFetch( { path: `/${ REST }/typo` } ),
		[]
	);

	// Say when a job we saw running has finished.
	const take = useCallback(
		( next ) => {
			for ( const row of next?.indexes || [] ) {
				const before = previous.current[ row.type ];
				if ( isRunning( before ) && ! isRunning( row.job ) ) {
					say(
						'failed' === row.job?.state
							? sprintf(
									/* translators: %s: index label. */
									__(
										'Typo settings failed to apply to %s.',
										'lwtv-underscores'
									),
									row.label
								)
							: sprintf(
									/* translators: %s: index label. */
									__(
										'Typo settings applied to %s.',
										'lwtv-underscores'
									),
									row.label
								)
					);
				}
				previous.current[ row.type ] = row.job;
			}
			setData( next );
		},
		[ say ]
	);

	// Load whenever the tab is shown, so a job run from the Status tab shows
	// here too. Unsaved drafts survive: a block resets its draft only when
	// its saved values change.
	useEffect( () => {
		if ( ! active ) {
			return;
		}
		const asked = generation.current;
		load()
			.then( ( next ) => {
				if ( asked !== generation.current ) {
					return;
				}
				take( next );
				setSelected( ( current ) => {
					if ( current ) {
						return current;
					}
					const wanted = fromUrl();
					return next.indexes.some( ( r ) => r.type === wanted )
						? wanted
						: next.indexes[ 0 ]?.type;
				} );
			} )
			.catch( ( e ) =>
				setLoadError(
					e?.message ||
						__(
							'Could not load the typo settings.',
							'lwtv-underscores'
						)
				)
			);
	}, [ active, load, take ] );

	const running = !! data?.indexes?.some( ( row ) => isRunning( row.job ) );
	const poll = usePoll( {
		active: active && running,
		request: load,
		onResult: take,
		onTimeout: () =>
			say(
				__( 'Still running; check again later.', 'lwtv-underscores' )
			),
		intervalMs: POLL_MS,
		maxMs: POLL_MAX_MS,
	} );

	// Warn before leaving with unsaved edits in any index.
	const anyDirty = Object.values( dirty ).some( Boolean );
	useEffect( () => {
		if ( ! anyDirty ) {
			return;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ anyDirty ] );

	const onDirty = useCallback( ( type, value ) => {
		setDirty( ( current ) =>
			current[ type ] === value
				? current
				: { ...current, [ type ]: value }
		);
	}, [] );

	const onSaved = ( row ) => {
		poll.invalidate();
		generation.current++;
		// Remember a started job, so its end is announced.
		previous.current[ row.type ] = row.job;
		setData( ( current ) => ( {
			...current,
			indexes: current.indexes.map( ( r ) =>
				r.type === row.type ? row : r
			),
		} ) );
		poll.restart();
	};

	const choose = ( type ) => {
		setSelected( type );
		toUrl( type );
	};

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}

	if ( ! data ) {
		return active ? <Spinner /> : null;
	}

	return (
		<div className="lwtv-settings__typo">
			<p className="lwtv-settings__intro">
				{ __(
					'How many typos each index forgives. Changes are saved, then applied to that index in the background.',
					'lwtv-underscores'
				) }
			</p>
			<div className="lwtv-settings__cols">
				<nav
					className="lwtv-settings__card lwtv-settings__typo-rail"
					aria-labelledby="lwtv-typo-rail-title"
				>
					<div className="lwtv-settings__card-head">
						<h2 id="lwtv-typo-rail-title">
							{ __( 'Indexes', 'lwtv-underscores' ) }
						</h2>
					</div>
					<ul>
						{ data.indexes.map( ( row ) => (
							<li key={ row.type }>
								<RailItem
									row={ row }
									current={ row.type === selected }
									dirty={ !! dirty[ row.type ] }
									onChoose={ () => choose( row.type ) }
								/>
							</li>
						) ) }
					</ul>
				</nav>
				<div className="lwtv-settings__typo-main">
					{ data.indexes.map( ( row ) => (
						<TypoBlock
							key={ row.type }
							row={ row }
							hidden={ row.type !== selected }
							limits={ data.limits }
							say={ say }
							onSaved={ onSaved }
							onDirty={ onDirty }
						/>
					) ) }
				</div>
			</div>
		</div>
	);
}

/**
 * One index in the rail.
 *
 * @param {Object}     props          Props.
 * @param {Object}     props.row      The index row.
 * @param {boolean}    props.current  Whether it's the one shown.
 * @param {boolean}    props.dirty    Whether it has unsaved edits.
 * @param {() => void} props.onChoose Show it.
 * @return {Element} Item.
 */
function RailItem( { row, current, dirty, onChoose } ) {
	let note = __( 'Using defaults', 'lwtv-underscores' );
	if ( dirty ) {
		note = __( 'Unsaved changes', 'lwtv-underscores' );
	} else if ( ! row.is_default ) {
		note = __( 'Customised', 'lwtv-underscores' );
	}

	return (
		<button
			type="button"
			className={ `lwtv-settings__rail-item${
				current ? ' is-current' : ''
			}` }
			aria-current={ current ? 'true' : undefined }
			onClick={ onChoose }
		>
			<span className="lwtv-settings__rail-text">
				<span className="lwtv-settings__rail-name">{ row.label }</span>
				<span className="lwtv-settings__rail-note">{ note }</span>
			</span>
			<JobIcon job={ row.job } />
		</button>
	);
}

/**
 * The last job on an index, as an icon with a name. Nothing when there's
 * no job to describe (none yet, or cancelled).
 *
 * @param {Object}  props     Props.
 * @param {?Object} props.job The index's last job.
 * @return {Element|null} Icon.
 */
export function JobIcon( { job } ) {
	const icon = jobIcon( job );
	if ( ! icon ) {
		return null;
	}
	return (
		<svg
			className={ `lwtv-settings__job-icon ${ icon.className }` }
			role="img"
			aria-label={ icon.name }
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.75"
			strokeLinecap="round"
			strokeLinejoin="round"
			focusable="false"
		>
			<title>{ icon.name }</title>
			{ icon.path }
		</svg>
	);
}

/**
 * Which icon a job gets.
 *
 * @param {?Object} job The index's last job.
 * @return {?{name: string, className: string, path: Element}} Icon, or null.
 */
function jobIcon( job ) {
	if ( isRunning( job ) ) {
		return {
			name: __( 'Applying', 'lwtv-underscores' ),
			className: 'is-warn',
			path: (
				<>
					<circle cx="8" cy="8" r="6.25" />
					<path d="M8 4.5V8l2.5 1.5" />
				</>
			),
		};
	}
	if ( 'failed' === job?.state ) {
		return {
			name: __( 'Apply failed', 'lwtv-underscores' ),
			className: 'is-error',
			path: (
				<>
					<circle cx="8" cy="8" r="6.25" />
					<path d="M8 4.75v3.75M8 11.25v.01" />
				</>
			),
		};
	}
	if ( 'done' === job?.state ) {
		return {
			name: __( 'Applied', 'lwtv-underscores' ),
			className: 'is-ok',
			path: <path d="M3 8.5l3.25 3.25L13 5" />,
		};
	}
	return null;
}
