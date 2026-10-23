import { Fragment } from '@wordpress/element';
import { RichText } from '@wordpress/block-editor';

// Internal Components
import DisplayQueer from './components/display-queer';
import DisplayWorth from './components/display-worth';
import DisplayTrigger from './components/display-trigger';
import DisplayStar from './components/display-star';

// `legacy` (false, 'bs5' or 'bs4'; see components/markup.js) reproduces
// older saved markup for the deprecations in ./deprecated.js.
export function makeSave( legacy = false ) {
	return function Save( props ) {
		const {
			attributes: { className },
		} = props;
		const { title, summary, queer, worthit, star, trigger } =
			props.attributes;

		return (
			<Fragment>
				<div
					className={ `${ className } bd-callout screener-shortcode` }
				>
					<h5>
						Screener Review On&nbsp;
						<RichText.Content tagName="em" value={ title } />
					</h5>
					<RichText.Content tagName="p" value={ summary } />
					<p>
						<DisplayQueer legacy={ legacy } score={ queer } />
						&nbsp;
						<DisplayWorth legacy={ legacy } score={ worthit } />
						&nbsp;
						<DisplayTrigger legacy={ legacy } score={ trigger } />
						&nbsp;
						<DisplayStar legacy={ legacy } score={ star } />
					</p>
				</div>
			</Fragment>
		);
	};
}

export default makeSave( false );
