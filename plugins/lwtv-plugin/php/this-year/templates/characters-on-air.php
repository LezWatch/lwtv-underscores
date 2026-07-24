<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This Year — Characters On Air: By Name grid + By Show cast cards.
 *
 * @package LezWatch.TV
 *
 * @var int   $this_year
 * @var int   $characters_on_air_count
 * @var array $characters_on_air         Numeric list of { slug, name, dead, death_years, shows:[{name,url}] }. Character URL is built from slug (canonical /character/{slug}/).
 * @var array $characters_on_air_by_show Numeric list of { slug, name, started, ended, characters:[{character_id,type,dead,last_death,name,url}], nations:[{name}], formats:[{name}] }.
 */

$lwtv_coa_count = (int) $characters_on_air_count;

// Empty state — guard first, nothing else in this template applies.
if ( 0 === $lwtv_coa_count ) {
	?>
	<div class="lwtv-ty-empty">
		<div class="lwtv-ty-empty-icon">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'construction.svg', icon: 'svg-construction', max_size: '28' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<h2><?php esc_html_e( 'No characters have been recorded for this year.', 'lwtv' ); ?></h2>
		<p><?php esc_html_e( 'Come back soon, our staff is hard at work researching.', 'lwtv' ); ?></p>
	</div>
	<?php
	return;
}

// Sort By Show cards by cast size, largest first.
$lwtv_ty_coa_by_show = $characters_on_air_by_show;
usort(
	$lwtv_ty_coa_by_show,
	static function ( $lwtv_ty_a, $lwtv_ty_b ) {
		return count( $lwtv_ty_b['characters'] ) - count( $lwtv_ty_a['characters'] );
	}
);

// Starting-letter popularity (A–Z) for the callouts. Names that don't begin
// with a Latin letter are skipped so the ranking stays letter-based.
$lwtv_coa_letters = array();
foreach ( $characters_on_air as $lwtv_coa_char ) {
	$lwtv_coa_first = mb_strtoupper( mb_substr( (string) $lwtv_coa_char['name'], 0, 1 ) );
	if ( 1 !== preg_match( '/^[A-Z]$/', $lwtv_coa_first ) ) {
		continue;
	}
	$lwtv_coa_letters[ $lwtv_coa_first ] = ( $lwtv_coa_letters[ $lwtv_coa_first ] ?? 0 ) + 1;
}
ksort( $lwtv_coa_letters );
$lwtv_coa_has_letters = ! empty( $lwtv_coa_letters );
$lwtv_coa_max         = $lwtv_coa_has_letters ? max( $lwtv_coa_letters ) : 0;
$lwtv_coa_min         = $lwtv_coa_has_letters ? min( $lwtv_coa_letters ) : 0;
$lwtv_coa_top         = $lwtv_coa_has_letters ? array_keys( $lwtv_coa_letters, $lwtv_coa_max, true ) : array();
$lwtv_coa_bottom      = $lwtv_coa_has_letters ? array_keys( $lwtv_coa_letters, $lwtv_coa_min, true ) : array();

// Letters no character's name begins with this year.
$lwtv_coa_unused      = array_values( array_diff( range( 'A', 'Z' ), array_keys( $lwtv_coa_letters ) ) );
$lwtv_coa_unused_n    = count( $lwtv_coa_unused );
$lwtv_coa_unused_list = '';
if ( 1 === $lwtv_coa_unused_n ) {
	$lwtv_coa_unused_list = $lwtv_coa_unused[0];
} elseif ( 2 === $lwtv_coa_unused_n ) {
	/* translators: 1 & 2: single letters joined as an either/or pair, e.g. "X or Z". */
	$lwtv_coa_unused_list = sprintf( __( '%1$s or %2$s', 'lwtv' ), $lwtv_coa_unused[0], $lwtv_coa_unused[1] );
} elseif ( $lwtv_coa_unused_n > 2 ) {
	$lwtv_coa_unused_last = end( $lwtv_coa_unused );
	$lwtv_coa_unused_head = implode( ', ', array_slice( $lwtv_coa_unused, 0, -1 ) );
	/* translators: 1: comma-separated letters, 2: the final letter, forming an or-list e.g. "Q, X, or Z". */
	$lwtv_coa_unused_list = sprintf( __( '%1$s, or %2$s', 'lwtv' ), $lwtv_coa_unused_head, $lwtv_coa_unused_last );
}
?>

