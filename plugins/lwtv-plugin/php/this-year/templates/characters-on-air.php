<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\This_Year\Build\Characters_On_Air;

/**
 * This Year — Characters On Air: By Name grid + By Show cast cards.
 *
 * @package LezWatch.TV
 *
 * @var int   $this_year
 * @var int   $characters_on_air_count
 * @var array $characters_on_air         Numeric list of { slug, name, dead, death_years, shows:[{name,url,type}] }.
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

// Sort By Show cards alphabetically by title, ignoring a leading article
// ("The", "A", "An") so e.g. "The Simpsons" files under S.
$lwtv_ty_coa_by_show = $characters_on_air_by_show;

/**
 * Normalize a show title into a sort key by dropping a single leading English
 * article so titles alphabetize the way a catalog would ("The Simpsons" → "S").
 *
 * Only the three English articles are handled: the vast majority of titles are
 * English, and non-English titles are impractical to article-strip reliably.
 * Alternate names are stored in a separate field, so this stays deliberately
 * simple. strnatcasecmp() (below) compares case-insensitively, so the article
 * match just needs the /i flag rather than lowercasing the whole title.
 *
 * @param string $lwtv_ty_name The show title.
 * @return string The comparison key.
 */
$lwtv_ty_coa_sort_key = static function ( string $lwtv_ty_name ): string {
	return preg_replace( '/^(?:a|an|the)\s+/i', '', trim( $lwtv_ty_name ) );
};

usort(
	$lwtv_ty_coa_by_show,
	static function ( $lwtv_ty_a, $lwtv_ty_b ) use ( $lwtv_ty_coa_sort_key ) {
		return strnatcasecmp(
			$lwtv_ty_coa_sort_key( (string) $lwtv_ty_a['name'] ),
			$lwtv_ty_coa_sort_key( (string) $lwtv_ty_b['name'] )
		);
	}
);

// Graph model (A–Z + a # bucket) and the bucketed directory. All the letter
// math lives in the transform so the template stays markup; see
// Characters_On_Air::alphabet()/::directory().
$lwtv_coa_graph     = Characters_On_Air::alphabet( $characters_on_air );
$lwtv_coa_directory = Characters_On_Air::directory( $characters_on_air );

// Human list of unused letters for the state line, e.g. "U or X" / "Q, X, or Z".
$lwtv_coa_unused      = $lwtv_coa_graph['unused'];
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
	/* translators: 1: comma-separated letters, 2: the final letter, e.g. "Q, X, or Z". */
	$lwtv_coa_unused_list = sprintf( __( '%1$s, or %2$s', 'lwtv' ), $lwtv_coa_unused_head, $lwtv_coa_unused_last );
}

// Tie captions naming the letters, e.g. "A and M". Reused for the peak / rarest sentences.
$lwtv_coa_join = static function ( array $letters ): string {
	$letters = array_values( $letters );
	if ( count( $letters ) <= 1 ) {
		return implode( '', $letters );
	}
	$last = array_pop( $letters );
	/* translators: 1: comma-separated letters, 2: the final letter, e.g. "A, B and C". */
	return sprintf( __( '%1$s and %2$s', 'lwtv' ), implode( ', ', $letters ), $last );
};
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

<?php
$lwtv_coa_role_labels = array(
	'regular'   => __( 'Regular', 'lwtv' ),
	'recurring' => __( 'Recurring', 'lwtv' ),
	'guest'     => __( 'Guest', 'lwtv' ),
);
?>

