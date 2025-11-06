<?php
/**
 * The template part for displaying a CTA button to a form modal to suggest edits.
 *
 * @package YIKES Starter
 */

$for_post      = $args['for_post'] ?? null;
$for_post_type = str_replace( 'post_type_', '', get_post_type( $for_post ) );

if ( is_null( $for_post ) || empty( $for_post ) ) {
	return;
}
?>

<!-- Button trigger modal -->
<div class="d-grid gap-2">
	<button type="button" class="btn btn-primary btn-lg btn-block" data-bs-toggle="modal" data-bs-target="#suggestForm">
		Suggest an Edit for <?php echo esc_html( ucfirst( $for_post_type ) ); ?> <?php echo esc_html( get_the_title( $for_post ) ); ?>
	</button>
</div>

<!-- Modal -->
<div class="modal fade" id="suggestForm" tabindex="-1" aria-labelledby="suggestFormLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title" id="suggestFormLabel">Suggest an Edit for <?php echo esc_html( get_the_title( $for_post ) ); ?></h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p>
					We welcome all corrections and additions to our database.
					<?php
					if ( 'actors' === $for_post_type ) {
						echo 'Any incorrect attributions of gender or sexual orientation are unintentional and will be corrected as soon as possible. Make sure any characters you suggest are a <a href="https://lezwatchtv.com/about/faq/#aioseo-what-6">qualifying queer character</a>.';
					} elseif ( 'shows' === $for_post_type ) {
						echo 'Before you suggest a new character, please make sure they are a <a href="https://lezwatchtv.com/about/faq/#aioseo-what-6">qualifying queer character</a>.';
					}
					?>
				</p>
				<p>
					<?php echo do_shortcode( '[gravityform id="1" title="false" description="false" ajax="true"]' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
