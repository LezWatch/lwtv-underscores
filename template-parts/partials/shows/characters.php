<?php
/**
 * The template for displaying the characters for a show
 *
 * @package LezWatch.TV
 */

$show_id       = $args['show_id'];
$havecharcount = $args['havecharcount'];
$havedeadcount = get_post_meta( $show_id, 'lezshows_dead_count', true );
?>
<section name="characters" id="characters" class="showschar-section">
	<h2>Characters</h2>
	<div class="card-body">
		<?php

		// Get the list of characters.
		$chars_by_role = lwtv_plugin()->get_chars_for_show( $show_id, 'all' );

		if ( empty( $havecharcount ) || '0' === $havecharcount ) {
			echo '<p>There are no characters listed yet for this show.</p>';
		} else {
			$deadtext = 'none are dead';
			if ( $havedeadcount > '0' ) {
				// translators: %s is the number of dead characters.
				$deadtext = sprintf( _n( '<strong>%s</strong> is dead', '<strong>%s</strong> are dead', $havedeadcount ), $havedeadcount );
			}

			// translators: %s is the number of characters total.
			echo wp_kses_post( '<p>There ' . sprintf( _n( 'is <strong>%s</strong> queer character', 'are <strong>%s</strong> queer characters', $havecharcount ), $havecharcount ) . ' listed for this show; ' . $deadtext . '.</p>' );

			// If there are regulars...
			if ( isset( $chars_by_role['regular'] ) && is_array( $chars_by_role['regular'] ) ) {
				?>
				<h3 class="title-regulars"><?php echo esc_html( _n( 'Regular', 'Regulars', count( $chars_by_role['regular'] ) ) ); ?> (<?php echo (int) count( $chars_by_role['regular'] ); ?>)</h3>
				<div class="container characters-regulars-container"><div class="row site-loop character-show-loop">
				<?php
				foreach ( $chars_by_role['regular'] as $character ) {
					get_template_part( 'template-parts/excerpt/characters', '', compact( 'character' ) );
				}
				echo '</div></div>';
			}
			// If there are recurring...
			if ( isset( $chars_by_role['recurring'] ) && is_array( $chars_by_role['recurring'] ) ) {
				?>
				<h3 class="title-recurring">Recurring (<?php echo count( $chars_by_role['recurring'] ); ?>)</h3>
				<div class="container characters-recurring-container"><div class="row site-loop character-show-loop">
				<?php
				foreach ( $chars_by_role['recurring'] as $character ) {
					get_template_part( 'template-parts/excerpt/characters', '', compact( 'character' ) );
				}
				echo '</div></div>';
			}
			// If there are guests...
			if ( isset( $chars_by_role['guest'] ) && is_array( $chars_by_role['guest'] ) ) {
				?>
				<h3 class="title-guest"><?php echo esc_html( _n( 'Guest', 'Guests', count( $chars_by_role['guest'] ) ) ); ?> (<?php echo count( $chars_by_role['guest'] ); ?>)</h3>
				<ul class="guest-character-list">
				<?php
				foreach ( $chars_by_role['guest'] as $character ) {
					// Remove any parenthesis from the character display name.
					$guest_char_title = ( str_contains( get_the_title( $character['id'] ), ')' ) ) ? strstr( get_the_title( $character['id'] ), '(', true ) : get_the_title( $character['id'] );
					$grave            = ( has_term( 'dead', 'lez_cliches', $character['id'] ) ) ? '<span role="img" aria-label="RIP Tombstone" title="RIP Tombstone" class="charlist-grave-sm">' . lwtv_plugin()->get_symbolicon( svg: 'rest-in-peace.svg', icon: 'svg-times-circle', max_size: '15' ) . '</span>' : '';
					?>
					<li><a href="<?php the_permalink( $character['id'] ); ?>" title="<?php echo esc_html( $guest_char_title ); ?>" ><?php echo esc_html( $guest_char_title ) . ' ' . $grave; // phpcs:ignore WordPress.Security.EscapeOutput ?></a></li>
					<?php
				}
				echo '</ul>';
			}
		}
		?>
	</div>
</section>
