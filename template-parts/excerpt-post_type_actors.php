<?php
/*
 * This content is called by all archival displays of actors
 *
 * Used by the following files
 *      - archive-post_type_actors.php
 *      - taxonomy.php
 *
 * @package LezWatch.TV
 */

global $post;

$the_id      = ( isset( $actor['id'] ) ) ? $actor['id'] : $post->ID;
$the_content = ( isset( $actor['content'] ) ) ? $actor['content'] : get_the_content();
$alt_text    = 'A picture of the actor ' . get_the_title( $the_id );
$archive     = ( is_archive() || is_tax() || is_page() ) ? true : false;

// The Terrible Terri Polo: If we don't reset, her stats apply to everyone.
unset( $shows, $actors, $gender, $sexuality, $cliches );
?>

<div class="card">
	<div class="actor-image-wrapper">
		<?php
		get_template_part(
			'template-parts/partials/output',
			'image',
			array(
				'to_show' => $the_id,
				'format'  => 'excerpt',
			)
		);
		?>
	</div>
	<div class="card-body">
		<h4 class="card-title">
			<a href="<?php the_permalink( $the_id ); ?>" title="<?php the_title_attribute( $the_id ); ?>" >
				<?php
				echo esc_html( get_the_title( $the_id ) );
				if ( $archive ) {
					$died = get_post_meta( $the_id, 'lezactors_death', true );
					if ( ! empty( $died ) ) {
						echo '<span role="img" aria-label="Grim Reaper" title="Grim Reaper" class="charlist-grave">' . lwtv_symbolicons( 'grim-reaper.svg', 'fa-times-circle' ) . '</span>';
					}
				}
				?>
			</a>
		</h4>
		<div class="card-text">
			<?php
			$gender    = lwtv_plugin()->get_actor_gender( $the_id );
			$sexuality = lwtv_plugin()->get_actor_sexuality( $the_id );

			// Gender and Sexuality
			if ( isset( $gender ) || isset( $sexuality ) ) {
				echo '<div class="card-meta-item gender sexuality"><p>';
				if ( isset( $gender ) ) {
					echo '&bull; <strong>Gender:</strong> ' . wp_kses_post( $gender ) . '<br />';
				}
				if ( isset( $sexuality ) ) {
					echo '&bull; <strong>Sexuality:</strong> ' . wp_kses_post( $sexuality );
				}
				echo '</p></div>';
			}
			?>
		</div>
	</div>
	<?php
	if ( $archive ) {
		?>
		<div class="card-footer">
			<a href="<?php the_permalink( $the_id ); ?>" title="<?php the_title_attribute( $the_id ); ?>" class="btn btn-outline-primary">Actor Profile</a>
		</div>
		<?php
	}
	?>
</div><!-- .card -->