<div class="lwtv-ty-section-head">
	<h2>
		<span class="lwtv-ty-coa-count" data-count-to="<?php echo (int) $lwtv_coa_count; ?>"><?php echo esc_html( number_format_i18n( $lwtv_coa_count ) ); ?></span>
		<?php
		printf(
			/* translators: %s: the year being reviewed. */
			esc_html( _n( 'character on air in %s', 'characters on air in %s', $lwtv_coa_count, 'lwtv' ) ),
			esc_html( (string) $this_year )
		);
		?>
	</h2>

	<ul class="nav nav-pills lwtv-ty-pills" id="lwtv-ty-coa-tabs" role="tablist">
		<li class="nav-item">
			<a class="nav-link active" id="lwtv-ty-coa-byname-tab" data-bs-toggle="pill" href="#lwtv-ty-coa-byname" role="tab" aria-controls="lwtv-ty-coa-byname" aria-selected="true"><?php esc_html_e( 'By Name', 'lwtv' ); ?></a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="lwtv-ty-coa-byshow-tab" data-bs-toggle="pill" href="#lwtv-ty-coa-byshow" role="tab" aria-controls="lwtv-ty-coa-byshow" aria-selected="false"><?php esc_html_e( 'By Show', 'lwtv' ); ?></a>
		</li>
	</ul>
</div>

<p class="lwtv-ty-section-subtitle"><?php esc_html_e( 'Every queer character with a role in a show airing this year.', 'lwtv' ); ?></p>

<?php if ( $lwtv_coa_has_letters ) : ?>
<div class="lwtv-trend-callouts">
	<div class="lwtv-trend-callout">
		<div class="lwtv-trend-callout-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Most popular starting letter', 'lwtv' ); ?></span>
			<p class="lwtv-trend-callout-text">
				<?php
				if ( 1 === count( $lwtv_coa_top ) ) {
					printf(
						/* translators: 1: starting letter, 2: number of characters whose names begin with it. */
						esc_html( _n( '%2$s character\'s name begins with %1$s.', '%2$s characters\' names begin with %1$s.', $lwtv_coa_max, 'lwtv' ) ),
						esc_html( $lwtv_coa_top[0] ),
						esc_html( number_format_i18n( $lwtv_coa_max ) )
					);
				} else {
					printf(
						/* translators: 1: number of letters tied, 2: number of characters for each. */
						esc_html__( '%1$s letters tie for the most, with %2$s names each.', 'lwtv' ),
						esc_html( number_format_i18n( count( $lwtv_coa_top ) ) ),
						esc_html( number_format_i18n( $lwtv_coa_max ) )
					);
				}
				?>
			</p>
		</div>
		<span class="lwtv-trend-callout-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'thumbs-up.svg', icon: 'svg-thumbs-up', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</div>
	<?php if ( count( $lwtv_coa_letters ) > 1 ) : // Only meaningful when the names span more than one starting letter. ?>
	<div class="lwtv-trend-callout">
		<div class="lwtv-trend-callout-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Least popular starting letter', 'lwtv' ); ?></span>
			<p class="lwtv-trend-callout-text">
				<?php
				if ( 1 === count( $lwtv_coa_bottom ) ) {
					printf(
						/* translators: 1: starting letter, 2: number of characters whose names begin with it. */
						esc_html( _n( 'Just %2$s character\'s name begins with %1$s, making it the rarest.', 'Just %2$s characters\' names begin with %1$s.', $lwtv_coa_min, 'lwtv' ) ),
						esc_html( $lwtv_coa_bottom[0] ),
						esc_html( number_format_i18n( $lwtv_coa_min ) )
					);
				} else {
					printf(
						/* translators: 1: number of letters tied, 2: number of characters for each. */
						esc_html__( '%1$s letters tie for the fewest, with just %2$s each.', 'lwtv' ),
						esc_html( number_format_i18n( count( $lwtv_coa_bottom ) ) ),
						esc_html( number_format_i18n( $lwtv_coa_min ) )
					);
				}
				?>
			</p>
		</div>
		<span class="lwtv-trend-callout-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'thumbs-down.svg', icon: 'svg-thumbs-down', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</div>
	<?php endif; ?>
	<div class="lwtv-trend-callout">
		<div class="lwtv-trend-callout-body">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Unused letters', 'lwtv' ); ?></span>
			<p class="lwtv-trend-callout-text">
				<?php
				if ( 0 === $lwtv_coa_unused_n ) {
					esc_html_e( 'Every letter of the alphabet shows up this year.', 'lwtv' );
				} elseif ( $lwtv_coa_unused_n > 8 ) {
					printf(
						/* translators: %s: number of alphabet letters that no character name begins with. */
						esc_html( _n( '%s letter goes unused this year.', '%s letters go unused this year.', $lwtv_coa_unused_n, 'lwtv' ) ),
						esc_html( number_format_i18n( $lwtv_coa_unused_n ) )
					);
				} else {
					printf(
						/* translators: %s: a list of letters, e.g. "Q, X, or Z". */
						esc_html__( 'No character\'s name starts with %s.', 'lwtv' ),
						esc_html( $lwtv_coa_unused_list )
					);
				}
				?>
			</p>
		</div>
		<span class="lwtv-trend-callout-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'ghost.svg', icon: 'svg-ghost', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</div>
