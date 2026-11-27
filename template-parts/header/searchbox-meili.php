<?php
/**
 * Searchbox: Meilisearch
 *
 * The trigger for the Meilisearch search modal (issue #749). Included with
 * array( 'part' => 'dialog' ), it renders the modal itself instead: the
 * <dialog>, the row icon templates, the browser config, and the hidden
 * SearchWP trigger the modal falls back to. That part renders once per page.
 *
 * Only used when lwtv_plugin()->meili_modal_enabled() is true.
 *
 * @package LWTV Underscores
 */

if ( 'dialog' !== ( $args['part'] ?? 'trigger' ) ) {
	?>
	<button type="button" class="nav-link lwtv-meili-trigger" data-lwtv-meili-open aria-haspopup="dialog">
		<?php echo lwtv_plugin()->get_symbolicon( svg: 'search.svg', icon: 'svg-search', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<span class="screen-reader-text"><?php esc_html_e( 'Search the Site', 'lwtv-underscores' ); ?></span>
	</button>
	<?php
	return;
}

if ( ! empty( $GLOBALS['lwtv_meili_dialog_rendered'] ) ) {
	return;
}

$lwtv_meili_json   = lwtv_plugin()->meili_modal_config_json();
$lwtv_meili_config = '' === $lwtv_meili_json ? null : json_decode( $lwtv_meili_json, true );
if ( ! is_array( $lwtv_meili_config ) || empty( $lwtv_meili_config['tabs'] ) ) {
	return;
}

$GLOBALS['lwtv_meili_dialog_rendered'] = true;

$lwtv_meili_strings = $lwtv_meili_config['strings'];

// Row icons, as in searchwp-live-ajax-search/live-search-results.php.
$lwtv_meili_icons = array(
	'shows'      => array( 'tv.svg', 'svg-tv', __( 'TV Show, mini-series, or movie', 'lwtv-underscores' ) ),
	'characters' => array( 'rubber-stamp.svg', 'svg-user', __( 'Character', 'lwtv-underscores' ) ),
	'actors'     => array( 'award-academy.svg', 'svg-user-tie', __( 'Actor', 'lwtv-underscores' ) ),
	'content'    => array( 'newspaper.svg', 'svg-newspaper', __( 'News Article', 'lwtv-underscores' ) ),
	'page'       => array( 'chalkboard.svg', 'svg-chalkboard', __( 'Page', 'lwtv-underscores' ) ),
);
?>
<dialog id="lwtv-meili-dialog" class="lwtv-meili-dialog" aria-label="<?php echo esc_attr( $lwtv_meili_strings['dialogLabel'] ); ?>" aria-modal="true">
	<div class="lwtv-meili-inner">
		<form role="search" class="lwtv-meili-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
			<label class="lwtv-meili-label" for="lwtv-meili-input"><?php echo esc_html( $lwtv_meili_strings['inputLabel'] ); ?></label>
			<div class="lwtv-meili-field">
				<input type="search" id="lwtv-meili-input" name="s" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr_x( 'Enter keywords &hellip;', 'placeholder', 'lwtv-underscores' ); ?>">
				<button type="button" class="btn-close" data-lwtv-meili-close aria-label="<?php echo esc_attr( $lwtv_meili_strings['close'] ); ?>"></button>
			</div>
		</form>

		<div role="tablist" id="lwtv-meili-tablist" class="lwtv-meili-tabs" aria-label="<?php esc_attr_e( 'Results by type', 'lwtv-underscores' ); ?>">
			<?php foreach ( $lwtv_meili_config['tabs'] as $lwtv_meili_tab ) : ?>
				<button type="button" role="tab" id="<?php echo esc_attr( 'lwtv-meili-tab-' . $lwtv_meili_tab['type'] ); ?>" aria-controls="<?php echo esc_attr( 'lwtv-meili-panel-' . $lwtv_meili_tab['type'] ); ?>" aria-selected="false" tabindex="-1">
					<span class="lwtv-meili-tab-label"><?php echo esc_html( $lwtv_meili_tab['label'] ); ?></span>
					<span class="lwtv-meili-count">0</span>
				</button>
			<?php endforeach; ?>
		</div>

		<div class="lwtv-meili-panels">
			<?php foreach ( $lwtv_meili_config['tabs'] as $lwtv_meili_tab ) : ?>
				<div role="tabpanel" id="<?php echo esc_attr( 'lwtv-meili-panel-' . $lwtv_meili_tab['type'] ); ?>" aria-labelledby="<?php echo esc_attr( 'lwtv-meili-tab-' . $lwtv_meili_tab['type'] ); ?>" tabindex="0" hidden></div>
			<?php endforeach; ?>
		</div>

		<div id="lwtv-meili-status" class="screen-reader-text" role="status" aria-live="polite"></div>
	</div>
</dialog>

<?php // Outside the dialog: it must still be heard after the dialog closes for the fallback. ?>
<div id="lwtv-meili-announce" class="screen-reader-text" role="status" aria-live="polite"></div>

<?php foreach ( $lwtv_meili_icons as $lwtv_meili_type => $lwtv_meili_icon ) : ?>
<template id="<?php echo esc_attr( 'lwtv-meili-icon-' . $lwtv_meili_type ); ?>">
	<?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_meili_icon[0], icon: $lwtv_meili_icon[1], max_size: '20px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<span class="screen-reader-text"><?php echo esc_html( $lwtv_meili_icon[2] ); ?></span>
</template>
<?php endforeach; ?>

<script type="application/json" id="lwtv-meili-config"><?php echo $lwtv_meili_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with JSON_HEX_TAG | JSON_HEX_AMP, so it can't close the script element. ?></script>

<?php
/*
 * Guard, in plain ES5 so any browser runs it: until the module marks itself
 * ready (<html data-lwtv-meili-ready>), the search button opens SearchWP or
 * goes to the results page. Covers a module that never loads or parses, a
 * browser without dialog.showModal(), and a module that fails to start, so
 * the button never does nothing.
 */
wp_print_inline_script_tag(
	'document.addEventListener("click",function(e){'
	. 'if(document.documentElement.getAttribute("data-lwtv-meili-ready")){return;}'
	. 'var t=e.target&&e.target.closest?e.target.closest("[data-lwtv-meili-open]"):null;'
	. 'if(!t){return;}'
	. 'e.preventDefault();'
	. 'var f=document.querySelector("[data-lwtv-meili-fallback] [data-searchwp-modal-trigger]");'
	. 'if(f){f.click();}else{window.location.href=' . wp_json_encode( home_url( '/?s=' ) ) . ';}'
	. '});',
	array( 'id' => 'lwtv-meili-guard' )
);

// The SearchWP modal stays queued (hidden trigger) as the fallback.
if ( function_exists( 'searchwp_modal_form_trigger' ) ) {
	?>
	<span hidden data-lwtv-meili-fallback>
		<?php get_template_part( 'template-parts/header/searchbox-searchwp' ); ?>
	</span>
	<?php
}
