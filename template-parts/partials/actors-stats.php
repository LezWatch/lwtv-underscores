<?php
/**
 * Template part for displaying the character or actor image
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$this_id = $args['actor_id'] ?? null;
?>

<section name="stats" id="stats" class="showschar-section">
	<h2>Character Statistics</h2>
	<div class="card-body">
		<div class="card-meta">
			<div class="card-meta-item">
				<?php
				$attributes = array(
					'posttype' => get_post_type( $this_id ),
				);
				$stats      = lwtv_plugin()->generate_stats_block_actor( $attributes );

				if ( ! empty( $stats ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput
					echo $stats;
				} else {
					echo '<p>After this maintenance, statistics will be right back!</p>';
				}
				?>
				<p><em><small>Note: Character roles may exceed the number of characters played, if the character was on multiple TV shows.</small></em></p>
			</div>
		</div>
	</div>
</section>
