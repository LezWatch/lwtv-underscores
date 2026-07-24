<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → List: derived gap cards + the full sortable record.
 *
 * @package LezWatch.TV
 *
 * @var array $deadchars_with_stats  'time' summary.
 * @var array $dead_records          date-keyed records (newest first).
 */

if ( empty( $dead_records ) ) {
	lwtv_plugin()->debug_log( 'death', 'Dead records empty' );
	return;
}

$dl_time = (int) ( $deadchars_with_stats['time'] ?? 0 );
$dl_most = (int) ( $deadchars_with_stats['most']['count'] ?? 0 );

// Supporting detail for the three record cards, derived from the full record.
// Keys are canonical Y-m-d, newest first (see build_list()); a date's 'since'
// is the gap down to the next-older death, and the oldest death has none.
$dl_keys        = array_keys( $dead_records );
$dl_longest     = 0;
$dl_longest_txt = '';      // "older → newer" for the single biggest gap.
$dl_most_count  = 0;
$dl_most_dates  = array(); // every date tied for the most deaths in one day.
$dl_shared_days = 0;       // how many distinct dates had more than one death.

foreach ( $dl_keys as $dl_i => $dl_k ) {
	$dl_g      = $dead_records[ $dl_k ];
	$dl_gsince = isset( $dl_g['since'] ) ? (int) $dl_g['since'] : -1;
	$dl_gdate  = (string) $dl_g['date'];
	$dl_gcount = count( (array) $dl_g['chars'] );

	if ( $dl_gcount > 1 ) {
		++$dl_shared_days;
	}

	if ( $dl_gcount > $dl_most_count ) {
		$dl_most_count = $dl_gcount;
		$dl_most_dates = array( $dl_gdate );
	} elseif ( $dl_gcount === $dl_most_count ) {
		$dl_most_dates[] = $dl_gdate;
	}

	// The gap sits between this death and the next-older one; capture the pair
	// of dates that bound the longest run without a death.
	if ( $dl_gsince > $dl_longest && isset( $dl_keys[ $dl_i + 1 ] ) ) {
		$dl_longest     = $dl_gsince;
		$dl_longest_txt = (string) $dead_records[ $dl_keys[ $dl_i + 1 ] ]['date'] . ' → ' . $dl_gdate;
	}
}

$dl_cards = array(
	array(
		'variant' => 'crimson',
		'label'   => __( 'Longest Gap', 'lwtv' ),
		'count'   => $dl_time,
		'unit'    => __( 'days', 'lwtv' ),
		'caption' => __( 'Between two consecutive deaths', 'lwtv' ),
		'detail'  => $dl_longest_txt,
	),
	array(
		'variant' => 'raspberry',
		'label'   => __( 'Shortest Gap', 'lwtv' ),
		'count'   => 0,
		'unit'    => __( 'days', 'lwtv' ),
		'caption' => sprintf(
			/* translators: %s: number of dates on which more than one character died. */
			_n(
				'Multiple characters have died on the same day %s time.',
				'Multiple characters have died on the same day %s times.',
				$dl_shared_days,
				'lwtv'
			),
			number_format_i18n( $dl_shared_days )
		),
		'detail'  => '',
	),
	array(
		'variant' => 'plum',
		'label'   => __( 'Most In One Day', 'lwtv' ),
		'count'   => $dl_most,
		'unit'    => '',
		'caption' => __( 'Characters killed on a single date', 'lwtv' ),
		'detail'  => implode( ', ', $dl_most_dates ),
	),
);

