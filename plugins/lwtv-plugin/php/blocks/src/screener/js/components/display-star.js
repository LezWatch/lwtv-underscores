import Star from './svg/star';
import { Fragment } from '@wordpress/element';
import { buttonClass, tooltipAttrs, showIcon } from './markup';

// Figure out Trigger Display
export default function DisplayStar( { score, legacy = false } ) {
	let color;

	switch ( score ) {
		case 'anti':
			color = 'danger';
			break;
		case 'bronze':
			color = 'danger';
			break;
		case 'silver':
			color = 'warning';
			break;
		default:
			color = 'gold';
	}

	if ( 'none' !== score ) {
		return (
			<Fragment>
				<span
					{ ...tooltipAttrs( legacy ) }
					aria-label={ `${ score } Star Show` }
					title=""
					data-original-title={ `${ score } Star Show` }
				>
					<button
						type="button"
						className={ buttonClass( 'info', legacy ) }
					>
						<span
							role="img"
							className={ `screener screener-star ${ color }` }
						>
							<span className="symbolicon" role="img">
								{ showIcon( legacy ) && <Star /> }
							</span>
						</span>
					</button>
				</span>
			</Fragment>
		);
	}
}
