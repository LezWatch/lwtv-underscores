<?php
/**
 * WP Bootstrap Navwalker
 *
 * @package WP-Bootstrap-Navwalker
 *
 * A custom WordPress nav walker class to implement the Bootstrap 5 navigation style
 * in a custom theme using the WordPress built in menu manager.
 *
 * Author: AlexWebLab, Ipstenu
 * GitHub Plugin URI: https://github.com/AlexWebLab/bootstrap-5-wordpress-navbar-walker
 *
 * Brought to modern PHPCS standards
 *
 * register a new menu
 * register_nav_menu( 'main-menu', 'Main menu' );
 */

/**
 * WP_Bootstrap_Navwalker class.
 */
class WP_Bootstrap_Navwalker extends Walker_Nav_Menu {

	private $current_item;

	/**
	 * Default alignment values.
	 */
	private $dropdown_menu_alignment_values = array(
		'dropdown-menu-start',
		'dropdown-menu-end',
		'dropdown-menu-sm-start',
		'dropdown-menu-sm-end',
		'dropdown-menu-md-start',
		'dropdown-menu-md-end',
		'dropdown-menu-lg-start',
		'dropdown-menu-lg-end',
		'dropdown-menu-xl-start',
		'dropdown-menu-xl-end',
		'dropdown-menu-xxl-start',
		'dropdown-menu-xxl-end',
	);

	/**
	 * Starts the list before the elements are added.
	 *
	 * @since WP 3.0.0
	 *
	 * @see Walker_Nav_Menu::start_lvl()
	 *
	 * @param string           $output Used to append additional content (passed by reference).
	 * @param int              $depth  Depth of menu item. Used for padding.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$dropdown_menu_class[] = '';
		foreach ( $this->current_item->classes as $class ) {
			// If the alignment menu in value is in the class, we will process it as a dropdown.
			// @ToDo: Check only for 'dropdown-menu-*' and do not need a distinct array.
			if ( in_array( $class, $this->dropdown_menu_alignment_values, true ) ) {
				$dropdown_menu_class[] = $class;
			}
		}
		$indent  = str_repeat( "\t", $depth );
		$submenu = ( $depth > 0 ) ? ' sub-menu' : '';
		$output .= "\n$indent<ul class=\"dropdown-menu$submenu " . esc_attr( implode( ' ', $dropdown_menu_class ) ) . " depth_$depth\">\n";
	}

	/**
	 * Starts the element output.
	 *
	 * @since WP 3.0.0
	 * @since WP 4.4.0 The {@see 'nav_menu_item_args'} filter was added.
	 *
	 * @see Walker_Nav_Menu::start_el()
	 *
	 * @param string           $output Used to append additional content (passed by reference).
	 * @param WP_Nav_Menu_Item $item   Menu item data object.
	 * @param int              $depth  Depth of menu item. Used for padding.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 * @param int              $id     Current item ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$this->current_item = $item;

		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		$li_attributes = '';
		$class_names   = '';
		$value         = '';

		$classes = empty( $item->classes ) ? array() : (array) $item->classes;

		$classes[] = ( $args->walker->has_children ) ? 'dropdown' : '';
		$classes[] = 'nav-item';
		$classes[] = 'nav-item-' . $item->ID;
		if ( $depth && $args->walker->has_children ) {
			$classes[] = 'dropdown-menu dropdown-menu-end';
		}

		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );
		$class_names = ' class="' . esc_attr( $class_names ) . '"';

		$id = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args );
		$id = strlen( $id ) ? ' id="' . esc_attr( $id ) . '"' : '';

		$output .= $indent . '<li ' . $id . $value . $class_names . $li_attributes . '>';

		$attributes  = ! empty( $item->attr_title ) ? ' title="' . esc_attr( $item->attr_title ) . '"' : '';
		$attributes .= ! empty( $item->target ) ? ' target="' . esc_attr( $item->target ) . '"' : '';
		$attributes .= ! empty( $item->xfn ) ? ' rel="' . esc_attr( $item->xfn ) . '"' : '';
		$attributes .= ! empty( $item->url ) ? ' href="' . esc_attr( $item->url ) . '"' : '';

		$active_class   = ( $item->current || $item->current_item_ancestor || in_array( 'current_page_parent', $item->classes, true ) || in_array( 'current-post-ancestor', $item->classes, true ) ) ? 'active' : '';
		$nav_link_class = ( $depth > 0 ) ? 'dropdown-item ' : 'nav-link ';
		$attributes    .= ( $args->walker->has_children ) ? ' class="' . $nav_link_class . $active_class . ' dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"' : ' class="' . $nav_link_class . $active_class . '"';

		$item_output  = $args->before;
		$item_output .= '<a ' . $attributes . '>';
		$item_output .= $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after;
		$item_output .= '</a>';
		$item_output .= $args->after;

		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}
}
