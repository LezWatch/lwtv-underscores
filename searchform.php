<?php
/**
 * The template for displaying search forms in LWTV Underscores
 *
 * @package LWTV Underscores
 */

// Build scope options — only when not inside the modal (which has its own selector).
$lwtv_sidebar_engines = array();
$lwtv_show_sidebar_scope = empty( $GLOBALS['lwtv_in_modal'] ) && class_exists( 'SearchWP' );

if ( $lwtv_show_sidebar_scope ) {
	$candidates = array(
		'shows'      => __( 'TV Shows', 'lwtv' ),
		'characters' => __( 'Characters', 'lwtv' ),
		'actors'     => __( 'Actors', 'lwtv' ),
	);

	foreach ( $candidates as $engine_slug => $engine_label ) {
		$exists = ( 'default' === $engine_slug )
			|| (bool) \SearchWP\Settings::get_engine_settings( $engine_slug );

		if ( $exists ) {
			$lwtv_sidebar_engines[ $engine_slug ] = $engine_label;
		}
	}

	$lwtv_show_sidebar_scope = count( $lwtv_sidebar_engines ) > 1;
}
?>

<div class="card card-search">
	<div class="card-header">
		<h4><?php echo lwtv_plugin()->get_symbolicon( svg: 'search.svg', icon: 'svg-search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Search the Database</h4>
	</div>
	<div class="card-body">

		<?php if ( $lwtv_show_sidebar_scope ) : ?>
			<center>
				<div class="lwtv-search-scope" role="group" aria-label="<?php esc_attr_e( 'Search scope', 'lwtv' ); ?>">
					<?php foreach ( $lwtv_sidebar_engines as $engine_slug => $engine_label ) : ?>
					<label class="lwtv-search-scope__option">
						<input
							type="radio"
							name="lwtv_sidebar_scope"
							value="<?php echo esc_attr( $engine_slug ); ?>"
							data-swpengine="<?php echo esc_attr( $engine_slug ); ?>"
							<?php checked( $engine_slug, 'default' ); ?>
						>
						<span><?php echo esc_html( $engine_label ); ?></span>
					</label>
					<?php endforeach; ?>
				</div>
			</center>
		<?php endif; ?>

		<form role="search" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
			<?php if ( $lwtv_show_sidebar_scope ) : ?>
			<input type="hidden" name="lwtv_scope" value="default">
			<?php endif; ?>
			<div class="input-group input-group-sm">
				<input type="text" name="s" id="sidebar-search" class="form-control" aria-label="Search for..." value="<?php the_search_query(); ?>" title="<?php echo esc_html_x( 'Search for:', 'label', 'lwtv-underscores' ); ?>" >
				<span class="input-group-btn">
					<button class="btn btn-primary btn-sm" type="submit">Go</button>
				</span>
			</div>
		</form>

	</div><!-- .card-body -->
</div><!-- .card -->

<?php if ( $lwtv_show_sidebar_scope ) : ?>
<script>
( function ( $ ) {
	'use strict';

	var $card = $( '.card-search' );

	$card.on( 'change', '.lwtv-search-scope input[type="radio"]', function () {
		var newEngine = $( this ).data( 'swpengine' );

		// Update the hidden field so the engine travels with form submission.
		$card.find( 'input[name="lwtv_scope"]' ).val( newEngine );

		// Update jQuery's data cache so the live-search AJAX uses the right engine.
		var $liveInput = $card.find( 'input[data-swplive="true"]' );
		$liveInput
			.attr( 'data-swpengine', newEngine )
			.data( 'swpengine', newEngine );

		if ( typeof $.fn.searchwp_live_search === 'function' ) {
			$liveInput.searchwp_live_search();
		}

		// Re-fire live search immediately if the field already has text.
		if ( $liveInput.val().length > 0 ) {
			$liveInput.trigger( 'input' );
		}
	} );
} ( jQuery ) );
</script>
<?php endif; ?>
