<?php
/**
 * Searchbox: SearchWP
 *
 * Implements the SearchWP modal form trigger when available.
 */

$args = array(
	'template' => 'LWTV',
	'engine'   => 'default',
	'icon'     => lwtv_plugin()->get_symbolicon( svg: 'search.svg', icon: 'svg-search', max_size: '24' ),
);

if ( class_exists( 'SearchWP' ) ) {
	$engine_settings = \SearchWP\Settings::get_engine_settings( $args['engine'] );
	$engine          = $engine_settings ? $args['engine'] : 'default';
} else {
	$engine = '{wp_native}';
}

$template   = searchwp_modal_form_get_template_from_label( $args['template'] );
$modal_hash = searchwp_modal_form_get_template_hash( $engine, $template['file'] );

add_filter(
	'searchwp_modal_form_queue',
	function ( $forms ) use ( $modal_hash ) {
		$forms[] = $modal_hash;

		return $forms;
	}
);

?>
<a class="nav-link searchwp-modal-form-trigger-el data-searchwp-modal-trigger'" href="<?php echo esc_attr( '#searchwp-modal-' . $modal_hash ); ?>" data-searchwp-modal-trigger="<?php echo esc_attr( 'searchwp-modal-' . $modal_hash ); ?>" role="button">
	<?php echo $args['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<span class="screen-reader-text">Search the Site</span>
</a>
