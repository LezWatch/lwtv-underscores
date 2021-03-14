<?php
/**
 * Bootstrap Navwalker
 *
 * @package YIKES Starter
 */

/**
 * Class Name: WP_Bootstrap_Navwalker
 * GitHub URI: https://github.com/dupkey/bs4navwalker
 * Description: A custom WordPress nav walker class for Bootstrap 4 (v4.0.0-alpha.1) nav menus in a custom theme using the WordPress built in menu manager
 * Version: 0.1
 * Author: Dominic Businaro - @dominicbusinaro
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */
class WP_Bootstrap_Navwalker extends Walker_Nav_Menu {

	/**
	 * Previous menu item data object.
	 * 
	 * @var object
	 */
	private $previous_item;

	/**
	 * Current menu item data object.
	 * 
	 * @var object
	 */
	private $current_item;

	/**
	 * Previous menu item data object depth.
	 * 
	 * @var object
	 */
	private $previous_item_depth;

	/**
	 * Current menu item data object depth.
	 * 
	 * @var object
	 */
	private $current_item_depth;

	/**
	 * Starts the list before the elements are added.
	 *
	 * @see Walker::start_lvl()
	 *
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int    $depth Depth of menu item. Used for padding.
	 * @param array  $args An array of arguments. @see wp_nav_menu().
	 */
	public function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent  = str_repeat( "\t", $depth );
		$list_id = ! is_null( $this->current_item ) ? $this->current_item->post_name : '';
		$output .= "\n$indent<ul aria-labelledby=\"" . esc_attr( $list_id ) . "\" class=\"dropdown-menu\">\n";
	}

	/**
	 * Ends the list of after the elements are added.
	 *
	 * @see Walker::end_lvl()
	 *
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int    $depth  Depth of menu item. Used for padding.
	 * @param array  $args   An array of arguments. @see wp_nav_menu().
	 */
	public function end_lvl( &$output, $depth = 0, $args = array() ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
	}

	/**
	 * Start the element output.
	 *
	 * @see Walker::start_el()
	 *
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $item   Menu item data object.
	 * @param int    $depth  Depth of menu item. Used for padding.
	 * @param array  $args   An array of arguments. @see wp_nav_menu().
	 * @param int    $id     Current item ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
		$this->current_item       = $item;
		$this->current_item_depth = $depth;

		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;

		/**
		 * Filter the CSS class(es) applied to a menu item's list item element.
		 *
		 * @since 3.0.0
		 * @since 4.1.0 The `$depth` parameter was added.
		 *
		 * @param array  $classes The CSS classes that are applied to the menu item's `<li>` element.
		 * @param object $item    The current menu item.
		 * @param array  $args    An array of {@see wp_nav_menu()} arguments.
		 * @param int    $depth   Depth of menu item. Used for padding.
		 */
		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) );

		// New.
		$class_names .= ' nav-item';

		if ( in_array( 'menu-item-has-children', $classes, true ) ) {
			$class_names .= ' dropdown';
		}

		if ( in_array( 'current-menu-item', $classes, true ) ) {
			$class_names .= ' active';
		}

		$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

		/**
		 * Filter the ID applied to a menu item's list item element.
		 *
		 * @since 3.0.1
		 * @since 4.1.0 The `$depth` parameter was added.
		 *
		 * @param string $menu_id The ID that is applied to the menu item's `<li>` element.
		 * @param object $item    The current menu item.
		 * @param array  $args    An array of {@see wp_nav_menu()} arguments.
		 * @param int    $depth   Depth of menu item. Used for padding.
		 */
		$id = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth );
		$id = $id ? ' id="' . esc_attr( $id ) . '"' : '';

		$output .= $indent . '<li' . $id . $class_names . '>';

		$atts           = array();
		$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
		$atts['target'] = ! empty( $item->target ) ? $item->target : '';
		$atts['rel']    = ! empty( $item->xfn ) ? $item->xfn : '';
		if ( ! empty( $item->url ) && '#' !== $item->url ) {
			$atts['href'] = $item->url;
		}


		// New.
		if ( 0 === $depth ) {
			$atts['class'] = 'nav-link';
		}

		if ( in_array( 'menu-item-has-children', $classes, true ) ) {
			$atts['class']         = isset( $atts['class'] ) ? $atts['class'] . ' dropdown-toggle' : 'dropdown-toggle';
			$atts['aria-expanded'] = 'false';
		}

		if ( in_array( 'menu-item-has-children', $classes, true ) ) {
			$atts['id'] = $item->post_name;
		}

		if ( $depth > 0 ) {
			$atts['class'] = isset( $atts['class'] ) ? $atts['class'] . ' dropdown-item' : 'dropdown-item';
		}

		if ( 'current-menu-item' === $item->classes || is_array( $item->classes ) && in_array( 'current-menu-item', $item->classes, true ) ) {
			$atts['class']       .= ' active';
			$atts['aria-current'] = 'page';
		}

		/**
		 * Filter the HTML attributes applied to a menu item's anchor element.
		 *
		 * @since 3.6.0
		 * @since 4.1.0 The `$depth` parameter was added.
		 *
		 * @param array $atts {
		 *     The HTML attributes applied to the menu item's `<a>` element, empty strings are ignored.
		 *
		 *     @type string $title  Title attribute.
		 *     @type string $target Target attribute.
		 *     @type string $rel    The rel attribute.
		 *     @type string $href   The href attribute.
		 * }
		 * @param object $item  The current menu item.
		 * @param array  $args  An array of {@see wp_nav_menu()} arguments.
		 * @param int    $depth Depth of menu item. Used for padding.
		 */
		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		$tag = ( in_array( 'menu-item-has-children', $classes, true ) ) ? 'button' : 'a';

		$item_output  = $args->before;
		$item_output .= '<' . $tag . $attributes . '>';
		/** This filter is documented in wp-includes/post-template.php */
		$item_output .= $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after;
		$item_output .= '</' . $tag . '>';
		$item_output .= $args->after;

		/**
		 * Filter a menu item's starting output.
		 *
		 * The menu item's starting output only includes `$args->before`, the opening `<a>`,
		 * the menu item's title, the closing `</a>`, and `$args->after`. Currently, there is
		 * no filter for modifying the opening and closing `<li>` for a menu item.
		 *
		 * @since 3.0.0
		 *
		 * @param string $item_output The menu item's starting HTML output.
		 * @param object $item        Menu item data object.
		 * @param int    $depth       Depth of menu item. Used for padding.
		 * @param array  $args        An array of {@see wp_nav_menu()} arguments.
		 */
		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}

	/**
	 * Ends the element output, if needed.
	 *
	 * @see Walker::end_el()
	 *
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $item   Page data object. Not used.
	 * @param int    $depth  Depth of page. Not Used.
	 * @param array  $args   An array of arguments. @see wp_nav_menu().
	 */
	public function end_el( &$output, $item, $depth = 0, $args = array() ) {
		if ( 0 !== $depth ) {
			$output .= "</li>\n";
		}

		$this->previous_item       = $item;
		$this->previous_item_depth = $depth;
	}
}