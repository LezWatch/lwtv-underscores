<?php
/**
 * The template part for displaying a CTA button to a form modal to suggest edits.
 *
 * @package YIKES Starter
 */

$for_post = $args['for_post'] ?? null;

if ( is_null( $for_post ) || empty( $for_post ) ) {
	return;
}
?>

<!-- Button trigger modal -->
<div class="d-grid gap-2">
<button type="button" class="btn btn-primary btn-lg btn-block" data-bs-toggle="modal" data-bs-target="#suggestForm">
	Suggest an Edit
</button>
</div>

<!-- Modal -->
<div class="modal fade" id="suggestForm" tabindex="-1" aria-labelledby="suggestFormLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title" id="suggestFormLabel">Suggest an Edit for <?php echo esc_html( get_the_title( $for_post ) ); ?></h3>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p>
					We welcome corrections and additions to our database. Any misattributions of gender or sexual orientation are unintentional and will be corrected as soon as possible.
				</p>
				<p>
					<?php echo do_shortcode( '[gravityform id="1" title="false" description="false" ajax="true"]' ); ?>
				</p>
			</div>
			<div class="modal-footer">
				<button type="button" data-dismiss="modal" class="btn btn-sm btn-primary">Close</button>
			</div>
		</div>
	</div>
</div>
