/**
 * One-way entries: typing the first word also finds the others.
 */
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	FormTokenField,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { forRow } from './rules';
import Messages from './Messages';

export default function OneWay( {
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
						'Typing the first word also finds the others, but not the other way round.',
						'lwtv-underscores'
					) }
				</p>
			</CardHeader>
			<CardBody>
				{ rows.length === 0 && (
					<p>
						{ __( 'No one-way entries yet.', 'lwtv-underscores' ) }
					</p>
				) }
				{ rows.map( ( row, i ) => {
					const rowErrors = forRow( errors, 'one_way', i );
					const rowWarnings = forRow( warnings, 'one_way', i );
					const name =
						[ row.from, ...row.to ]
							.filter( Boolean )
							.join( ', ' ) ||
						__( '(empty)', 'lwtv-underscores' );

					return (
						<div
							key={ row.key }
							id={ `lwtv-rules-one_way-${ i }` }
							className={ `lwtv-rules__row${ rowErrors.length ? ' has-error' : '' }` }
							tabIndex={ -1 }
						>
							<div className="lwtv-rules__fields lwtv-rules__fields--one-way">
								<TextControl
									label={ sprintf(
										/* translators: %d: entry number. */
										__(
											'Entry %d: people type',
											'lwtv-underscores'
										),
										i + 1
									) }
									value={ row.from }
									onChange={ ( from ) =>
										onChange( i, { ...row, from } )
									}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
								<span
									className="lwtv-rules__arrow"
									aria-hidden="true"
								>
									→
								</span>
								<FormTokenField
									label={ sprintf(
										/* translators: %d: entry number. */
										__(
											'Entry %d: also find',
											'lwtv-underscores'
										),
										i + 1
									) }
									value={ row.to }
									onChange={ ( to ) =>
										onChange( i, {
											...row,
											to: to.map( String ),
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
									/* translators: %s: the entry's words. */
									__(
										'Remove the one-way entry %s',
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
					id="lwtv-rules-add-one_way"
					variant="secondary"
					onClick={ onAdd }
				>
					{ __( 'Add one', 'lwtv-underscores' ) }
				</Button>
			</CardFooter>
		</Card>
	);
}