</div>
<?php endif; ?>

<div class="tab-content" id="lwtv-ty-coa-tabContent">

	<div class="tab-pane fade show active" id="lwtv-ty-coa-byname" role="tabpanel" aria-labelledby="lwtv-ty-coa-byname-tab">
		<div class="lwtv-ty-charname">
			<?php foreach ( $characters_on_air as $lwtv_ty_char ) : ?>
				<div class="lwtv-ty-charname-row">
					<a href="<?php echo esc_url( home_url( '/character/' . $lwtv_ty_char['slug'] . '/' ) ); ?>" class="lwtv-ty-charname-link">
						<?php
						echo esc_html( $lwtv_ty_char['name'] );
						if ( $lwtv_ty_char['dead'] ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo ' ' . lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '15' );
						}
						?>
					</a>
					<span class="lwtv-ty-charname-shows">
						<?php foreach ( $lwtv_ty_char['shows'] as $lwtv_ty_char_show ) : ?>
							<a href="<?php echo esc_url( $lwtv_ty_char_show['url'] ); ?>"><?php echo esc_html( $lwtv_ty_char_show['name'] ); ?></a>
						<?php endforeach; ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="tab-pane fade" id="lwtv-ty-coa-byshow" role="tabpanel" aria-labelledby="lwtv-ty-coa-byshow-tab">
		<div class="lwtv-ty-charshow">
			<?php
			foreach ( $lwtv_ty_coa_by_show as $lwtv_ty_show ) :
				$lwtv_ty_show_url   = home_url( '/show/' . $lwtv_ty_show['slug'] . '/' );
				$lwtv_ty_show_count = count( $lwtv_ty_show['characters'] );
				$lwtv_ty_show_meta  = array_filter(
					array(
						$lwtv_ty_show['nations'][0]['name'] ?? '',
						$lwtv_ty_show['formats'][0]['name'] ?? '',
					)
				);
				?>
				<div class="lwtv-ty-charshow-card">
					<div class="lwtv-ty-charshow-head">
						<a href="<?php echo esc_url( $lwtv_ty_show_url ); ?>" class="lwtv-ty-charshow-link"><?php echo esc_html( $lwtv_ty_show['name'] ); ?></a>
						<span class="badge lwtv-ty-charshow-count"><?php echo esc_html( number_format_i18n( $lwtv_ty_show_count ) ); ?></span>
					</div>
					<?php if ( ! empty( $lwtv_ty_show_meta ) ) : ?>
						<div class="lwtv-ty-charshow-meta"><?php echo esc_html( implode( ' · ', $lwtv_ty_show_meta ) ); ?></div>
					<?php endif; ?>
					<div class="lwtv-ty-charshow-chips">
						<?php foreach ( $lwtv_ty_show['characters'] as $lwtv_ty_chip ) : ?>
							<a href="<?php echo esc_url( $lwtv_ty_chip['url'] ); ?>" class="lwtv-ty-chip">
								<?php
								echo esc_html( $lwtv_ty_chip['name'] );
								if ( $lwtv_ty_chip['dead'] ) {
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo ' ' . lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '10' );
								}
								?>
								<span class="lwtv-ty-chip-role"><?php echo esc_html( ucfirst( $lwtv_ty_chip['type'] ) ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

</div>
