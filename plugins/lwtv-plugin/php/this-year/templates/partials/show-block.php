<?php

use LWTV\This_Year\Build\Shows_Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This Year — shared Shows / New Shows / Canceled Shows block.
 *
 * Renders a count-up header, a By Name / By Format / By Country pill toggle,
 * and three tab-panes of two-column group cards. Included by
 * shows-on-air.php, new-shows.php, and canceled-shows.php after each sets
 * the $sb_* contract vars below.
 *
 * @package LezWatch.TV
 *
 * @var string $sb_accent     'blue'|'pink'|'amber' — drives the view's accent color.
 * @var int    $sb_count      Header count (also used for the count-up animation).
 * @var string $sb_title      Fully assembled heading sentence, count + copy + year.
 * @var string $sb_desc       Subtitle sentence under the header.
 * @var string $sb_foot       Footnote sentence under the group-card grid.
 * @var string $sb_source     Source (new, canceled, on-air)
 * @var array  $sb_by_name    [ marker => [ showName => {url,name,country,format,airdates} ] ].
 * @var array  $sb_by_format  [ format => [ showName => {url,name,country,format,airdates} ] ].
 * @var array  $sb_by_country [ country => [ showName => {url,name,country,format,airdates} ] ].
 * @var array  $sb_callouts   Optional [ {label,text,icon} ] callout boxes under the subtitle.
 *                            Auto-derived (most-popular letter / format / country) when a caller
 *                            leaves it unset; pass an empty array to suppress the callouts.
 */

$lwtv_sb_accent = in_array( $sb_accent, array( 'blue', 'pink', 'amber' ), true ) ? $sb_accent : 'blue';
$lwtv_sb_count  = (int) $sb_count;

// Splice a `data-count-to` span around the number inside the already-assembled
// $sb_title sentence, so the header reads as one phrase (no duplicated count)
// while still giving the count-up script a target to animate.
$lwtv_sb_num_str    = number_format_i18n( $lwtv_sb_count );
$lwtv_sb_title_esc  = esc_html( $sb_title );
$lwtv_sb_num_pos    = strpos( $lwtv_sb_title_esc, $lwtv_sb_num_str );
$lwtv_sb_count_span = '<span class="lwtv-ty-sb-count" data-count-to="' . $lwtv_sb_count . '">' . $lwtv_sb_num_str . '</span>';

if ( false !== $lwtv_sb_num_pos ) {
	$lwtv_sb_title_html = substr( $lwtv_sb_title_esc, 0, $lwtv_sb_num_pos )
		. $lwtv_sb_count_span
		. substr( $lwtv_sb_title_esc, $lwtv_sb_num_pos + strlen( $lwtv_sb_num_str ) );
} else {
	// Fallback if the count can't be located in the sentence (unexpected title shape).
	$lwtv_sb_title_html = $lwtv_sb_count_span . ' ' . $lwtv_sb_title_esc;
}

/**
 * Render one pane: a sticky jump bar + a hanging-gutter two-column list
 * (or an empty-state line).
 *
 * @param array  $lwtv_sb_groups      [ groupKey => [ showName => {url,name,country,format} ] ].
 * @param string $lwtv_sb_meta_mode   Which non-grouping dimension(s) to show per item: 'name'|'format'|'country'.
 * @param string $lwtv_sb_pane_accent Accent slug for the group-key color.
 * @param string $lwtv_sb_slug        Pane slug ('byname'|'byformat'|'bycountry') — id prefix.
 */
