import { Fragment } from '@wordpress/element';
import { RichText } from '@wordpress/block-editor';
import metadata from '../block.json';

// Bootstrap 5 markup (grade alert alert-info), saved before the v6
// migration. Lets those blocks validate; they upgrade on next save.
// Deprecations don't inherit attributes or supports, so pass them through.
const v1 = {
	attributes: metadata.attributes,
	supports: metadata.supports,
	save( props ) {
		const {
			attributes: { className },
		} = props;
		const { summary, grade } = props.attributes;

		return (
			<Fragment>
				<div className={ `${ className } bd-callout show-grade` }>
					<div className="grade alert alert-info">{ grade }</div>
					<div className="show-grade body">
						<RichText.Content tagName="p" value={ summary } />
					</div>
				</div>
			</Fragment>
		);
	},
};

export default [ v1 ];
