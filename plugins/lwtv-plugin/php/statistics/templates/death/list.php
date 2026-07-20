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

$dl_cards = array(
	array(
		'variant' => 'crimson',
		'label'   => __( 'Longest Gap', 'lwtv' ),
		'count'   => $dl_time,
		'unit'    => __( 'days', 'lwtv' ),
		'caption' => __( 'Between two consecutive deaths', 'lwtv' ),
	),
	array(
		'variant' => 'raspberry',
		'label'   => __( 'Shortest Gap', 'lwtv' ),
		'count'   => 0,
		'unit'    => __( 'days', 'lwtv' ),
		'caption' => __( 'Multiple have died the same day', 'lwtv' ),
	),
	array(
		'variant' => 'plum',
		'label'   => __( 'Most In One Day', 'lwtv' ),
		'count'   => $dl_most,
		'unit'    => '',
		'caption' => __( 'Characters killed on a single date', 'lwtv' ),
	),
);

// Flatten date groups → one row per dead character (a date's gap applies to each).
$dl_rows = array();
foreach ( $dead_records as $dl_date => $dl_group ) {
	$dl_since = isset( $dl_group['since'] ) ? (int) $dl_group['since'] : -1; // -1 => oldest row, show "—".

	// The raw ACF meta backing the date key is usually Y-m-d, but a handful of
	// rows were saved without dashes (Ymd). Normalize for display + text-sort.
	$dl_date_display = (string) $dl_group['date'];
	if ( false === strpos( $dl_date_display, '-' ) && 8 === strlen( $dl_date_display ) ) {
		$dl_date_display = substr( $dl_date_display, 0, 4 ) . '-' . substr( $dl_date_display, 4, 2 ) . '-' . substr( $dl_date_display, 6, 2 );
	}

	foreach ( (array) $dl_group['chars'] as $dl_char ) {
		$dl_rows[] = array(
			'name'  => $dl_char['name'],
			'url'   => $dl_char['url'],
			'date'  => $dl_date_display,
			'since' => $dl_since,
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
</p>
<div class="lwtv-death-list-wrap">
	<table id="DeadCharactersTable" class="tablesorter lwtv-death-list">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'lwtv' ); ?></th>
				<th><?php esc_html_e( 'Date', 'lwtv' ); ?></th>
				<th class="lwtv-death-list-num"><?php esc_html_e( 'Days Since Prev', 'lwtv' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $dl_rows as $dl_r ) {
				?>
				<tr>
					<td><a href="<?php echo esc_url( $dl_r['url'] ); ?>"><?php echo esc_html( $dl_r['name'] ); ?></a></td>
					<td><?php echo esc_html( $dl_r['date'] ); ?></td>
					<td class="lwtv-death-list-num" data-text="<?php echo esc_attr( $dl_r['since'] >= 0 ? (string) $dl_r['since'] : '-1' ); ?>"><?php echo ( $dl_r['since'] >= 0 ) ? esc_html( number_format_i18n( $dl_r['since'] ) ) : '—'; ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>
</div>