<div class="tab-content" id="lwtv-ty-coa-tabContent">

	<div class="tab-pane fade show active" id="lwtv-ty-coa-byname" role="tabpanel" aria-labelledby="lwtv-ty-coa-byname-tab">
		<?php if ( $lwtv_coa_count > 0 ) : ?>
		<div class="lwtv-ty-coa-graph" id="top">
			<div class="lwtv-ty-coa-graph-head">
				<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Jump to a letter', 'lwtv' ); ?></span>
				<span class="lwtv-ty-coa-graph-hint">
					<?php
					printf(
						/* translators: %s: the total character count. */
						esc_html__( "Bar height is that letter's share of the %s", 'lwtv' ),
						esc_html( number_format_i18n( $lwtv_coa_count ) )
					);
					?>
				</span>
			</div>

			<div class="lwtv-ty-coa-bars" role="list">
				<?php foreach ( $lwtv_coa_graph['columns'] as $lwtv_coa_col ) : ?>
					<?php
					$lwtv_coa_letter  = $lwtv_coa_col['letter'];
					$lwtv_coa_anchor  = ( '#' === $lwtv_coa_letter ) ? 'coa-letter-hash' : 'coa-letter-' . $lwtv_coa_letter;
					$lwtv_coa_height  = ( $lwtv_coa_graph['max'] > 0 && $lwtv_coa_col['count'] > 0 )
						? max( 3, (int) round( $lwtv_coa_col['count'] / $lwtv_coa_graph['max'] * 54 ) )
						: 3;
					$lwtv_coa_classes = 'lwtv-ty-coa-bar';
					if ( $lwtv_coa_col['empty'] ) {
						$lwtv_coa_classes .= ' is-empty';
					} elseif ( $lwtv_coa_col['peak'] ) {
						$lwtv_coa_classes .= ' is-peak';
					}
					?>
					<?php if ( $lwtv_coa_col['empty'] ) : ?>
						<span class="<?php echo esc_attr( $lwtv_coa_classes ); ?>" role="listitem" aria-disabled="true">
							<span class="lwtv-ty-coa-bar-count">&mdash;</span>
							<span class="lwtv-ty-coa-bar-fill" style="height:3px"></span>
							<span class="lwtv-ty-coa-bar-letter"><?php echo esc_html( $lwtv_coa_letter ); ?></span>
						</span>
					<?php else : ?>
						<a class="<?php echo esc_attr( $lwtv_coa_classes ); ?>" role="listitem"
							href="#<?php echo esc_attr( $lwtv_coa_anchor ); ?>"
							aria-label="
							<?php
							printf(
								/* translators: 1: a letter (or #), 2: number of characters under it. */
								esc_attr__( 'Jump to %1$s, %2$s characters', 'lwtv' ),
								esc_attr( $lwtv_coa_letter ),
								esc_attr( number_format_i18n( $lwtv_coa_col['count'] ) )
							);
							?>
							">
							<span class="lwtv-ty-coa-bar-count"><?php echo esc_html( number_format_i18n( $lwtv_coa_col['count'] ) ); ?></span>
							<span class="lwtv-ty-coa-bar-fill" style="height:<?php echo (int) $lwtv_coa_height; ?>px"></span>
							<span class="lwtv-ty-coa-bar-letter"><?php echo esc_html( $lwtv_coa_letter ); ?></span>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

			<div class="lwtv-ty-coa-graph-foot">
				<span class="lwtv-ty-coa-graph-ties">
					<?php if ( $lwtv_coa_graph['max'] > 0 ) : ?>
						<span class="lwtv-ty-coa-tie">
							<?php
							printf(
								/* translators: 1: letter(s), 2: the shared count. */
								esc_html( _n( '%1$s has the most, %2$s', '%1$s tie for the most, %2$s each', count( $lwtv_coa_graph['top'] ), 'lwtv' ) ),
								'<strong>' . esc_html( $lwtv_coa_join( $lwtv_coa_graph['top'] ) ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								esc_html( number_format_i18n( $lwtv_coa_graph['max'] ) )
							);
							?>
						</span>
					<?php endif; ?>
				</span>
				<span class="lwtv-ty-coa-graph-state">
					<?php
					if ( 0 === $lwtv_coa_unused_n ) {
						printf(
							/* translators: %s: number of letters in use. */
							esc_html__( '%s letters in use · every letter appears this year', 'lwtv' ),
							esc_html( number_format_i18n( $lwtv_coa_graph['in_use'] ) )
						);
					} else {
						printf(
							/* translators: 1: number of letters in use, 2: list of unused letters. */
							esc_html__( '%1$s letters in use · %2$s empty this year', 'lwtv' ),
							esc_html( number_format_i18n( $lwtv_coa_graph['in_use'] ) ),
							esc_html( $lwtv_coa_unused_list )
						);
					}
					?>
				</span>
			</div>
		</div>
		<?php endif; ?>

		<div class="lwtv-ty-coa-directory">
			<div class="lwtv-ty-coa-dir-head" aria-hidden="true">
				<span><?php esc_html_e( 'Character', 'lwtv' ); ?></span>
				<span class="lwtv-ty-coa-dir-head-show"><?php esc_html_e( 'Show', 'lwtv' ); ?></span>
				<span><?php esc_html_e( 'Role', 'lwtv' ); ?></span>
			</div>

			<?php foreach ( $lwtv_coa_directory as $lwtv_coa_group ) : ?>
				<?php
				$lwtv_coa_gletter = $lwtv_coa_group['letter'];
				$lwtv_coa_ganchor = ( '#' === $lwtv_coa_gletter ) ? 'coa-letter-hash' : 'coa-letter-' . $lwtv_coa_gletter;
				?>
				<div class="lwtv-ty-coa-subhead" id="<?php echo esc_attr( $lwtv_coa_ganchor ); ?>">
					<span class="badge lwtv-ty-coa-subhead-letter"><?php echo esc_html( $lwtv_coa_gletter ); ?></span>
					<span class="badge lwtv-ty-coa-subhead-count"><?php echo esc_html( number_format_i18n( $lwtv_coa_group['count'] ) ); ?></span>
					<a class="badge lwtv-ty-coa-subhead-top" href="#top" aria-label="<?php esc_attr_e( 'Back to the letter graph', 'lwtv' ); ?>">&uarr;</a>
				</div>

				<?php foreach ( $lwtv_coa_group['rows'] as $lwtv_coa_row ) : ?>
					<?php
					// Tooltip listing every role, e.g. "Regular on Station 19, guest on Grey's Anatomy".
					$lwtv_coa_title_parts = array();
					foreach ( $lwtv_coa_row['roles'] as $lwtv_coa_i => $lwtv_coa_r ) {
						$lwtv_coa_label         = $lwtv_coa_role_labels[ $lwtv_coa_r['type'] ] ?? ucfirst( $lwtv_coa_r['type'] );
						$lwtv_coa_label         = ( 0 === $lwtv_coa_i ) ? $lwtv_coa_label : mb_strtolower( $lwtv_coa_label );
						$lwtv_coa_title_parts[] = ( '' !== $lwtv_coa_r['show'] )
							/* translators: 1: role label, 2: show name. */
							? sprintf( __( '%1$s on %2$s', 'lwtv' ), $lwtv_coa_label, $lwtv_coa_r['show'] )
							: $lwtv_coa_label;
					}
					$lwtv_coa_title = implode( ', ', $lwtv_coa_title_parts );
					?>
					<div class="lwtv-ty-coa-dir-row" data-letter="<?php echo esc_attr( $lwtv_coa_gletter ); ?>" data-role="<?php echo esc_attr( $lwtv_coa_row['role'] ); ?>">
						<span class="lwtv-ty-coa-dir-char">
							<a href="<?php echo esc_url( home_url( '/character/' . $lwtv_coa_row['slug'] . '/' ) ); ?>"><?php echo esc_html( $lwtv_coa_row['name'] ); ?></a>
							<?php if ( $lwtv_coa_row['dead'] ) : ?>
								<?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '15' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<span class="screen-reader-text"><?php esc_html_e( 'Died this year', 'lwtv' ); ?></span>
							<?php endif; ?>
						</span>
						<span class="lwtv-ty-coa-dir-show">
							<?php
							$lwtv_coa_show_links = array();
							foreach ( $lwtv_coa_row['shows'] as $lwtv_coa_show ) {
								$lwtv_coa_show_links[] = '<a href="' . esc_url( $lwtv_coa_show['url'] ) . '">' . esc_html( $lwtv_coa_show['name'] ) . '</a>';
							}
							echo implode( ' · ', $lwtv_coa_show_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</span>
						<span class="lwtv-ty-coa-dir-role"<?php echo ( '' !== $lwtv_coa_title ) ? ' title="' . esc_attr( $lwtv_coa_title ) . '"' : ''; ?>>
							<?php if ( '' !== $lwtv_coa_row['role'] ) : ?>
								<span class="lwtv-ty-coa-role-dot role-<?php echo esc_attr( $lwtv_coa_row['role'] ); ?>"></span>
								<?php echo esc_html( $lwtv_coa_role_labels[ $lwtv_coa_row['role'] ] ?? '' ); ?>
							<?php endif; ?>
						</span>
					</div>
				<?php endforeach; ?>
			<?php endforeach; ?>

			<div class="lwtv-ty-coa-dir-foot">
				<?php
				printf(
					/* translators: %s: total number of characters. */
					esc_html__( '%s characters, A to Z.', 'lwtv' ),
					esc_html( number_format_i18n( $lwtv_coa_count ) )
				);
				?>
			</div>
		</div>
	</div>

	<div class="tab-pane fade" id="lwtv-ty-coa-byshow" role="tabpanel" aria-labelledby="lwtv-ty-coa-byshow-tab">
		<div class="lwtv-ty-coa-sortnote">
			<span class="lwtv-ty-coa-sortpill"><?php esc_html_e( 'Shows A–Z, articles ignored', 'lwtv' ); ?></span>
			<span class="lwtv-ty-coa-sortnote-text"><?php esc_html_e( '“The Beast in Me” files under B; numeric titles like 9-1-1 lead.', 'lwtv' ); ?></span>
		</div>
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
					<div class="lwtv-ty-charshow-cast">
						<?php foreach ( Characters_On_Air::cast_for_show( $lwtv_ty_show['characters'] ) as $lwtv_ty_castmate ) : ?>
							<div class="lwtv-ty-charshow-castrow">
								<a href="<?php echo esc_url( $lwtv_ty_castmate['url'] ); ?>" class="lwtv-ty-charshow-castname">
									<?php echo esc_html( $lwtv_ty_castmate['name'] ); ?>
									<?php if ( ! empty( $lwtv_ty_castmate['dead'] ) ) : ?>
										<?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '12' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<span class="screen-reader-text"><?php esc_html_e( 'Died this year', 'lwtv' ); ?></span>
									<?php endif; ?>
								</a>
								<span class="lwtv-ty-charshow-castrole">
									<span class="lwtv-ty-coa-role-dot role-<?php echo esc_attr( $lwtv_ty_castmate['type'] ); ?>"></span>
									<?php echo esc_html( $lwtv_coa_role_labels[ $lwtv_ty_castmate['type'] ] ?? ucfirst( $lwtv_ty_castmate['type'] ) ); ?>
								</span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

</div>
