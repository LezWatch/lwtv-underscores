/**
 * Two-way groups: every word means the same thing.
 */
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	FormTokenField,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { forRow } from './rules';
import Messages from './Messages';

export default function TwoWay( {
	rows,
	errors,
	warnings,
	onChange,
	onAdd,
	onRemove,
} ) {
	return (
		<Card className="lwtv-rules__section">
			<CardHeader className="lwtv-rules__section-header">
				<p>
					{ __(
						'Every word in a group means the same thing: searching for any of them finds the others.',
						'lwtv-underscores'
					) }
				</p>
			</CardHeader>
			<CardBody>
				{ rows.length === 0 && (
					<p>{ __( 'No groups yet.', 'lwtv-underscores' ) }</p>
				) }
				{ rows.map( ( row, i ) => {
					const rowErrors = forRow( errors, 'two_way', i );
					const rowWarnings = forRow( warnings, 'two_way', i );
					const name =
						row.words.join( ', ' ) ||
						__( '(empty)', 'lwtv-underscores' );

					return (
						<div
							key={ row.key }
							id={ `lwtv-rules-two_way-${ i }` }
							className={ `lwtv-rules__row${ rowErrors.length ? ' has-error' : '' }` }
							tabIndex={ -1 }
						>
							<div className="lwtv-rules__fields">
								<FormTokenField
									label={ sprintf(
										/* translators: %d: group number. */
										__( 'Group %d', 'lwtv-underscores' ),
										i + 1
									) }
									value={ row.words }
									onChange={ ( words ) =>
										onChange( i, {
											...row,
											words: words.map( String ),
										} )
									}
									tokenizeOnBlur
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</div>
							<Button
								variant="secondary"
								isDestructive
								onClick={ () => onRemove( i ) }
								aria-label={ sprintf(
									/* translators: %s: the group's words. */
									__(
										'Remove the group %s',
										'lwtv-underscores'
									),
									name
								) }
							>
								{ __( 'Remove', 'lwtv-underscores' ) }
							</Button>
							<Messages
								errors={ rowErrors }
								warnings={ rowWarnings }
							/>
						</div>
					);
				} ) }
			</CardBody>
			<CardFooter>
				<Button
					id="lwtv-rules-add-two_way"
					variant="secondary"
					onClick={ onAdd }
				>
					{ __( 'Add a group', 'lwtv-underscores' ) }
				</Button>
			</CardFooter>
		</Card>
	);
}
