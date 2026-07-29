<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\This_Year\Build\Characters_On_Air;
use LWTV\This_Year\Build\Dead_Characters;

/**
 * This Year — Dead Characters: By Date rows / By Show cards / empty state.
 *
 * @package LezWatch.TV
 *
 * @var int   $this_year
 * @var int   $dead_characters_count
 * @var array $dead_by_date Keyed by death-date string → list of { slug, name, dead, death_years, shows:[{name,url,type}] }.
 *                           Character URL is built from slug (canonical /character/{slug}/).
 * @var array $dead_by_show Keyed by show_id → { show:{name,url,nations:[{name}],formats:[{name}]}, characters:[{name,url,type}] }.
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

// Deaths-by-month graph model + the longest-stretch fact (pure transforms).
$lwtv_dc_months  = Dead_Characters::months( $dead_by_date );
$lwtv_dc_stretch = Dead_Characters::longest_stretch( $dead_by_date );

// Localized month helpers (locale is a WP concern — kept out of the transform).
$lwtv_dc_month_name = static function ( int $lwtv_dc_n ): string {
	return isset( $GLOBALS['wp_locale'] ) ? $GLOBALS['wp_locale']->get_month( $lwtv_dc_n ) : (string) $lwtv_dc_n;
};
$lwtv_dc_month_abbr = static function ( int $lwtv_dc_n ) use ( $lwtv_dc_month_name ): string {
	return isset( $GLOBALS['wp_locale'] ) ? $GLOBALS['wp_locale']->get_month_abbrev( $lwtv_dc_month_name( $lwtv_dc_n ) ) : (string) $lwtv_dc_n;
};

// Peak month + the "recorded none" list, both from the graph model.
$lwtv_dc_peak_count = 0;
$lwtv_dc_peak_nums  = array();
$lwtv_dc_empty_nums = array();
foreach ( $lwtv_dc_months as $lwtv_dc_m ) {
	if ( $lwtv_dc_m['peak'] ) {
		$lwtv_dc_peak_count  = $lwtv_dc_m['count'];
		$lwtv_dc_peak_nums[] = $lwtv_dc_m['num'];
	}
	if ( $lwtv_dc_m['empty'] ) {
		$lwtv_dc_empty_nums[] = $lwtv_dc_m['num'];
	}
}

// Oxford-free "or" join of month names, capped at 3 then "the next N months".
$lwtv_dc_join_months = static function ( array $lwtv_dc_nums ) use ( $lwtv_dc_month_name ): string {
	$names = array_map( static fn( $lwtv_dc_n ) => $lwtv_dc_month_name( (int) $lwtv_dc_n ), $lwtv_dc_nums );
	if ( count( $names ) <= 1 ) {
		return implode( '', $names );
	}
	$last = array_pop( $names );
	/* translators: 1: comma-separated month names, 2: the final month name. */
	return sprintf( __( '%1$s or %2$s', 'lwtv' ), implode( ', ', $names ), $last );
};
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

