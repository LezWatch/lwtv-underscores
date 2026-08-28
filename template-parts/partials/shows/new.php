<?php
/**
 * The template for a show with no characters listed.
 *
 * Two different situations, and telling them apart matters to readers:
 *
 * - Nobody has got to this show yet, so the data really is on its way.
 * - We have looked and cannot find anything. `lezshows_no_chars` is an editor
 *   saying so deliberately, and it also stops the show being reported as a
 *   problem in the Shows debugger.
 *
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? get_the_ID();

if ( get_post_meta( $show_id, 'lezshows_no_chars', true ) ) {
	$no_known_characters = lwtv_plugin()->get_symbolicon( svg: 'warning.svg', icon: 'svg-warning', max_size: '15' );
	?>

	<section name="nochars" id="nochars" class="showschar-section">
		<h2>No Known Characters</h2>

		<div class="card-body">
			<div class="alert alert-warning" role="alert">
				<?php echo $no_known_characters; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				We know there are queer characters, but cannot find information on them! If you know any details, please
				<a href="#" data-bs-toggle="modal" data-bs-target="#suggestForm">contact us</a>!
			</div>
		</div>
	</section>

	<?php
	return;
}

$under_construction = lwtv_plugin()->get_symbolicon( svg: 'construction.svg', icon: 'svg-construction', max_size: '15' );
?>

<section name="newshow" id="newshow" class="showschar-section">
	<h2>Under Construction</h2>

	<div class="card-body">
		<div class="alert alert-info" role="alert">
			<?php echo $under_construction; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			This show's data is still being calculated. It will be available as soon as possible.
		</div>
	</div>
</section>
