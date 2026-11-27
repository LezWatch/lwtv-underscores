<?php
/**
 * The template for displaying search forms in LWTV Underscores
 *
 * @package LWTV Underscores
 */

// Build scope options — only when not inside the modal (which has its own selector),
// and not when the Meilisearch results page is on: it has tabs, and the form just submits `s`.
$lwtv_sidebar_engines    = array();
$lwtv_show_sidebar_scope = empty( $GLOBALS['lwtv_in_modal'] ) && class_exists( 'SearchWP' ) && ! lwtv_plugin()->meili_modal_enabled();

if ( $lwtv_show_sidebar_scope ) {
	$candidates = array(
		'shows'      => __( 'TV Shows', 'lwtv-underscores' ),
		'characters' => __( 'Characters', 'lwtv-underscores' ),
		'actors'     => __( 'Actors', 'lwtv-underscores' ),
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

/*
 * With the Meilisearch results page on, the same pills pick the tab the page
 * opens on: plain radios named `type` inside the form, so no JS is needed.
 * None picked means the page opens on the strongest match.
 */
$lwtv_meili_scope = empty( $GLOBALS['lwtv_in_modal'] ) && lwtv_plugin()->meili_modal_enabled();
$lwtv_meili_types = array(
	'shows'      => __( 'TV Shows', 'lwtv-underscores' ),
	'characters' => __( 'Characters', 'lwtv-underscores' ),
	'actors'     => __( 'Actors', 'lwtv-underscores' ),
);
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: which pill to show as picked.
$lwtv_meili_current = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';

// The as-you-type list under the box (inc/js/meili-search.js) needs ids unique per form.
$GLOBALS['lwtv_meili_inline_n'] = ( $GLOBALS['lwtv_meili_inline_n'] ?? 0 ) + 1;
$lwtv_meili_inline_id           = 'lwtv-meili-inline-' . $GLOBALS['lwtv_meili_inline_n'];
$lwtv_meili_inline_attrs        = $lwtv_meili_scope
	? sprintf( ' autocomplete="off" data-lwtv-meili-inline aria-controls="%1$s" aria-describedby="%1$s-status"', esc_attr( $lwtv_meili_inline_id ) )
	: '';
?>

<div class="card card-search">
	<div class="card-header">
		<h4><?php echo lwtv_plugin()->get_symbolicon( svg: 'search.svg', icon: 'svg-search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Search the Database</h4>
	</div>
	<div class="card-body">

		<?php if ( $lwtv_show_sidebar_scope ) : ?>
			<center>
				<div class="lwtv-search-scope" role="group" aria-label="<?php esc_attr_e( 'Search scope', 'lwtv-underscores' ); ?>">
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
			<?php if ( $lwtv_meili_scope ) : ?>
			<div class="lwtv-search-scope" role="group" aria-label="<?php esc_attr_e( 'Search in', 'lwtv-underscores' ); ?>">
				<?php foreach ( $lwtv_meili_types as $lwtv_meili_type => $lwtv_meili_label ) : ?>
				<label class="lwtv-search-scope__option">
					<input type="radio" name="type" value="<?php echo esc_attr( $lwtv_meili_type ); ?>" <?php checked( $lwtv_meili_type, $lwtv_meili_current ); ?>>
					<span><?php echo esc_html( $lwtv_meili_label ); ?></span>
				</label>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<?php if ( $lwtv_show_sidebar_scope ) : ?>
			<input type="hidden" name="lwtv_scope" value="default">
			<?php endif; ?>
			<div class="input-group input-group-sm">
				<input type="text" name="s" id="sidebar-search" class="form-control" aria-label="Search for..." value="<?php the_search_query(); ?>" title="<?php echo esc_html_x( 'Search for:', 'label', 'lwtv-underscores' ); ?>"<?php echo $lwtv_meili_inline_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above with esc_attr(). ?>>
				<button class="btn btn-solid theme-primary btn-sm" type="submit">Go</button>
			</div>
		</form>
		<?php if ( $lwtv_meili_scope ) : ?>
		<div class="lwtv-meili-inline" id="<?php echo esc_attr( $lwtv_meili_inline_id ); ?>" hidden></div>
		<div class="screen-reader-text" id="<?php echo esc_attr( $lwtv_meili_inline_id . '-status' ); ?>" role="status" aria-live="polite"></div>
		<?php endif; ?>

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
