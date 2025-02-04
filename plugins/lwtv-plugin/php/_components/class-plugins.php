<?php
/*
 * Plugins
 */
namespace LWTV\_Components;

use LWTV\Plugins\CMB2;
use LWTV\Plugins\Comment_Probation;
use LWTV\Plugins\FacetWP;
use LWTV\Plugins\Gravity_Forms;
use LWTV\Plugins\Jetpack;
use LWTV\Plugins\Related_Posts_By_Taxonomy;
use LWTV\Plugins\SearchWP;
use LWTV\Plugins\WP_Rocket;
use LWTV\Plugins\Yoast;

class Plugins implements Component, Templater {

	/*
	 * Init
	 *
	 * Call the sub plugins
	 */
	public function init(): void {
		new Comment_Probation();
		new CMB2();
		new FacetWP();
		new Gravity_Forms();
		new Jetpack();
		new Related_Posts_By_Taxonomy();
		new SearchWP();
		new WP_Rocket();
		new Yoast();

		// Shadow Taxonomy
		require_once LWTV_THEME_PATH . '/plugins/shadow-taxonomy/index.php';
	}

	/**
	 * Gets tags to expose as methods accessible through `lwtv_plugin()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function get_template_tags(): array {
		return array(
			'jetpack_post_meta' => array( $this, 'jetpack_post_meta' ),
		);
	}

	/**
	 * Get Jetpack Post Meta
	 *
	 * @param  int    $post_id
	 * @param  string $meta_key
	 * @return mixed
	 */
	public function jetpack_post_meta() {
		if ( function_exists( 'sharing_display' ) ) {
			sharing_display( '', true );
		}

		if ( class_exists( 'Jetpack_Likes' ) ) {
			$custom_likes = new \Jetpack_Likes();
			$post_meta    = $custom_likes->post_likes( '' );
		}
	}
}