// Flatten date groups → one row per dead character. Everyone who died on a
// given date shares that date's gap, so each same-day row shows the same number.
// A date with more than one death is flagged 'shared' → an asterisk in the
// output, so the repeated value reads as "same day" instead of a copy error.
// The marker lives in the value (not the row position), so it stays correct no
// matter how the sortable table reorders same-day ties.
$dl_rows       = array();
$dl_has_shared = false;
foreach ( $dead_records as $dl_date => $dl_group ) {
	$dl_since = isset( $dl_group['since'] ) ? (int) $dl_group['since'] : -1; // -1 => oldest row, show "—".

	// The raw ACF meta backing the date key is usually Y-m-d, but a handful of
	// rows were saved without dashes (Ymd). Normalize for display + text-sort.
	$dl_date_display = (string) $dl_group['date'];
	if ( false === strpos( $dl_date_display, '-' ) && 8 === strlen( $dl_date_display ) ) {
		$dl_date_display = substr( $dl_date_display, 0, 4 ) . '-' . substr( $dl_date_display, 4, 2 ) . '-' . substr( $dl_date_display, 6, 2 );
	}

	$dl_chars  = (array) $dl_group['chars'];
	$dl_shared = count( $dl_chars ) > 1;
	if ( $dl_shared ) {
		$dl_has_shared = true;
	}
	foreach ( $dl_chars as $dl_char ) {
		$dl_rows[] = array(
			'name'   => $dl_char['name'],
			'url'    => $dl_char['url'],
			'date'   => $dl_date_display,
			'since'  => $dl_since,
			'shared' => $dl_shared,
		);
	}
}
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Record', 'lwtv' ); ?></p>
<div class="lwtv-toll">
	<?php
	foreach ( $dl_cards as $dl_c ) {
		?>
		<div class="lwtv-toll-tile lwtv-toll-tile--<?php echo esc_attr( $dl_c['variant'] ); ?>">
			<span class="lwtv-toll-eyebrow"><?php echo esc_html( $dl_c['label'] ); ?></span>
			<span class="lwtv-toll-numline">
				<span class="lwtv-toll-num" data-count-to="<?php echo (int) $dl_c['count']; ?>"><?php echo esc_html( number_format_i18n( $dl_c['count'] ) ); ?></span>
				<?php if ( '' !== $dl_c['unit'] ) : ?>
					<span class="lwtv-toll-unit"><?php echo esc_html( $dl_c['unit'] ); ?></span>
				<?php endif; ?>
			</span>
			<span class="lwtv-toll-caption"><?php echo esc_html( $dl_c['caption'] ); ?></span>
			<?php if ( ! empty( $dl_c['detail'] ) ) : ?>
				<span class="lwtv-toll-detail"><?php echo esc_html( $dl_c['detail'] ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}
	?>
</div>

<p class="lwtv-death-list-intro">
	<?php
	printf(
		/* translators: %s: number of dead characters. */
		esc_html( _n( '%s character, newest first. Click a column heading to sort.', '%s characters, newest first. Click a column heading to sort.', count( $dl_rows ), 'lwtv' ) ),
		esc_html( number_format_i18n( count( $dl_rows ) ) )
	);
	?>

	<?php if ( $dl_has_shared ) : ?>
		<?php esc_html_e( 'Multiple deaths in one day are marked with *.', 'lwtv' ); ?>
	<?php endif; ?>


</p>
<div class="lwtv-death-list-wrap">
	<table id="DeadCharactersTable" class="tablesorter lwtv-death-list">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'lwtv' ); ?></th>
				<th><?php esc_html_e( 'Date', 'lwtv' ); ?></th>
				<th class="lwtv-death-list-num"><?php esc_html_e( 'Days Since Prev Death', 'lwtv' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $dl_rows as $dl_r ) {
				// Asterisk marks a death that shares its date with another. The
				// number is unchanged (both are the same # of days since the prior
				// distinct death); '—' means the oldest death, with no prior.
				$dl_star = empty( $dl_r['shared'] ) ? '' : '*';
				$dl_num  = ( $dl_r['since'] >= 0 ) ? number_format_i18n( $dl_r['since'] ) : '—';
				?>
				<tr>
					<td><a href="<?php echo esc_url( $dl_r['url'] ); ?>"><?php echo esc_html( $dl_r['name'] ); ?></a></td>
					<td><?php echo esc_html( $dl_r['date'] ); ?></td>
					<td class="lwtv-death-list-num" data-text="<?php echo esc_attr( $dl_r['since'] >= 0 ? (string) $dl_r['since'] : '-1' ); ?>"><?php echo esc_html( $dl_num . $dl_star ); ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>
</div>
