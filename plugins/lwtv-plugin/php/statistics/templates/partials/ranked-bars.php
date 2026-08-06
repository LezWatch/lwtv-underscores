<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable ranked horizontal-bar list (full-width panel).
 *
 * @package LezWatch.TV
 *
 * @var array  $ranked {
 *   @type array  $rows    slug => ['name','count','url'(optional)].
 *   @type int    $total   Denominator for pct (all shows).
 *   @type string $family  Color family: characters|actors|shows.
 *   @type string $title   Panel heading.
 *   @type string $sub     Panel sub-line (optional).
 *   @type string $svg     Header icon sprite file (optional).
 *   @type string $icon    Header icon FA fallback (optional).
 *   @type string $base    URL base for row links (e.g. '/trope/'); '' to use row 'url'.
 *   @type string $mode    'share' (default) | 'leaderboard' | 'lollipop'.
 * }
 */

$ranked_rows = $ranked['rows'] ?? array();
uasort( $ranked_rows, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$ranked_total = (int) ( $ranked['total'] ?? 0 );
$ranked_mode  = ( isset( $ranked['mode'] ) && in_array( $ranked['mode'], array( 'leaderboard', 'lollipop' ), true ) ) ? $ranked['mode'] : 'share';
$ranked_top   = ! empty( $ranked_rows ) ? max( array_map( fn( $r ) => (int) $r['count'], $ranked_rows ) ) : 0;
$ranked_rank  = 0;
?>
<section class="lwtv-panel bg-light">
	<header class="lwtv-panel-head">
		<span class="lwtv-panel-icon <?php echo esc_attr( $ranked['family'] ?? 'shows' ); ?>">
			<?php echo lwtv_plugin()->get_symbolicon( svg: $ranked['svg'] ?? 'tag.svg', icon: $ranked['icon'] ?? 'svg-tag', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<div>
			<h2 class="lwtv-panel-title"><?php echo esc_html( $ranked['title'] ?? ( $ranked['eyebrow'] ?? '' ) ); ?></h2>
			<?php if ( ! empty( $ranked['sub'] ) ) : ?>
				<p class="lwtv-panel-sub"><?php echo esc_html( $ranked['sub'] ); ?></p>
			<?php endif; ?>
		</div>
	</header>
	<div class="lwtv-leaders lwtv-bars--<?php echo esc_attr( $ranked['family'] ?? 'shows' ); ?>">
		<?php
		foreach ( $ranked_rows as $ranked_slug => $ranked_row ) {
			$ranked_count = (int) $ranked_row['count'];
			// Share mode skips empty terms; leaderboard keeps every ranked row.
			if ( 'share' === $ranked_mode && $ranked_count <= 0 ) {
				continue;
			}
			++$ranked_rank;
			if ( 'leaderboard' === $ranked_mode || 'lollipop' === $ranked_mode ) {
				// Bar/stick relative to the top count; label is the raw count.
				$ranked_width = ( $ranked_top > 0 ) ? round( ( $ranked_count / $ranked_top ) * 100, 1 ) : 0;
				$ranked_label = number_format_i18n( $ranked_count );
			} else {
				// Bar is the true share of the total; label is count · pct%.
				$ranked_width = ( $ranked_total > 0 ) ? round( ( $ranked_count / $ranked_total ) * 100, 1 ) : 0;
				$ranked_label = number_format_i18n( $ranked_count ) . ' · ' . $ranked_width . '%';
			}
			$ranked_href = ( ! empty( $ranked['base'] ) ) ? site_url( $ranked['base'] . $ranked_slug ) : ( $ranked_row['url'] ?? '#' );
			?>
			<div class="lwtv-leader-row">
				<div class="lwtv-leader-head">
					<?php if ( 'leaderboard' === $ranked_mode ) : ?>
						<span class="lwtv-leader-name">
							<span class="lwtv-leader-rank"><?php echo esc_html( number_format_i18n( $ranked_rank ) ); ?></span>
							<a href="<?php echo esc_url( $ranked_href ); ?>"><?php echo esc_html( $ranked_row['name'] ); ?></a>
						</span>
					<?php else : ?>
						<a class="lwtv-leader-name" href="<?php echo esc_url( $ranked_href ); ?>"><?php echo esc_html( $ranked_row['name'] ); ?></a>
					<?php endif; ?>
					<span class="lwtv-leader-value"><?php echo esc_html( $ranked_label ); ?></span>
				</div>
				<?php if ( 'lollipop' === $ranked_mode ) : ?>
					<div class="lwtv-lolli-track" aria-hidden="true">
						<span class="lwtv-lolli" style="width:0" data-grow-to="<?php echo esc_attr( (string) $ranked_width ); ?>"><span class="lwtv-lolli-stick"></span><span class="lwtv-lolli-dot"></span></span>
					</div>
				<?php else : ?>
					<div class="progress lwtv-leader-track">
						<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $ranked_width ); ?>" aria-valuenow="<?php echo esc_attr( (string) $ranked_count ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) ( 'leaderboard' === $ranked_mode ? $ranked_top : $ranked_total ) ); ?>"></div>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
		?>
	</div>
</section>
