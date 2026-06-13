<?php
/*
 * Postiz
 *
 * Integration with https://postiz.ipstenu.com/
 *
 * @package lwtv-plugin
 */
namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Postiz\Postiz as Postiz_Class;
use LWTV\Postiz\Of_The_Day;
use LWTV\Postiz\New_Post;

class Postiz implements Component {
	public function init(): void {
		add_action( 'acf/init', array( $this, 'register_hooks' ) );
	}

	public function register_hooks(): void {
		new Of_The_Day();
		new New_Post();
	}
}
