/**
 * A row's errors and warnings, as plain text.
 */
import { __ } from '@wordpress/i18n';

export default function Messages( { errors, warnings } ) {
	if ( ! errors.length && ! warnings.length ) {
		return null;
	}

	return (
		<ul className="lwtv-rules__messages">
			{ errors.map( ( m, i ) => (
				<li key={ `e${ i }` } className="is-error">
					<strong>{ __( 'Error:', 'lwtv-underscores' ) }</strong>{ ' ' }
					{ m.message }
				</li>
			) ) }
			{ warnings.map( ( m, i ) => (
				<li key={ `w${ i }` } className="is-warning">
					<strong>{ __( 'Warning:', 'lwtv-underscores' ) }</strong>{ ' ' }
					{ m.message }
				</li>
			) ) }
		</ul>
	);
}
