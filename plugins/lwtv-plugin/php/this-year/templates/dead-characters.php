<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This Year — Dead Characters: By Date rows / By Show cards / empty state.
 *
 * @package LezWatch.TV
 *
 * @var int   $this_year
 * @var int   $dead_characters_count
 * @var array $dead_by_date Keyed by death-date string → list of { slug, name, dead, death_years, shows:[{name,url,type}] }.
 *                           Character URL is built from slug (canonical /character/{slug}/).
 * @var array $dead_by_show Keyed by show_id → { show:{name,url,nations:[{name}],formats:[{name}]}, characters:[{name,url}] }.
 */

$lwtv_dc_count = (int) $dead_characters_count;

// Empty state — guard first, nothing else in this template applies.
if ( 0 === $lwtv_dc_count ) {
	?>
	<div class="lwtv-ty-empty">
		<div class="lwtv-ty-empty-icon">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'fingers-crossed.svg', icon: 'svg-fingers-crossed', max_size: '28' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<h2><?php esc_html_e( 'No characters died this year', 'lwtv' ); ?></h2>
		<p><?php esc_html_e( "I know! We're surprised too. Fingers crossed it stays that way.", 'lwtv' ); ?></p>
	</div>
	<?php
	return;
}

// ---- Callout derivations (only reached when deaths > 0). ----

// Deadliest month: total character deaths per calendar month across the dated groups.
$lwtv_dc_month_tally = array();
foreach ( $dead_by_date as $lwtv_dc_mk => $lwtv_dc_mchars ) {
	$lwtv_dc_mnorm = (string) $lwtv_dc_mk;
	if ( false === strpos( $lwtv_dc_mnorm, '-' ) && 8 === strlen( $lwtv_dc_mnorm ) ) {
		$lwtv_dc_mnorm = substr( $lwtv_dc_mnorm, 0, 4 ) . '-' . substr( $lwtv_dc_mnorm, 4, 2 ) . '-' . substr( $lwtv_dc_mnorm, 6, 2 );
	}
	$lwtv_dc_mts = strtotime( $lwtv_dc_mnorm );
	if ( ! $lwtv_dc_mts ) {
		continue;
	}
	$lwtv_dc_mnum                         = (int) gmdate( 'n', $lwtv_dc_mts );
	$lwtv_dc_month_tally[ $lwtv_dc_mnum ] = ( $lwtv_dc_month_tally[ $lwtv_dc_mnum ] ?? 0 ) + count( (array) $lwtv_dc_mchars );
}
arsort( $lwtv_dc_month_tally );
$lwtv_dc_top_month_num   = (int) ( array_key_first( $lwtv_dc_month_tally ) ?? 0 );
$lwtv_dc_top_month_count = $lwtv_dc_top_month_num ? (int) $lwtv_dc_month_tally[ $lwtv_dc_top_month_num ] : 0;
$lwtv_dc_top_month_name  = ( $lwtv_dc_top_month_num && isset( $GLOBALS['wp_locale'] ) ) ? $GLOBALS['wp_locale']->get_month( $lwtv_dc_top_month_num ) : '';

// Deadliest show: the show with the most deaths. A tie for the top — including
// every show having the same count — means no single show stands out.
$lwtv_dc_show_max    = 0;
$lwtv_dc_show_top    = null;
$lwtv_dc_show_tiecnt = 0;
foreach ( $dead_by_show as $lwtv_dc_srow ) {
	$lwtv_dc_sn = count( (array) ( $lwtv_dc_srow['characters'] ?? array() ) );
	if ( $lwtv_dc_sn > $lwtv_dc_show_max ) {
		$lwtv_dc_show_max    = $lwtv_dc_sn;
		$lwtv_dc_show_top    = $lwtv_dc_srow['show'] ?? null;
		$lwtv_dc_show_tiecnt = 1;
	} elseif ( $lwtv_dc_sn === $lwtv_dc_show_max ) {
		++$lwtv_dc_show_tiecnt;
	}
}
$lwtv_dc_show_standout = ( $lwtv_dc_show_top && 1 === $lwtv_dc_show_tiecnt );
?>

