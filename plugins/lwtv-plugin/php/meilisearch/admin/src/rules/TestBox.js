/**
 * Test a search: the top hits per index, as the site searches.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function TestBox( { restPath } ) {
	const [ q, setQ ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const run = ( event ) => {
		event.preventDefault();
		if ( ! q.trim() ) {
			return;
		}
		setBusy( true );
		setError( '' );
		apiFetch( {
			path: `/${ restPath }/test?q=${ encodeURIComponent( q.trim() ) }`,
		} )
			.then( ( data ) => setResults( data.results || [] ) )
			.catch( ( e ) => {
				setResults( null );
				setError(
					e?.message ||
						__( 'The test search failed.', 'lwtv-underscores' )
				);
			} )
			.finally( () => setBusy( false ) );
	};

	return (
		<form className="lwtv-rules__test" onSubmit={ run }>
			<h3>{ __( 'Test a search', 'lwtv-underscores' ) }</h3>
			<p>
				{ __(
					'Searches the saved, applied list.',
					'lwtv-underscores'
				) }
			</p>
			<div className="lwtv-rules__test-row">
				<TextControl
					label={ __( 'Search for', 'lwtv-underscores' ) }
					value={ q }
					onChange={ setQ }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<Button
					variant="secondary"
					type="submit"
					disabled={ busy || ! q.trim() }
					accessibleWhenDisabled
				>
					{ __( 'Test', 'lwtv-underscores' ) }
				</Button>
				{ busy && <Spinner /> }
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ results && (
				<dl className="lwtv-rules__test-results" aria-live="polite">
					{ results.length === 0 && (
						<dd>
							{ __(
								'No index is set up here.',
								'lwtv-underscores'
							) }
						</dd>
					) }
					{ results.map( ( r ) => (
						<div key={ r.type }>
							<dt>{ r.label }</dt>
							<dd>
								{ r.hits.length
									? r.hits
											.map( ( h ) => h.title )
											.join( ' · ' )
									: __( 'No results', 'lwtv-underscores' ) }
							</dd>
						</div>
					) ) }
				</dl>
			) }
		</form>
	);
}
