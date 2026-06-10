<?php
/**
 * SearchWP Modal Form Name: LWTV
 *
 * More info: https://searchwp.com/documentation/extensions/modal-form/#templates
 */

// Build the list of scope options — only include engines that actually exist.
$lwtv_search_engines = array();

if ( class_exists( 'SearchWP' ) && function_exists( 'searchwp_modal_form_get_template_hash' ) ) {
	$candidates = array(
		'default'    => __( 'Everything', 'lwtv' ),
		'shows'      => __( 'TV Shows', 'lwtv' ),
		'characters' => __( 'Characters', 'lwtv' ),
		'actors'     => __( 'Actors', 'lwtv' ),
	);

	foreach ( $candidates as $engine_slug => $engine_label ) {
		$exists = ( 'default' === $engine_slug )
			|| (bool) \SearchWP\Settings::get_engine_settings( $engine_slug );

		if ( $exists ) {
			$lwtv_search_engines[ $engine_slug ] = array(
				'label' => $engine_label,
				'hash'  => searchwp_modal_form_get_template_hash( $engine_slug, __FILE__ ),
			);
		}
	}
}

$lwtv_show_scope = count( $lwtv_search_engines ) > 1;
?>

<div class="searchwp-modal-form-lwtv">
	<div class="searchwp-modal-form__overlay" tabindex="-1" data-searchwp-modal-form-close>
		<div class="searchwp-modal-form__container" role="dialog" aria-modal="true">
			<main class="searchwp-modal-form__content">

				<?php if ( $lwtv_show_scope ) : ?>
				<div class="lwtv-search-scope" role="group" aria-label="<?php esc_attr_e( 'Search scope', 'lwtv' ); ?>">
					<?php foreach ( $lwtv_search_engines as $engine_slug => $engine_data ) : ?>
					<label class="lwtv-search-scope__option">
						<input
							type="radio"
							name="lwtv_search_scope"
							value="<?php echo esc_attr( $engine_slug ); ?>"
							data-swpengine="<?php echo esc_attr( $engine_slug ); ?>"
							data-swpmfe="<?php echo esc_attr( $engine_data['hash'] ); ?>"
							<?php checked( $engine_slug, 'default' ); ?>
						>
						<span><?php echo esc_html( $engine_data['label'] ); ?></span>
					</label>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php echo get_search_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			</main>
		</div>
	</div>
</div>

<?php if ( $lwtv_show_scope ) : ?>
<script>
( function ( $ ) {
	'use strict';

	$( '.lwtv-search-scope' ).on( 'change', 'input[type="radio"]', function () {
		var $radio    = $( this );
		var $modal    = $radio.closest( '.searchwp-modal-form' );
		var $form     = $modal.find( 'form' );
		var newHash   = $radio.data( 'swpmfe' );
		var newEngine = $radio.data( 'swpengine' );

		// 1. Swap the swpmfe hidden input so SearchWP uses the right engine
		//    for both full-text search and the searchwp\query\args engine swap.
		$form.find( 'input[name="swpmfe"]' ).val( newHash );

		// 2. Update jQuery's internal data cache for the live-search engine.
		//    The live-search plugin reads $input.data('swpengine') on every
		//    AJAX call — NOT the HTML attribute — so we must update the jQuery
		//    cache directly, not just setAttribute().
		var $liveInput = $form.find( 'input[data-swplive="true"]' );
		$liveInput.data( 'swpengine', newEngine );

		// 3. Re-fire live search immediately if the field already has text,
		//    so results update without the user re-typing.
		if ( $liveInput.val().length > 0 ) {
			$liveInput.trigger( 'input' );
		}
	} );
} ( jQuery ) );
</script>
<?php endif; ?>