<div class="lwtv-ty-section-head">
	<h2 class="lwtv-ty-dc-title">
		<span class="lwtv-ty-dc-count" data-count-to="<?php echo (int) $lwtv_dc_count; ?>"><?php echo esc_html( number_format_i18n( $lwtv_dc_count ) ); ?></span>
		<?php
		printf(
			/* translators: %s: the year being reviewed. */
			esc_html( _n( 'character died in %s', 'characters died in %s', $lwtv_dc_count, 'lwtv' ) ),
			esc_html( (string) $this_year )
		);
		?>
	</h2>

	<ul class="nav nav-pills lwtv-ty-pills" id="lwtv-ty-dc-tabs" role="tablist">
		<li class="nav-item">
			<a class="nav-link active" id="lwtv-ty-dc-bydate-tab" data-bs-toggle="pill" href="#lwtv-ty-dc-bydate" role="tab" aria-controls="lwtv-ty-dc-bydate" aria-selected="true"><?php esc_html_e( 'By Date', 'lwtv' ); ?></a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="lwtv-ty-dc-byshow-tab" data-bs-toggle="pill" href="#lwtv-ty-dc-byshow" role="tab" aria-controls="lwtv-ty-dc-byshow" aria-selected="false"><?php esc_html_e( 'By Show', 'lwtv' ); ?></a>
		</li>
	</ul>
</div>

<p class="lwtv-ty-section-subtitle">
	<?php
	printf(
		/* translators: %s: the year being reviewed. */
		esc_html__( 'Queer characters we lost in %s.', 'lwtv' ),
		esc_html( (string) $this_year )
	);
	?>
	<a href="<?php echo esc_url( home_url( '/statistics/death/' ) ); ?>"><?php esc_html_e( 'See the full death statistics →', 'lwtv' ); ?></a>
</p>

<div class="lwtv-trend-callouts">
	<div class="lwtv-trend-callout">
		<div class="lwtv-trend-callout-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Deadliest Month', 'lwtv' ); ?></span>
			<p class="lwtv-trend-callout-text">
				<?php
				if ( $lwtv_dc_top_month_name ) {
					printf(
						/* translators: 1: month name, 2: number of characters who died that month. */
						esc_html( _n( '%2$s character died in %1$s.', '%2$s characters died in %1$s.', $lwtv_dc_top_month_count, 'lwtv' ) ),
						esc_html( $lwtv_dc_top_month_name ),
						esc_html( number_format_i18n( $lwtv_dc_top_month_count ) )
					);
				}
				?>
			</p>
		</div>
		<span class="lwtv-trend-callout-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'calendar-alt.svg', icon: 'svg-calendar-alt', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</div>
	<div class="lwtv-trend-callout">
		<div class="lwtv-trend-callout-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Deadliest Show', 'lwtv' ); ?></span>
			<p class="lwtv-trend-callout-text">
				<?php
				if ( $lwtv_dc_show_standout ) {
					printf(
						/* translators: 1: show name, 2: number of that show's queer characters who died this year. */
						esc_html( _n( '%1$s lost %2$s queer character this year.', '%1$s lost %2$s queer characters this year.', $lwtv_dc_show_max, 'lwtv' ) ),
						esc_html( $lwtv_dc_show_top['name'] ),
						esc_html( number_format_i18n( $lwtv_dc_show_max ) )
					);
				} else {
					esc_html_e( 'No show stands out above the rest.', 'lwtv' );
				}
				?>
			</p>
		</div>
		<span class="lwtv-trend-callout-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</div>
</div>

