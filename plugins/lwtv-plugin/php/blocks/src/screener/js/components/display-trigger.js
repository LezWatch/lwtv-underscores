import { Fragment } from '@wordpress/element';
import Warning from './svg/warning';
import { buttonClass, tooltipAttrs, showIcon } from './markup';

// Figure out Trigger Display
export default function DisplayTrigger( { score, legacy = false } ) {
	let color;

	switch ( score ) {
		case 'high':
			color = 'danger';
			break;
		case 'medium':
			color = 'warning';
			break;
		default:
			color = 'info';
	}

	if ( 'none' !== score ) {
		return (
			<Fragment>
				<span
					{ ...tooltipAttrs( legacy ) }
					aria-label="Warning - This show contains triggers"
					title="Warning - This show contains triggers"
				>
					<button
						type="button"
						className={ buttonClass( color, legacy ) }
					>
						<span
							role="img"
							className={ `screener screener-warn ${ color }` }
						>
							<span className="symbolicon" role="img">
								{ showIcon( legacy ) && <Warning /> }
							</span>
						</span>
					</button>
				</span>
			</Fragment>
		);
	}
}