$lwtv_sb_render_pane = static function ( array $lwtv_sb_groups, string $lwtv_sb_meta_mode, string $lwtv_sb_pane_accent, string $lwtv_sb_slug ) {
	if ( empty( $lwtv_sb_groups ) ) {
		?>
		<p class="lwtv-ty-group-empty"><?php esc_html_e( 'None this year.', 'lwtv' ); ?></p>
		<?php
		return;
	}

	$lwtv_sb_keys = array_keys( $lwtv_sb_groups );

	// The jump bar and the meta line share the grouping dimension; counts only
	// matter for the (three-chip) format bar.
	$lwtv_sb_counts = array();
	if ( 'format' === $lwtv_sb_meta_mode ) {
		foreach ( $lwtv_sb_groups as $lwtv_sb_k => $lwtv_sb_v ) {
			$lwtv_sb_counts[ (string) $lwtv_sb_k ] = count( (array) $lwtv_sb_v );
		}
	}
	$lwtv_sb_bar = Shows_Block::jump_bar( $lwtv_sb_keys, $lwtv_sb_meta_mode, $lwtv_sb_counts );

	switch ( $lwtv_sb_meta_mode ) {
		case 'format':
			$lwtv_sb_eyebrow = __( 'Jump to a format', 'lwtv' );
			break;
		case 'country':
			$lwtv_sb_eyebrow = __( 'Jump to a country', 'lwtv' );
			break;
		default:
			$lwtv_sb_eyebrow = __( 'Jump to a letter', 'lwtv' );
			break;
	}

	// Format dot class per format name; unknown/empty formats get no dot.
	$lwtv_sb_dot_class = static function ( string $format ): string {
		switch ( $format ) {
			case 'TV Show':
				return 'lwtv-ty-sb-dot--tv';
			case 'Mini-Series':
				return 'lwtv-ty-sb-dot--mini';
			case 'Web Series':
				return 'lwtv-ty-sb-dot--web';
			default:
				return '';
		}
	};
	?>
	<nav class="lwtv-ty-sb-jump" id="<?php echo esc_attr( $lwtv_sb_slug ); ?>-jump" aria-label="<?php echo esc_attr( $lwtv_sb_eyebrow ); ?>">
		<span class="lwtv-stats-eyebrow"><?php echo esc_html( $lwtv_sb_eyebrow ); ?></span>
		<div class="lwtv-ty-sb-chips">
			<?php
			foreach ( $lwtv_sb_bar as $lwtv_sb_chip ) :
				$lwtv_sb_chip_label = $lwtv_sb_chip['label'];
				if ( null !== $lwtv_sb_chip['count'] ) {
					$lwtv_sb_chip_label .= ' ' . number_format_i18n( (int) $lwtv_sb_chip['count'] );
				}
				?>
				<?php if ( $lwtv_sb_chip['struck'] ) : ?>
					<span class="lwtv-ty-sb-chip lwtv-ty-sb-chip--empty"><?php echo esc_html( $lwtv_sb_chip_label ); ?></span>
				<?php else : ?>
					<a class="lwtv-ty-sb-chip" href="#<?php echo esc_attr( $lwtv_sb_slug . '-g' . $lwtv_sb_chip['target'] ); ?>"><?php echo esc_html( $lwtv_sb_chip_label ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</nav>

	<div class="lwtv-ty-group-grid lwtv-ty-group-grid--<?php echo esc_attr( $lwtv_sb_pane_accent ); ?>">
		<?php $lwtv_sb_i = 0; ?>
		<?php foreach ( $lwtv_sb_groups as $lwtv_sb_group_key => $lwtv_sb_shows ) : ?>
			<div class="lwtv-ty-sb-row" id="<?php echo esc_attr( $lwtv_sb_slug . '-g' . $lwtv_sb_i ); ?>">
				<div class="lwtv-ty-sb-gutter">
					<span class="lwtv-ty-group-key"><?php echo esc_html( (string) $lwtv_sb_group_key ); ?></span>
					<span class="lwtv-ty-group-count">
						<?php
						/* translators: %s: number of shows in this group. */
						echo esc_html( sprintf( _n( '%s show', '%s shows', count( $lwtv_sb_shows ), 'lwtv' ), number_format_i18n( count( $lwtv_sb_shows ) ) ) );
						?>
					</span>
				</div>

				<div class="lwtv-ty-sb-shows">
					<?php
					foreach ( $lwtv_sb_shows as $lwtv_sb_show ) :
						switch ( $lwtv_sb_meta_mode ) {
							case 'name':
								$lwtv_sb_meta = implode(
									' · ',
									array_filter(
										array(
											(string) ( $lwtv_sb_show['country'] ?? '' ),
											(string) ( $lwtv_sb_show['format'] ?? '' ),
										)
									)
								);
								break;

							case 'format':
								$lwtv_sb_meta = (string) ( $lwtv_sb_show['country'] ?? '' );
								break;

							default: // 'country'.
								$lwtv_sb_meta = (string) ( $lwtv_sb_show['format'] ?? '' );
								break;
						}
						$lwtv_sb_dot = $lwtv_sb_dot_class( (string) ( $lwtv_sb_show['format'] ?? '' ) );
						?>
						<div class="lwtv-ty-sb-item">
							<span class="lwtv-ty-sb-dot <?php echo esc_attr( $lwtv_sb_dot ); ?>" aria-hidden="true"></span>
							<a class="lwtv-ty-sb-title" href="<?php echo esc_url( $lwtv_sb_show['url'] ); ?>"><?php echo esc_html( $lwtv_sb_show['name'] ); ?></a>
							<?php if ( '' !== $lwtv_sb_meta ) : ?>
								<span class="lwtv-ty-group-meta"><?php echo esc_html( $lwtv_sb_meta ); ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php ++$lwtv_sb_i; ?>
		<?php endforeach; ?>
	</div>
	<?php
};

// Auto-derive the "most popular" callouts (starting letter / format / country)
// from the grouped data unless a caller supplied — or deliberately suppressed
// with an empty array — its own $sb_callouts. Each is the largest group in the
// matching grouping; when several groups tie for the top the callout reports
// the tie instead of arbitrarily naming one.
if ( ! isset( $sb_callouts ) ) {
	// Returns [ top_key, top_count, tie_count ]. tie_count is how many groups
	// share the top count (1 = a clear winner). top_key is the first
	// (alphabetically-earliest) of the tied groups, as the data is pre-sorted.
	$lwtv_sb_top = static function ( array $groups, $letters_only = false ) {
		$lwtv_sb_counts = array();
		foreach ( $groups as $lwtv_sb_key => $lwtv_sb_shows ) {
			if ( $letters_only && 1 !== preg_match( '/^[A-Z]$/', (string) $lwtv_sb_key ) ) {
				continue;
			}
			$lwtv_sb_counts[ (string) $lwtv_sb_key ] = count( (array) $lwtv_sb_shows );
		}
		if ( empty( $lwtv_sb_counts ) ) {
			return array( null, 0, 0 );
		}
		$lwtv_sb_max  = max( $lwtv_sb_counts );
		$lwtv_sb_tied = array_keys( $lwtv_sb_counts, $lwtv_sb_max, true );
		return array( $lwtv_sb_tied[0], $lwtv_sb_max, count( $lwtv_sb_tied ) );
	};

	list( $lwtv_sb_format, $lwtv_sb_format_n, $lwtv_sb_format_ties )    = $lwtv_sb_top( $sb_by_format );
	list( $lwtv_sb_country, $lwtv_sb_country_n, $lwtv_sb_country_ties ) = $lwtv_sb_top( $sb_by_country );

	$sb_callouts = array();

	if ( $lwtv_sb_format ) {
		if ( 1 === $lwtv_sb_format_ties ) {
			/* translators: 1: format name, 2: number of shows, 3: type of output (new, canceled, on-air) */
			$lwtv_sb_format_text = sprintf( _n( '%2$s %3$s %1$s, the most common format.', '%2$s %3$s %1$ss, the most common format.', $lwtv_sb_format_n, 'lwtv' ), $lwtv_sb_format, number_format_i18n( $lwtv_sb_format_n ), $sb_source );
		} else {
			/* translators: 1: number of tied formats, 2: shows per format. */
			$lwtv_sb_format_text = sprintf( _n( '%1$s formats tie for the most, with %2$s %3$s show each.', '%1$s formats tie for the most, with %2$s %3$s shows each.', $lwtv_sb_format_n, 'lwtv' ), number_format_i18n( $lwtv_sb_format_ties ), number_format_i18n( $lwtv_sb_format_n ), $sb_source );
		}
		$sb_callouts[] = array(
			'label' => __( 'Most popular format', 'lwtv' ),
			'icon'  => 'tv.svg',
			'text'  => $lwtv_sb_format_text,
		);
	}

	if ( $lwtv_sb_country ) {
		if ( 1 === $lwtv_sb_country_ties ) {
			/* translators: 1: country name, 2: number of shows. */
			$lwtv_sb_country_text = sprintf( _n( '%1$s has %2$s %3$s show, more than any other country.', '%1$s has %2$s %3$s shows, more than any other country.', $lwtv_sb_country_n, 'lwtv' ), $lwtv_sb_country, number_format_i18n( $lwtv_sb_country_n ), $sb_source );
		} else {
			/* translators: 1: number of tied countries, 2: shows per country. */
			$lwtv_sb_country_text = sprintf( _n( '%1$s countries tie for the most, with %2$s %3$s show each.', '%1$s countries tie for the most, with %2$s %3$s shows each.', $lwtv_sb_country_n, 'lwtv' ), number_format_i18n( $lwtv_sb_country_ties ), number_format_i18n( $lwtv_sb_country_n ), $sb_source );
		}
		$sb_callouts[] = array(
			'label' => __( 'Most popular country', 'lwtv' ),
			'icon'  => 'globe.svg',
			'text'  => $lwtv_sb_country_text,
		);
	}
}
?>

<div class="lwtv-ty-section-head">
	<h2 class="lwtv-ty-block-title lwtv-ty-block-title--<?php echo esc_attr( $lwtv_sb_accent ); ?>">
		<?php
		// $lwtv_sb_title_html is built above from esc_html()'d segments plus a
		// hardcoded, fully-escaped <span> — nothing here is unescaped user input.
		echo $lwtv_sb_title_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</h2>

	<ul class="nav nav-pills lwtv-ty-pills" id="lwtv-ty-sb-tabs" role="tablist">
		<li class="nav-item">
			<a class="nav-link active" id="lwtv-ty-sb-byname-tab" data-bs-toggle="pill" href="#lwtv-ty-sb-byname" role="tab" aria-controls="lwtv-ty-sb-byname" aria-selected="true"><?php esc_html_e( 'By Name', 'lwtv' ); ?></a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="lwtv-ty-sb-byformat-tab" data-bs-toggle="pill" href="#lwtv-ty-sb-byformat" role="tab" aria-controls="lwtv-ty-sb-byformat" aria-selected="false"><?php esc_html_e( 'By Format', 'lwtv' ); ?></a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="lwtv-ty-sb-bycountry-tab" data-bs-toggle="pill" href="#lwtv-ty-sb-bycountry" role="tab" aria-controls="lwtv-ty-sb-bycountry" aria-selected="false"><?php esc_html_e( 'By Country', 'lwtv' ); ?></a>
		</li>
	</ul>
</div>

<p class="lwtv-ty-section-subtitle"><?php echo esc_html( $sb_desc ); ?></p>

<?php if ( ! empty( $sb_callouts ) && is_array( $sb_callouts ) ) : ?>
<div class="lwtv-trend-callouts">
	<?php foreach ( $sb_callouts as $lwtv_sb_callout ) : ?>
		<div class="lwtv-trend-callout">
			<div class="lwtv-trend-callout-body">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $lwtv_sb_callout['label'] ?? '' ); ?></span>
				<p class="lwtv-trend-callout-text"><?php echo esc_html( $lwtv_sb_callout['text'] ?? '' ); ?></p>
			</div>
			<?php if ( ! empty( $lwtv_sb_callout['icon'] ) ) : ?>
				<span class="lwtv-trend-callout-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_sb_callout['icon'], icon: 'svg-' . str_replace( '.svg', '', $lwtv_sb_callout['icon'] ), max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="tab-content" id="lwtv-ty-sb-tabContent">

	<div class="tab-pane fade show active" id="lwtv-ty-sb-byname" role="tabpanel" aria-labelledby="lwtv-ty-sb-byname-tab">
		<?php $lwtv_sb_render_pane( $sb_by_name, 'name', $lwtv_sb_accent, 'byname' ); ?>
	</div>

	<div class="tab-pane fade" id="lwtv-ty-sb-byformat" role="tabpanel" aria-labelledby="lwtv-ty-sb-byformat-tab">
		<?php $lwtv_sb_render_pane( $sb_by_format, 'format', $lwtv_sb_accent, 'byformat' ); ?>
	</div>

	<div class="tab-pane fade" id="lwtv-ty-sb-bycountry" role="tabpanel" aria-labelledby="lwtv-ty-sb-bycountry-tab">
		<?php $lwtv_sb_render_pane( $sb_by_country, 'country', $lwtv_sb_accent, 'bycountry' ); ?>
	</div>

</div>

<p class="lwtv-ty-block-foot"><?php echo esc_html( $sb_foot ); ?></p>