<div class="tab-content" id="lwtv-ty-dc-tabContent">

	<div class="tab-pane fade show active" id="lwtv-ty-dc-bydate" role="tabpanel" aria-labelledby="lwtv-ty-dc-bydate-tab">
		<div class="lwtv-ty-deathdate">
			<?php
			foreach ( $dead_by_date as $lwtv_ty_dd_date_key => $lwtv_ty_dd_chars ) :
				// The date keys are usually Y-m-d, but a handful of legacy rows were
				// saved without dashes (Ymd). Normalize before formatting/parsing.
				$lwtv_ty_dd_date_display = (string) $lwtv_ty_dd_date_key;
				if ( false === strpos( $lwtv_ty_dd_date_display, '-' ) && 8 === strlen( $lwtv_ty_dd_date_display ) ) {
					$lwtv_ty_dd_date_display = substr( $lwtv_ty_dd_date_display, 0, 4 ) . '-' . substr( $lwtv_ty_dd_date_display, 4, 2 ) . '-' . substr( $lwtv_ty_dd_date_display, 6, 2 );
				}
				?>
				<div class="lwtv-ty-deathdate-row">
					<div class="lwtv-ty-deathdate-date"><?php echo esc_html( gmdate( 'M j', strtotime( $lwtv_ty_dd_date_display ) ) ); ?></div>
					<div class="lwtv-ty-deathdate-chars">
						<?php
						foreach ( $lwtv_ty_dd_chars as $lwtv_ty_dd_char ) :
							$lwtv_ty_dd_show = $lwtv_ty_dd_char['shows'][0] ?? null;
							?>
							<div class="lwtv-ty-deathdate-char">
								<a href="<?php echo esc_url( home_url( '/character/' . $lwtv_ty_dd_char['slug'] . '/' ) ); ?>"><?php echo esc_html( $lwtv_ty_dd_char['name'] ); ?></a>
								<?php if ( $lwtv_ty_dd_show ) : ?>
									<span class="lwtv-ty-deathdate-show"> (<em><a href="<?php echo esc_url( $lwtv_ty_dd_show['url'] ); ?>"><?php echo esc_html( $lwtv_ty_dd_show['name'] ); ?></a></em>)</span>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="tab-pane fade" id="lwtv-ty-dc-byshow" role="tabpanel" aria-labelledby="lwtv-ty-dc-byshow-tab">
		<div class="lwtv-ty-deathshow">
			<?php
			foreach ( $dead_by_show as $lwtv_ty_ds_row ) :
				$lwtv_ty_ds_show  = $lwtv_ty_ds_row['show'];
				$lwtv_ty_ds_chars = $lwtv_ty_ds_row['characters'] ?? array();
				$lwtv_ty_ds_meta  = array_filter(
					array(
						$lwtv_ty_ds_show['nations'][0]['name'] ?? '',
						$lwtv_ty_ds_show['formats'][0]['name'] ?? '',
					)
				);
				?>
				<div class="lwtv-ty-deathshow-card">
					<div class="lwtv-ty-deathshow-head">
						<a href="<?php echo esc_url( $lwtv_ty_ds_show['url'] ); ?>" class="lwtv-ty-deathshow-link"><?php echo esc_html( $lwtv_ty_ds_show['name'] ); ?></a>
						<span class="badge lwtv-ty-deathshow-count"><?php echo esc_html( number_format_i18n( count( $lwtv_ty_ds_chars ) ) ); ?></span>
					</div>
					<?php if ( ! empty( $lwtv_ty_ds_meta ) ) : ?>
						<div class="lwtv-ty-deathshow-meta"><?php echo esc_html( implode( ' · ', $lwtv_ty_ds_meta ) ); ?></div>
					<?php endif; ?>
					<div class="lwtv-ty-deathshow-chars">
						<?php foreach ( $lwtv_ty_ds_chars as $lwtv_ty_ds_char ) : ?>
							<a href="<?php echo esc_url( $lwtv_ty_ds_char['url'] ); ?>" class="lwtv-ty-deathshow-charlink"><?php echo esc_html( $lwtv_ty_ds_char['name'] ); ?></a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

</div>
