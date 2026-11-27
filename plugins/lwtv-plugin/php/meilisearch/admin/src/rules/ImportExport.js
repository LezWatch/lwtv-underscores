/**
 * Export the saved list as JSON, and import one through the same save.
 */
import { Button, Modal, Notice } from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { parseImport } from './rules';

export default function ImportExport( { saved, busy, onImport } ) {
	const input = useRef();
	const [ pending, setPending ] = useState( null );
	const [ problem, setProblem ] = useState( '' );

	const exportList = () => {
		const date = new Date().toISOString().slice( 0, 10 );
		const blob = new Blob(
			[ JSON.stringify( { version: 1, synonyms: saved }, null, '\t' ) ],
			{ type: 'application/json' }
		);
		const url = URL.createObjectURL( blob );
		const a = document.createElement( 'a' );
		a.href = url;
		a.download = `lwtv-search-rules-${ window.location.hostname }-${ date }.json`;
		document.body.appendChild( a );
		a.click();
		a.remove();
		URL.revokeObjectURL( url );
	};

	const readFile = ( event ) => {
		const file = event.target.files?.[ 0 ];
		event.target.value = '';
		setProblem( '' );
		if ( ! file ) {
			return;
		}
		file.text().then( ( text ) => {
			const synonyms = parseImport( text );
			if ( ! synonyms ) {
				setProblem(
					__(
						"That file isn't a search rules export, so nothing was imported.",
						'lwtv-underscores'
					)
				);
				return;
			}
			setPending( synonyms );
		} );
	};

	return (
		<div className="lwtv-rules__import-export">
			<h3>{ __( 'Export and import', 'lwtv-underscores' ) }</h3>
			<div className="lwtv-rules__file">
				<Button variant="secondary" onClick={ exportList }>
					{ __( 'Export', 'lwtv-underscores' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () => input.current?.click() }
					disabled={ busy }
					accessibleWhenDisabled
				>
					{ __( 'Import…', 'lwtv-underscores' ) }
				</Button>
				{ /* The real picker, opened by the button above. */ }
				<input
					ref={ input }
					type="file"
					accept="application/json,.json"
					onChange={ readFile }
					className="screen-reader-text"
					tabIndex={ -1 }
					aria-hidden="true"
				/>
			</div>
			<p className="lwtv-rules__hint">
				{ __(
					'Export downloads the saved list as JSON. Import replaces the list with a file’s, after you confirm.',
					'lwtv-underscores'
				) }
			</p>
			{ problem && (
				<Notice status="error" onRemove={ () => setProblem( '' ) }>
					{ problem }
				</Notice>
			) }
			{ pending && (
				<Modal
					title={ __( 'Import search rules?', 'lwtv-underscores' ) }
					onRequestClose={ () => setPending( null ) }
				>
					<p>
						{ sprintf(
							/* translators: 1: groups summary, 2: one-way summary. */
							__(
								'%1$s, %2$s. This replaces the current list.',
								'lwtv-underscores'
							),
							sprintf(
								/* translators: %d: number of groups. */
								_n(
									'%d group',
									'%d groups',
									pending.two_way.length,
									'lwtv-underscores'
								),
								pending.two_way.length
							),
							sprintf(
								/* translators: %d: number of one-way entries. */
								_n(
									'%d one-way',
									'%d one-way',
									pending.one_way.length,
									'lwtv-underscores'
								),
								pending.one_way.length
							)
						) }
					</p>
					<div className="lwtv-rules__modal-actions">
						<Button
							variant="tertiary"
							onClick={ () => setPending( null ) }
						>
							{ __( 'Cancel', 'lwtv-underscores' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => {
								const synonyms = pending;
								setPending( null );
								onImport( synonyms );
							} }
						>
							{ __( 'Replace and save', 'lwtv-underscores' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
