<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Ranked taxonomy leaderboard (nations / stations): rank · name · share bar
 * (true share of all shows, ramp by rank) · shows·pct · characters · dead.
 *
 * @package LezWatch.TV
 *
 * @var array  $leaderboard_rows  Ranked [ slug => ['name','count'] ], desc by count.
 * @var array  $leaderboard_chars [ slug => ['total','dead'] ].
 * @var int    $leaderboard_all   Total shows (for share %).
 * @var string $leaderboard_base  Base URL for row links (default '/statistics/nations/').
 * @var string $leaderboard_qvar  Query var for row links (default 'nation').
 * @var string $leaderboard_title Panel title.
 * @var string $leaderboard_col   Name-column header (default 'Nation').
 * @var string $leaderboard_items Lowercase plural noun for the sub-line (default 'nations').
 * @var string $leaderboard_icon_svg Header sprite file (default 'globe.svg').
 * @var string $leaderboard_icon_fa  Header FA fallback (default 'svg-globe').
 */

$lb_rows  = is_array( $leaderboard_rows ) ? $leaderboard_rows : array();
$lb_total = count( $lb_rows );
$lb_shown = array_slice( $lb_rows, 0, 10, true );
$lb_rank  = 0;
$lb_base  = $leaderboard_base ?? '/statistics/nations/';
$lb_qvar  = $leaderboard_qvar ?? 'nation';
$lb_title = $leaderboard_title ?? __( 'Nations by number of shows', 'lwtv' );
$lb_col   = $leaderboard_col ?? __( 'Nation', 'lwtv' );
$lb_items = $leaderboard_items ?? __( 'nations', 'lwtv' );
$lb_isvg  = $leaderboard_icon_svg ?? 'globe.svg';
$lb_ifa   = $leaderboard_icon_fa ?? 'svg-globe';
?>
<section class="lwtv-panel bg-light">
	<header class="lwtv-panel-head">
		<span class="lwtv-panel-icon sexuality">
			<?php echo lwtv_plugin()->get_symbolicon( svg: $lb_isvg, icon: $lb_ifa, max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<div>
			<h2 class="lwtv-panel-title"><?php echo esc_html( $lb_title ); ?></h2>
			<p class="lwtv-panel-sub">
				<?php
				printf(
					/* translators: 1: count, 2: plural noun (nations/stations). */
					esc_html__( 'Top 10 of %1$s %2$s with shows.', 'lwtv' ),
					esc_html( number_format_i18n( $lb_total ) ),
					esc_html( $lb_items )
				);
				?>
			</p>
		</div>
	</header>
	<div class="lwtv-nations-lb">
		<div class="lwtv-nations-lb-head">
			<span></span>
			<span><?php echo esc_html( $lb_col ); ?></span>
			<span><?php esc_html_e( 'Share of all shows', 'lwtv' ); ?></span>
			<span class="lwtv-nations-lb-num"><?php esc_html_e( 'Shows', 'lwtv' ); ?></span>
			<span class="lwtv-nations-lb-num"><?php esc_html_e( 'Chars', 'lwtv' ); ?></span>
			<span class="lwtv-nations-lb-num"><?php esc_html_e( 'Dead', 'lwtv' ); ?></span>
		</div>
		<?php
		foreach ( $lb_shown as $lb_slug => $lb_data ) {
			++$lb_rank;
			$lb_clean = ltrim( $lb_slug, '_' );
			$lb_shows = (int) $lb_data['count'];
			$lb_chars = (int) ( $leaderboard_chars[ $lb_clean ]['total'] ?? 0 );
			$lb_dead  = (int) ( $leaderboard_chars[ $lb_clean ]['dead'] ?? 0 );
			$lb_pct   = ( $leaderboard_all > 0 ) ? round( ( $lb_shows / $leaderboard_all ) * 100, 1 ) : 0;
			$lb_ramp  = min( $lb_rank, 5 );
			?>
			<div class="lwtv-nations-lb-row">
				<span class="lwtv-nations-lb-rank"><?php echo esc_html( number_format_i18n( $lb_rank ) ); ?></span>
				<a class="lwtv-nations-lb-name" href="<?php echo esc_url( add_query_arg( $lb_qvar, $lb_slug, $lb_base ) ); ?>"><?php echo esc_html( $lb_data['name'] ); ?></a>
				<span class="lwtv-nations-lb-track">
					<span class="lwtv-nations-lb-bar lwtv-nations-lb-bar--<?php echo (int) $lb_ramp; ?>" style="width:0" data-grow-to="<?php echo esc_attr( (string) $lb_pct ); ?>"></span>
				</span>
				<span class="lwtv-nations-lb-num"><?php echo esc_html( number_format_i18n( $lb_shows ) . ' · ' . $lb_pct . '%' ); ?></span>
				<span class="lwtv-nations-lb-num"><?php echo esc_html( number_format_i18n( $lb_chars ) ); ?></span>
				<span class="lwtv-nations-lb-num lwtv-nations-lb-dead"><?php echo esc_html( number_format_i18n( $lb_dead ) ); ?></span>
			</div>
			<?php
		}
		?>
	</div>
</section>