<div class="lwtv-trend-callouts<?php echo ( null === $lwtv_dc_stretch ) ? ' lwtv-trend-callouts--single' : ''; ?>">
	<div class="lwtv-trend-callout">
		<div class="lwtv-trend-callout-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Deadliest Show', 'lwtv' ); ?></span>
			<p class="lwtv-trend-callout-text">
				<?php
				if ( $lwtv_dc_show_standout ) {
					printf(
						/* translators: 1: show name (emphasized), 2: number of that show's queer characters who died. */
						esc_html( _n( '%1$s lost %2$s queer character this year.', '%1$s lost %2$s queer characters this year.', $lwtv_dc_show_max, 'lwtv' ) ),
						'<em>' . esc_html( $lwtv_dc_show_top['name'] ) . '</em>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	<?php if ( null !== $lwtv_dc_stretch ) : ?>
	<div class="lwtv-trend-callout">
		<div class="lwtv-trend-callout-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Longest Stretch', 'lwtv' ); ?></span>
			<p class="lwtv-trend-callout-text">
				<?php
				printf(
					/* translators: 1: number of days, 2: start date, 3: end date. */
					esc_html( _n( '%1$s day passed without a death, from %2$s to %3$s.', '%1$s days passed without a death, from %2$s to %3$s.', $lwtv_dc_stretch['days'], 'lwtv' ) ),
					esc_html( number_format_i18n( $lwtv_dc_stretch['days'] ) ),
					esc_html( gmdate( 'F j', strtotime( $lwtv_dc_stretch['from'] ) ) ),
					esc_html( gmdate( 'F j', strtotime( $lwtv_dc_stretch['to'] ) ) )
				);
				?>
			</p>
		</div>
		<span class="lwtv-trend-callout-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'calendar-alt.svg', icon: 'svg-calendar-alt', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</div>
	<?php endif; ?>
</div>

<div class="tab-content" id="lwtv-ty-dc-tabContent">

	<div class="tab-pane fade show active" id="lwtv-ty-dc-bydate" role="tabpanel" aria-labelledby="lwtv-ty-dc-bydate-tab">
		<div class="lwtv-ty-dc-graph">
			<div class="lwtv-ty-dc-graph-head">
				<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Deaths by month', 'lwtv' ); ?></span>
				<span class="lwtv-ty-dc-graph-hint"><?php esc_html_e( 'Click a month to jump to that section', 'lwtv' ); ?></span>
			</div>
			<div class="lwtv-ty-dc-bars" role="list">
				<?php
				$lwtv_dc_max = max( array_column( $lwtv_dc_months, 'count' ) );
				foreach ( $lwtv_dc_months as $lwtv_dc_col ) :
					$lwtv_dc_h   = ( $lwtv_dc_max > 0 && $lwtv_dc_col['count'] > 0 )
						? max( 3, (int) round( $lwtv_dc_col['count'] / $lwtv_dc_max * 42 ) )
						: 3;
					$lwtv_dc_cls = 'lwtv-ty-dc-bar';
					if ( $lwtv_dc_col['empty'] ) {
						$lwtv_dc_cls .= ' is-empty';
					} elseif ( $lwtv_dc_col['peak'] ) {
						$lwtv_dc_cls .= ' is-peak';
					}
					?>
					<?php if ( $lwtv_dc_col['empty'] ) : ?>
						<span class="<?php echo esc_attr( $lwtv_dc_cls ); ?>" role="listitem" aria-disabled="true">
							<span class="lwtv-ty-dc-bar-count">&mdash;</span>
							<span class="lwtv-ty-dc-bar-fill" style="height:3px"></span>
							<span class="lwtv-ty-dc-bar-label"><?php echo esc_html( $lwtv_dc_month_abbr( $lwtv_dc_col['num'] ) ); ?></span>
						</span>
					<?php else : ?>
						<a class="<?php echo esc_attr( $lwtv_dc_cls ); ?>" role="listitem"
							href="#lwtv-ty-dc-month-<?php echo (int) $lwtv_dc_col['num']; ?>"
							aria-label="
							<?php
							printf(
								/* translators: 1: month name, 2: number of deaths that month. */
								esc_attr__( 'Jump to %1$s, %2$s deaths', 'lwtv' ),
								esc_attr( $lwtv_dc_month_name( $lwtv_dc_col['num'] ) ),
								esc_attr( number_format_i18n( $lwtv_dc_col['count'] ) )
							);
							?>
							">
							<span class="lwtv-ty-dc-bar-count"><?php echo esc_html( number_format_i18n( $lwtv_dc_col['count'] ) ); ?></span>
							<span class="lwtv-ty-dc-bar-fill" style="height:<?php echo (int) $lwtv_dc_h; ?>px"></span>
							<span class="lwtv-ty-dc-bar-label"><?php echo esc_html( $lwtv_dc_month_abbr( $lwtv_dc_col['num'] ) ); ?></span>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<div class="lwtv-ty-dc-graph-foot">
				<?php if ( $lwtv_dc_peak_count > 0 && 1 === count( $lwtv_dc_peak_nums ) ) : ?>
					<span class="lwtv-ty-dc-fact">
						<?php
						printf(
							/* translators: 1: month name, 2: number of deaths. */
							esc_html( _n( '%1$s was the deadliest month, %2$s death', '%1$s was the deadliest month, %2$s deaths', $lwtv_dc_peak_count, 'lwtv' ) ),
							'<strong>' . esc_html( $lwtv_dc_month_name( $lwtv_dc_peak_nums[0] ) ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							esc_html( number_format_i18n( $lwtv_dc_peak_count ) )
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( ! empty( $lwtv_dc_empty_nums ) ) : ?>
					<span class="lwtv-ty-dc-fact lwtv-ty-dc-fact--none">
						<?php
						printf(
							/* translators: %s: list of month names that recorded no deaths. */
							esc_html__( '%s recorded none', 'lwtv' ),
							esc_html( $lwtv_dc_join_months( $lwtv_dc_empty_nums ) )
						);
						?>
					</span>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$lwtv_dc_role_labels = array(
			'regular'   => __( 'Regular', 'lwtv' ),
			'recurring' => __( 'Recurring', 'lwtv' ),
			'guest'     => __( 'Guest', 'lwtv' ),
		);
		$lwtv_dc_timeline    = Dead_Characters::timeline( $dead_by_date );
		?>
		<div class="lwtv-ty-dc-timeline">
			<?php foreach ( $lwtv_dc_timeline as $lwtv_dc_item ) : ?>
				<?php if ( 'waypoint' === $lwtv_dc_item['type'] ) : ?>
					<div class="lwtv-ty-dc-tl-waypoint" id="lwtv-ty-dc-month-<?php echo (int) $lwtv_dc_item['month']; ?>">
						<div class="lwtv-ty-dc-tl-gutter"><?php echo esc_html( $lwtv_dc_month_name( $lwtv_dc_item['month'] ) ); ?></div>
						<div class="lwtv-ty-dc-tl-rail"><span class="lwtv-ty-dc-pip-month"></span></div>
						<div class="lwtv-ty-dc-tl-content">
							<?php
							printf(
								/* translators: %s: number of deaths that month. */
								esc_html( _n( '%s death', '%s deaths', $lwtv_dc_item['count'], 'lwtv' ) ),
								esc_html( number_format_i18n( $lwtv_dc_item['count'] ) )
							);
							?>
						</div>
					</div>
				<?php elseif ( 'gap' === $lwtv_dc_item['type'] ) : ?>
					<div class="lwtv-ty-dc-tl-gap">
						<div class="lwtv-ty-dc-tl-gutter"></div>
						<div class="lwtv-ty-dc-tl-rail lwtv-ty-dc-tl-rail--dashed"></div>
						<div class="lwtv-ty-dc-tl-content">
							<?php
							if ( count( $lwtv_dc_item['months'] ) >= 4 ) {
								printf(
									/* translators: %s: number of consecutive months with no deaths. */
									esc_html( _n( 'No deaths for the next %s month', 'No deaths for the next %s months', count( $lwtv_dc_item['months'] ), 'lwtv' ) ),
									esc_html( number_format_i18n( count( $lwtv_dc_item['months'] ) ) )
								);
							} else {
								printf(
									/* translators: %s: a list of month names. */
									esc_html__( 'No deaths in %s', 'lwtv' ),
									esc_html( $lwtv_dc_join_months( $lwtv_dc_item['months'] ) )
								);
							}
							?>
						</div>
					</div>
				<?php elseif ( 'death' === $lwtv_dc_item['type'] ) : ?>
					<?php $lwtv_dc_ts = strtotime( $lwtv_dc_item['date'] ); ?>
					<div class="lwtv-ty-dc-tl-death" data-month="<?php echo (int) gmdate( 'n', $lwtv_dc_ts ); ?>">
						<div class="lwtv-ty-dc-tl-gutter lwtv-ty-dc-tl-date"><?php echo esc_html( gmdate( 'M j', $lwtv_dc_ts ) ); ?></div>
						<div class="lwtv-ty-dc-tl-rail"><span class="lwtv-ty-dc-pip-death"></span></div>
						<div class="lwtv-ty-dc-tl-content">
							<a class="lwtv-ty-dc-tl-name" href="<?php echo esc_url( home_url( '/character/' . $lwtv_dc_item['slug'] . '/' ) ); ?>"><?php echo esc_html( $lwtv_dc_item['name'] ); ?></a>
							<div class="lwtv-ty-dc-tl-meta">
								<em class="lwtv-ty-dc-tl-shows">
									<?php
									$lwtv_dc_show_links = array();
									foreach ( $lwtv_dc_item['shows'] as $lwtv_dc_show ) {
										$lwtv_dc_show_links[] = '<a href="' . esc_url( $lwtv_dc_show['url'] ) . '">' . esc_html( $lwtv_dc_show['name'] ) . '</a>';
									}
									echo implode( ' · ', $lwtv_dc_show_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								</em>
								<?php if ( '' !== $lwtv_dc_item['role'] ) : ?>
									<span class="lwtv-ty-dc-chip">
										<span class="lwtv-ty-coa-role-dot role-<?php echo esc_attr( $lwtv_dc_item['role'] ); ?>"></span>
										<?php echo esc_html( $lwtv_dc_role_labels[ $lwtv_dc_item['role'] ] ?? ucfirst( $lwtv_dc_item['role'] ) ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php elseif ( 'tail' === $lwtv_dc_item['type'] ) : ?>
					<div class="lwtv-ty-dc-tl-tail">
						<div class="lwtv-ty-dc-tl-gutter"></div>
						<div class="lwtv-ty-dc-tl-rail"><span class="lwtv-ty-dc-pip-tail"></span></div>
						<div class="lwtv-ty-dc-tl-content">
							<?php
							// Two independent plurals (characters, months) can't share one _n();
							// build them separately and drop the months clause when none are empty.
							$lwtv_dc_tail = sprintf(
								/* translators: %s: total number of characters who died this year. */
								esc_html( _n( '%s character, in the order we lost them.', '%s characters, in the order we lost them.', $lwtv_dc_item['total'], 'lwtv' ) ),
								esc_html( number_format_i18n( $lwtv_dc_item['total'] ) )
							);
							if ( $lwtv_dc_item['empty_month_count'] > 0 ) {
								$lwtv_dc_tail .= ' ' . sprintf(
									/* translators: %s: number of months that recorded no deaths. */
									esc_html( _n( '%s month recorded no deaths.', '%s months recorded no deaths.', $lwtv_dc_item['empty_month_count'], 'lwtv' ) ),
									esc_html( number_format_i18n( $lwtv_dc_item['empty_month_count'] ) )
								);
							}
							echo $lwtv_dc_tail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="tab-pane fade" id="lwtv-ty-dc-byshow" role="tabpanel" aria-labelledby="lwtv-ty-dc-byshow-tab">
		<?php
		// Match the Characters On Air "By Show" treatment: shows alphabetized
		// (leading article ignored), natural-height cards, cast rendered as
		// alphabetized rows with a role dot. Role/name filtering + sort is the
		// shared Characters_On_Air::cast_for_show() transform.
		$lwtv_dc_role_labels = array(
			'regular'   => __( 'Regular', 'lwtv' ),
			'recurring' => __( 'Recurring', 'lwtv' ),
			'guest'     => __( 'Guest', 'lwtv' ),
		);

		$lwtv_dc_ds_sort_key = static function ( string $lwtv_dc_ds_name ): string {
			return preg_replace( '/^(?:a|an|the)\s+/i', '', trim( $lwtv_dc_ds_name ) );
		};

		$lwtv_dc_by_show_sorted = $dead_by_show;
		usort(
			$lwtv_dc_by_show_sorted,
			static function ( $lwtv_dc_ds_a, $lwtv_dc_ds_b ) use ( $lwtv_dc_ds_sort_key ) {
				return strnatcasecmp(
					$lwtv_dc_ds_sort_key( (string) ( $lwtv_dc_ds_a['show']['name'] ?? '' ) ),
					$lwtv_dc_ds_sort_key( (string) ( $lwtv_dc_ds_b['show']['name'] ?? '' ) )
				);
			}
		);
		?>
		<div class="lwtv-ty-coa-sortnote">
			<span class="lwtv-ty-coa-sortpill"><?php esc_html_e( 'Shows A–Z, articles ignored', 'lwtv' ); ?></span>
			<span class="lwtv-ty-coa-sortnote-text"><?php esc_html_e( '“The Beast in Me” files under B; numeric titles like 9-1-1 lead.', 'lwtv' ); ?></span>
		</div>
		<div class="lwtv-ty-charshow">
			<?php
			foreach ( $lwtv_dc_by_show_sorted as $lwtv_ty_ds_row ) :
				$lwtv_ty_ds_show  = $lwtv_ty_ds_row['show'];
				$lwtv_ty_ds_chars = Characters_On_Air::cast_for_show( $lwtv_ty_ds_row['characters'] ?? array() );
				$lwtv_ty_ds_meta  = array_filter(
					array(
						$lwtv_ty_ds_show['nations'][0]['name'] ?? '',
						$lwtv_ty_ds_show['formats'][0]['name'] ?? '',
					)
				);
				?>
				<div class="lwtv-ty-charshow-card">
					<div class="lwtv-ty-charshow-head">
						<a href="<?php echo esc_url( $lwtv_ty_ds_show['url'] ); ?>" class="lwtv-ty-charshow-link"><?php echo esc_html( $lwtv_ty_ds_show['name'] ); ?></a>
						<span class="badge lwtv-ty-charshow-count"><?php echo esc_html( number_format_i18n( count( $lwtv_ty_ds_chars ) ) ); ?></span>
					</div>
					<?php if ( ! empty( $lwtv_ty_ds_meta ) ) : ?>
						<div class="lwtv-ty-charshow-meta"><?php echo esc_html( implode( ' · ', $lwtv_ty_ds_meta ) ); ?></div>
					<?php endif; ?>
					<div class="lwtv-ty-charshow-cast">
						<?php foreach ( $lwtv_ty_ds_chars as $lwtv_ty_ds_char ) : ?>
							<div class="lwtv-ty-charshow-castrow">
								<a href="<?php echo esc_url( $lwtv_ty_ds_char['url'] ); ?>" class="lwtv-ty-charshow-castname"><?php echo esc_html( $lwtv_ty_ds_char['name'] ); ?></a>
								<?php if ( ! empty( $lwtv_ty_ds_char['type'] ) ) : ?>
									<span class="lwtv-ty-charshow-castrole">
										<span class="lwtv-ty-coa-role-dot role-<?php echo esc_attr( $lwtv_ty_ds_char['type'] ); ?>"></span>
										<?php echo esc_html( $lwtv_dc_role_labels[ $lwtv_ty_ds_char['type'] ] ?? ucfirst( $lwtv_ty_ds_char['type'] ) ); ?>
									</span>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

</div>
