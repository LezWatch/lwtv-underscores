import { Fragment } from '@wordpress/element';
import { buttonClass, tooltipAttrs } from './markup';

// Display queer
export default function DisplayQueer( { score, legacy = false } ) {
	return (
		<Fragment>
			<span
				{ ...tooltipAttrs( legacy ) }
				aria-label="How good is this show for queers?"
				title=""
				data-original-title="How good is this show for queers?"
			>
				<button
					type="button"
					className={ buttonClass( 'dark', legacy ) }
				>
					Queer Score: { `${ score }` }
				</button>
			</span>
		</Fragment>
	);
}
