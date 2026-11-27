/**
 * Where the last apply stands, per index.
 */
import { Button, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { summarize } from './rules';

/**
 * "2 minutes ago", from a unix time.
 *
 * @param {number} at Unix seconds.
 * @return {string} Relative time.
 */
function ago( at ) {
	const seconds = Math.round( at - Date.now() / 1000 );
	const rtf = new Intl.RelativeTimeFormat(
		document.documentElement.lang || undefined,
		{ numeric: 'auto' }
	);
	const abs = Math.abs( seconds );

	if ( abs < 60 ) {
		return rtf.format( seconds, 'second' );
	}
	if ( abs < 3600 ) {
		return rtf.format( Math.round( seconds / 60 ), 'minute' );
	}
	if ( abs < 86400 ) {
		return rtf.format( Math.round( seconds / 3600 ), 'hour' );
	}
	return rtf.format( Math.round( seconds / 86400 ), 'day' );
}

export default function Status( {
	applied,
	labels,
	timedOut,
	busy,
	onApplyAgain,
} ) {
	if ( ! applied?.at ) {
		return (
			<p className="lwtv-rules__status">
				{ __( 'Not applied yet.', 'lwtv-underscores' ) }
			</p>
		);
	}

	const s = summarize( applied );
	const label = ( type ) => labels[ type ] || type;

	return (
		<div className="lwtv-rules__status">
			<p id="lwtv-rules-status" tabIndex={ -1 }>
				{ s.applying && <Spinner /> }
				{ sprintf(
					/* translators: 1: indexes that took the list, 2: indexes that exist here. */
					__(
						'Applied to %1$d of %2$d set-up indexes',
						'lwtv-underscores'
					),
					s.ok,
					s.setUp
				) }
				{ ' · ' }
				{ ago( applied.at ) }
				{ s.applying &&
					! timedOut &&
					` · ${ __( 'applying…', 'lwtv-underscores' ) }` }
				{ s.applying &&
					timedOut &&
					` · ${ __( 'still applying; reload to check.', 'lwtv-underscores' ) }` }
			</p>
			{ ( s.notSetUp.length > 0 || s.failed.length > 0 ) && (
				<ul>
					{ s.notSetUp.map( ( type ) => (
						<li key={ type }>
							{ sprintf(
								/* translators: %s: index name. */
								__(
									"%s isn't set up here.",
									'lwtv-underscores'
								),
								label( type )
							) }
						</li>
					) ) }
					{ s.failed.map( ( f ) => (
						<li key={ f.type } className="is-error">
							{ sprintf(
								/* translators: 1: index name, 2: error from the search server. */
								__(
									'%1$s: not applied (%2$s)',
									'lwtv-underscores'
								),
								label( f.type ),
								f.status
							) }
						</li>
					) ) }
				</ul>
			) }
			{ ( s.notSetUp.length > 0 || s.failed.length > 0 ) && (
				<Button
					variant="secondary"
					onClick={ onApplyAgain }
					disabled={ busy }
					accessibleWhenDisabled
				>
					{ __( 'Apply again', 'lwtv-underscores' ) }
				</Button>
			) }
		</div>
	);
}
