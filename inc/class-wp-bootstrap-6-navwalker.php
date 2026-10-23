<?php
/**
 * WP Bootstrap Navwalker
 *
 * @package WP-Bootstrap-Navwalker
 *
 * @wordpress-plugin
 * Plugin Name: WP Bootstrap Navwalker
 * Plugin URI:  https://github.com/wp-bootstrap/wp-bootstrap-navwalker
 * Description: A custom WordPress nav walker class to implement the Bootstrap 5 navigation style in a custom theme using the WordPress built in menu manager.
 *
 * LezWatch.TV fork: outputs Bootstrap 6 Menu markup. Top-level items stay
 * `<li class="nav-item">`; a parent's toggle and its `.menu` are siblings;
 * child items are flat `<a class="menu-item">` (no `<li>`); third-level
 * parents are wrapped in `.submenu`.
 * Author: Edward McIntyre - @twittem, WP Bootstrap, William Patton - @pattonwebz, IanDelMar - @IanDelMar
 * Version: 5.0.0
 * Author URI: https://github.com/wp-bootstrap
 * GitHub Plugin URI: https://github.com/wp-bootstrap/wp-bootstrap-navwalker
 * GitHub Branch: master
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 */

// Check if Class Exists.
if ( class_exists( 'WP_Bootstrap_Navwalker' ) ) {
	return;
}

/**
 * WP_Bootstrap_Navwalker class.
 */
class WP_Bootstrap_Navwalker extends Walker_Nav_Menu {

	/**
	 * Whether the items_wrap contains schema microdata or not.
	 *
	 * @since 4.2.0
	 * @var boolean
	 */
	private $has_schema = false;

	/**
	 * The default major Bootstrap version for which to create the markup.
	 *
	 * @var int
	 */
	private $bs_version = 5;

	/**
	 * Closing tag owed by each open start_el(), popped by end_el().
	 *
	 * Bootstrap 6 child items have no `<li>`, so what end_el() must close
	 * depends on what start_el() opened.
	 *
	 * @var string[]
	 */
	private $close_stack = array();

	/**
	 * Ensures the items_wrap argument contains microdata.
	 *
	 * @since 4.2.0
	 */
	public function __construct() {
		if ( ! has_filter( 'wp_nav_menu_args', array( $this, 'add_schema_to_navbar_ul' ) ) ) {
			add_filter( 'wp_nav_menu_args', array( $this, 'add_schema_to_navbar_ul' ) );
		}
	}

	/**
	 * Starts the list before the elements are added.
	 *
	 * @since WP 3.0.0
	 *
	 * @see Walker_Nav_Menu::start_lvl()
	 *
	 * @param string           $output Used to append additional content (passed by reference).
	 * @param int              $depth  Depth of menu item. Used for padding.
	 * @param WP_Nav_Menu_Args $args   An object of `wp_nav_menu()` arguments.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
			$t = '';
			$n = '';
		} else {
			$t = "\t";
			$n = "\n";
		}
		$indent = str_repeat( $t, $depth );

		// Bootstrap 6 Menu container.
		$classes = array( 'menu' );

		/**
		 * Filters the CSS class(es) applied to a menu list element.
		 *
		 * @since WP 4.8.0
		 *
		 * @param array    $classes The CSS classes that are applied to the menu `<ul>` element.
		 * @param stdClass $args    An object of `wp_nav_menu()` arguments.
		 * @param int      $depth   Depth of menu item. Used for padding.
		 */
		$class_names = join( ' ', apply_filters( 'nav_menu_submenu_css_class', $classes, $args, $depth ) );
		$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

		/*
		 * The `.menu` container needs to have a labelledby
		 * attribute which points to it's trigger link.
		 *
		 * Form a string for the labelledby attribute from the the latest
		 * link with an id that was added to the $output.
		 */
		$labelledby = '';
		// Find all links with an id in the output.
		preg_match_all( '/(<a.*?id=\"|\')(.*?)\"|\'.*?>/im', $output, $matches );
		// With pointer at end of array check if we got an ID match.
		if ( end( $matches[2] ) ) {
			// Build a string to use as aria-labelledby.
			$labelledby = 'aria-labelledby="' . esc_attr( end( $matches[2] ) ) . '"';
		}
		$output .= "{$n}{$indent}<div$class_names $labelledby>{$n}";
	}

	/**
	 * Ends the list of after the elements are added.
	 *
	 * @see Walker_Nav_Menu::end_lvl()
	 *
	 * @param string           $output Used to append additional content (passed by reference).
	 * @param int              $depth  Depth of menu item. Used for padding.
	 * @param WP_Nav_Menu_Args $args   An object of `wp_nav_menu()` arguments.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$n       = ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) ? '' : "\n";
		$indent  = ( $n ) ? str_repeat( "\t", $depth ) : '';
		$output .= "{$indent}</div>{$n}";
	}

	/**
	 * Ends the element output: closes whatever start_el() opened.
	 *
	 * @see Walker_Nav_Menu::end_el()
	 *
	 * @param string           $output Used to append additional content (passed by reference).
	 * @param WP_Nav_Menu_Item $item   Page data object. Not used.
	 * @param int              $depth  Depth of page. Not Used.
	 * @param WP_Nav_Menu_Args $args   An object of `wp_nav_menu()` arguments.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$n       = ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) ? '' : "\n";
		$output .= (string) array_pop( $this->close_stack ) . $n;
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
	 * @param WP_Nav_Menu_Args $args   An object of `wp_nav_menu()` arguments.
	 * @param int              $id     Current item ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
			$t = '';
			$n = '';
		} else {
			$t = "\t";
			$n = "\n";
		}
		$indent = ( $depth ) ? str_repeat( $t, $depth ) : '';

		if ( false !== strpos( $args->items_wrap, 'itemscope' ) && false === $this->has_schema ) {
			$this->has_schema  = true;
			$args->link_before = '<span itemprop="name">' . $args->link_before;
			$args->link_after .= '</span>';
		}

		$classes = empty( $item->classes ) ? array() : (array) $item->classes;

		/*
		 * Updating the CSS classes of a menu item in the WordPress
		 * Customizer preview results in all classes defined in that
		 * particular input box to come in as one big class string.
		 */
		$split_on_spaces = function ( $a_class ) {
			return preg_split( '/\s+/', $a_class );
		};
		$classes         = $this->flatten( array_map( $split_on_spaces, $classes ) );

		/*
		 * Initialize some holder variables to store specially handled item
		 * wrappers and icons.
		 */
		$linkmod_classes = array();
		$icon_classes    = array();

		/*
		 * Get an updated $classes array without linkmod or icon classes.
		 *
		 * NOTE: linkmod and icon class arrays are passed by reference and
		 * are maybe modified before being used later in this function.
		 */
		$classes = $this->separate_linkmods_and_icons_from_classes( $classes, $linkmod_classes, $icon_classes, $depth );

		// Join any icon classes plucked from $classes into a string.
		$icon_class_string = join( ' ', $icon_classes );

		// Whether the current item is a dropdown.
		$is_dropdown = false;
		if ( $this->has_children && 1 !== $args->depth ) {
			$is_dropdown = true;
			if ( $depth >= $args->depth - 1 && 0 !== $args->depth ) {
				$is_dropdown = false;
			}
		}

		// Whether the current item is a dropdown item.
		$is_dropdown_item = false;
		if ( ! ( $this->has_children && 0 === $depth ) && $depth > 0 ) {
			$is_dropdown_item = true;
		}

		/*
		 * Whether the current item is active or the item is an ancestor of
		 * an active item.
		 */
		$is_active = false;
		if ( $item->current || $item->current_item_ancestor ) {
			if ( ! ( $item->current_item_ancestor && 1 === $args->depth ) ) {
				$is_active = true;
			}
		}

		/**
		 * Filters the arguments for a single nav menu item.
		 *
		 * @since WP 4.4.0
		 *
		 * @param WP_Nav_Menu_Args $args  An object of `wp_nav_menu()` arguments.
		 * @param WP_Nav_Menu_Item $item  Menu item data object.
		 * @param int              $depth Depth of menu item. Used for padding.
		 *
		 * @var WP_Nav_Menu_Args
		 */
		$args = apply_filters( 'nav_menu_item_args', $args, $item, $depth );

		// Add some additional default classes to the item.
		$classes[] = 'menu-item-' . $item->ID;
		if ( 0 === $depth ) {
			$classes[] = 'nav-item';
		}

		// Allow filtering the classes.
		$classes = apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth );

		// WordPress adds `menu-item` to every item, but in Bootstrap 6 that is
		// the Menu link component class. Keep it only on child links (re-added below).
		$classes = array_diff( $classes, array( 'menu-item' ) );

		// Form a string of classes in format: class="class_names".
		$class_names = join( ' ', $classes );
		$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

		/**
		 * Filters the ID applied to a menu item's list item element.
		 *
		 * @since WP 3.0.1
		 * @since WP 4.1.0 The `$depth` parameter was added.
		 *
		 * @param string           $menu_id The ID that is applied to the menu item's `<li>` element.
		 * @param WP_Nav_Menu_Item $item    The current menu item.
		 * @param WP_Nav_Menu_Args $args    An object of `wp_nav_menu()` arguments.
		 * @param int              $depth   Depth of menu item. Used for padding.
		 */
		$id = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth );
		$id = $id ? ' id="' . esc_attr( $id ) . '"' : '';

		/*
		 * Bootstrap 6 wrappers:
		 * - top level:         <li class="nav-item">, closed with </li>
		 * - child with kids:   <div class="submenu">, closed with </div>
		 * - child leaf:        no wrapper; its classes and id move onto the <a>
		 */
		$child_classes = '';
		$child_id      = '';
		if ( 0 === $depth ) {
			$output             .= $indent . '<li ' . $id . $class_names . '>';
			$this->close_stack[] = '</li>';
		} elseif ( $is_dropdown ) {
			$output             .= $indent . '<div' . $id . ' class="' . esc_attr( trim( 'submenu ' . join( ' ', $classes ) ) ) . '">';
			$this->close_stack[] = '</div>';
		} else {
			$child_classes       = join( ' ', $classes );
			$child_id            = 'menu-item-' . $item->ID;
			$this->close_stack[] = '';
		}

		// Initialize array for holding the $atts for the link item.
		$atts           = array();
		$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
		$atts['target'] = ! empty( $item->target ) ? $item->target : '';
		if ( '_blank' === $item->target && empty( $item->xfn ) ) {
			$atts['rel'] = 'noopener noreferrer';
		} else {
			$atts['rel'] = ! empty( $item->xfn ) ? $item->xfn : '';
		}

		// If the item has_children add atts to <a>.
		if ( $is_dropdown ) {
			$infix = '';
			if ( 5 === $this->get_bs_version( $args ) ) {
				$infix = '-bs';
			}

			$atts['href'] = '#';
			$atts['id']   = 'menu-item-dropdown-' . $item->ID;

			if ( 0 === $depth ) {
				// Top-level toggle; its `.menu` is the next sibling (start_lvl).
				$atts[ "data$infix-toggle" ] = 'menu';
				$atts['aria-expanded']       = 'false';
				$atts['class']               = 'nav-link';
			} else {
				// `.submenu > .menu-item` is the submenu trigger; Menu handles it, no toggle attr.
				$atts['class'] = 'menu-item';
			}

			$atts['class'] .= $is_active ? ' active' : '';
		} else {
			if ( true === $this->has_schema ) {
				$atts['itemprop'] = 'url';
			}

			$atts['href'] = ! empty( $item->url ) ? $item->url : '#';

			// For items in menus use .menu-item instead of .nav-link.
			if ( $is_dropdown_item ) {
				$atts['id']     = $child_id;
				$atts['class']  = trim( 'menu-item ' . $child_classes );
				$atts['class'] .= $is_active ? ' active' : '';
			} else {
				$atts['class'] = 'nav-link';
			}
		}

		$atts['aria-current'] = $item->current ? 'page' : '';

		// Update atts of this item based on any custom linkmod classes.
		$atts = $this->update_atts_for_linkmod_type( $atts, $linkmod_classes );

		// Allow filtering of the $atts array before using it.
		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

		// Set a typeflag to easily test if this is a linkmod or not.
		$linkmod_type = $this->get_linkmod_type( $linkmod_classes );

		// A linkmod element (header/divider/text) supplies its own class, and it
		// is not a link, so it takes the item's other classes but not `menu-item`.
		// Pulling them out here avoids emitting two class attributes.
		$linkmod_extra_classes = '';
		if ( '' !== $linkmod_type ) {
			$item_classes          = isset( $atts['class'] ) ? preg_split( '/\s+/', $atts['class'], -1, PREG_SPLIT_NO_EMPTY ) : array();
			$linkmod_extra_classes = join( ' ', array_diff( $item_classes, array( 'menu-item' ) ) );
			unset( $atts['class'] );
		}

		// Build a string of html containing all the atts for the item.
		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		// START appending the internal item contents to the output.
		$item_output = isset( $args->before ) ? $args->before : '';

		/*
		 * This is the start of the internal nav item. Depending on what
		 * kind of linkmod we have we may need different wrapper elements.
		 */
		if ( '' !== $linkmod_type ) {
			// Is linkmod, output the required element opener.
			$item_output .= $this->linkmod_element_open( $linkmod_type, $attributes, $linkmod_extra_classes );
		} else {
			// With no link mod type set this must be a standard <a> tag.
			$item_output .= '<a' . $attributes . '>';
		}

		/*
		 * Initiate empty icon var, then if we have a string containing any
		 * icon classes form the icon markup with an <i> element. This is
		 * output inside of the item before the $title (the link text).
		 */
		$icon_html = '';
		if ( ! empty( $icon_class_string ) ) {
			// Append an <i> with the icon classes to what is output before links.
			$icon_html = '<i class="' . esc_attr( $icon_class_string ) . '" aria-hidden="true"></i> ';
		}

		/** This filter is documented in wp-includes/post-template.php */
		$title = apply_filters( 'the_title', $item->title, $item->ID );

		/**
		 * Filters a menu item's title.
		 *
		 * @since WP 4.4.0
		 *
		 * @param string           $title The menu item's title.
		 * @param WP_Nav_Menu_Item $item  The current menu item.
		 * @param WP_Nav_Menu_Args $args  An object of `wp_nav_menu()` arguments.
		 * @param int              $depth Depth of menu item. Used for padding.
		 */
		$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

		// If a screen-reader linkmod was set, apply it to the nav item's text only.
		if ( in_array( 'sr-only', $linkmod_classes, true ) ) {
			$title         = $this->wrap_for_screen_reader( $title );
			$keys_to_unset = array_keys( $linkmod_classes, 'sr-only', true );
			foreach ( $keys_to_unset as $k ) {
				unset( $linkmod_classes[ $k ] );
			}
		}

		// Put the item contents into $output (a divider is an empty <hr>).
		if ( 'menu-divider' !== $linkmod_type ) {
			$item_output .= isset( $args->link_before ) ? $args->link_before . $icon_html . $title . $args->link_after : '';
		}

		/*
		 * This is the end of the internal nav item. We need to close the
		 * correct element depending on the type of link or link mod.
		 */
		if ( '' !== $linkmod_type ) {
			// Is linkmod, output the required closing element.
			$item_output .= $this->linkmod_element_close( $linkmod_type );
		} else {
			// With no link mod type set this must be a standard <a> tag.
			$item_output .= '</a>';
		}

		$item_output .= isset( $args->after ) ? $args->after : '';

		// END appending the internal item contents to the output.
		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}

	/**
	 * Menu fallback.
	 *
	 * If this function is assigned to the wp_nav_menu's fallback_cb variable
	 * and a menu has not been assigned to the theme location in the WordPress
	 * menu manager the function will display nothing to a non-logged in user,
	 * and will add a link to the WordPress menu manager if logged in as an admin.
	 *
	 * @param array $args Arguments passed from the `wp_nav_menu()` function.
	 * @return string|void String when echo is false.
	 */
	public static function fallback( $args ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		// Initialize var to store fallback html.
		$fallback_output = '';

		// Menu container opening tag.
		$show_container = false;
		if ( $args['container'] ) {
			/**
			 * Filters the list of HTML tags that are valid for use as menu containers.
			 *
			 * @since WP 3.0.0
			 *
			 * @param array $tags The acceptable HTML tags for use as menu containers.
			 *                    Default is array containing 'div' and 'nav'.
			 */
			$allowed_tags = apply_filters( 'wp_nav_menu_container_allowedtags', array( 'div', 'nav' ) );
			if ( is_string( $args['container'] ) && in_array( $args['container'], $allowed_tags, true ) ) {
				$show_container   = true;
				$class            = $args['container_class'] ? ' class="menu-fallback-container ' . esc_attr( $args['container_class'] ) . '"' : ' class="menu-fallback-container"';
				$id               = $args['container_id'] ? ' id="' . esc_attr( $args['container_id'] ) . '"' : '';
				$fallback_output .= '<' . $args['container'] . $id . $class . '>';
			}
		}

		// The fallback menu.
		$class            = $args['menu_class'] ? ' class="menu-fallback-menu ' . esc_attr( $args['menu_class'] ) . '"' : ' class="menu-fallback-menu"';
		$id               = $args['menu_id'] ? ' id="' . esc_attr( $args['menu_id'] ) . '"' : '';
		$fallback_output .= '<ul' . $id . $class . '>';
		$fallback_output .= '<li class="nav-item"><a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '" class="nav-link" title="' . esc_attr__( 'Add a menu', 'lwtv-underscores' ) . '">' . esc_html__( 'Add a menu', 'lwtv-underscores' ) . '</a></li>';
		$fallback_output .= '</ul>';

		// Menu container closing tag.
		if ( $show_container ) {
			$fallback_output .= '</' . $args['container'] . '>';
		}

		// If $args has 'echo' key and it's true echo, otherwise return.
		if ( array_key_exists( 'echo', $args ) && $args['echo'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $fallback_output;
		} else {
			return $fallback_output;
		}
	}

	/**
	 * Filter to ensure the items_wrap argument contains microdata.
	 *
	 * @since 4.2.0
	 *
	 * @param  array $args The nav instance arguments.
	 * @return array $args The altered nav instance arguments.
	 */
	public function add_schema_to_navbar_ul( $args ) {
		if ( isset( $args['items_wrap'] ) ) {
			$wrap = $args['items_wrap'];
			if ( strpos( $wrap, 'SiteNavigationElement' ) === false ) {
				$args['items_wrap'] = preg_replace( '/(>).*>?\%3\$s/', ' itemscope itemtype="http://www.schema.org/SiteNavigationElement"$0', $wrap );
			}
		}
		return $args;
	}

	/**
	 * Finds any custom linkmod or icon classes and store in their holder
	 * arrays then remove them from the main classes array.
	 *
	 * Supported linkmods: .disabled, .menu-header, .menu-divider, .menu-text, .visually-hidden (or .sr-only)
	 * (the v5 .dropdown-* names are still accepted from menu item CSS classes)
	 * Supported iconsets: Font Awesome 4/5, Glypicons
	 *
	 * NOTE: This accepts the linkmod and icon arrays by reference.
	 *
	 * @since 4.0.0
	 *
	 * @param array   $classes         An array of classes currently assigned to the item.
	 * @param array   $linkmod_classes An array to hold linkmod classes.
	 * @param array   $icon_classes    An array to hold icon classes.
	 * @param integer $depth           An integer holding current depth level.
	 * @return array  $classes A maybe modified array of classnames.
	 */
	private function separate_linkmods_and_icons_from_classes( $classes, &$linkmod_classes, &$icon_classes, $depth ) {
		// Loop through $classes array to find linkmod or icon classes.
		foreach ( $classes as $key => $class ) {
			/*
			 * If any special classes are found, store the class in it's
			 * holder array and and unset the item from $classes.
			 */
			if ( preg_match( '/^disabled|^sr-only|^visually-hidden/i', $class ) ) {
				// Test for .disabled or screen-reader classes. Stored lowercase so the
				// strict comparisons in get_linkmod_type() and
				// update_atts_for_linkmod_type() match what this /i regex matched.
				// Bootstrap 6's .visually-hidden is stored as the v4 .sr-only
				// linkmod name, which existing menu items still use.
				$linkmod_classes[] = ( 'visually-hidden' === strtolower( $class ) ) ? 'sr-only' : strtolower( $class );
				unset( $classes[ $key ] );
			} elseif ( preg_match( '/^(dropdown|menu)-(header|divider|item-text|text)$/i', $class ) && $depth > 0 ) {
				/*
				 * Test for .dropdown-header or .dropdown-divider and a
				 * depth greater than 0 - IE inside a dropdown. Lowercased as above.
				 */
				$linkmod_classes[] = strtolower( $class );
				unset( $classes[ $key ] );
			} elseif ( preg_match( '/^fa-(\S*)?|^fa(s|r|l|b)?(\s?)?$/i', $class ) ) {
				// Font Awesome.
				$icon_classes[] = $class;
				unset( $classes[ $key ] );
			} elseif ( preg_match( '/^glyphicon-(\S*)?|^glyphicon(\s?)$/i', $class ) ) {
				// Glyphicons.
				$icon_classes[] = $class;
				unset( $classes[ $key ] );
			}
		}

		return $classes;
	}

	/**
	 * Returns a string containing a linkmod type and updates the $atts
	 * array accordingly depending on the decided.
	 *
	 * @since 4.0.0
	 *
	 * @param array $linkmod_classes Array of any link modifier classes.
	 * @return string Empty for default, a linkmod type string otherwise.
	 */
	private function get_linkmod_type( $linkmod_classes = array() ) {
		$linkmod_type = '';
		// Loop through array of linkmod classes to handle their $atts.
		if ( ! empty( $linkmod_classes ) ) {
			foreach ( $linkmod_classes as $link_class ) {
				if ( ! empty( $link_class ) ) {

					// Check for special class types and set a flag for them.
					if ( in_array( $link_class, array( 'dropdown-header', 'menu-header' ), true ) ) {
						$linkmod_type = 'menu-header';
					} elseif ( in_array( $link_class, array( 'dropdown-divider', 'menu-divider' ), true ) ) {
						$linkmod_type = 'menu-divider';
					} elseif ( in_array( $link_class, array( 'dropdown-item-text', 'menu-text' ), true ) ) {
						$linkmod_type = 'menu-text';
					}
				}
			}
		}
		return $linkmod_type;
	}

	/**
	 * Updates the attributes of a nav item depending on the limkmod classes.
	 *
	 * @since 4.0.0
	 *
	 * @param array $atts            Array of atts for the current link in nav item.
	 * @param array $linkmod_classes An array of classes that modify link or nav item behaviors or displays.
	 * @return array Maybe updated array of attributes for item.
	 */
	private function update_atts_for_linkmod_type( $atts = array(), $linkmod_classes = array() ) {
		if ( ! empty( $linkmod_classes ) ) {
			foreach ( $linkmod_classes as $link_class ) {
				if ( ! empty( $link_class ) ) {
					/*
					 * Update $atts with a space and the extra classname
					 * so long as it's not a sr-only class.
					 */
					// The linkmod element supplies its own class, so skip linkmod names.
					if ( 'sr-only' !== $link_class && ! preg_match( '/^(dropdown|menu)-(header|divider|item-text|text)$/', $link_class ) ) {
						$atts['class'] .= ' ' . esc_attr( $link_class );
					}
					// Check for special class types we need additional handling for.
					if ( 'disabled' === $link_class ) {
						// Convert link to '#' and unset open targets.
						$atts['href'] = '#';
						unset( $atts['target'] );
					} elseif ( preg_match( '/^(dropdown|menu)-(header|divider|item-text|text)$/', $link_class ) ) {
						// Store a type flag and unset href and target.
						unset( $atts['href'] );
						unset( $atts['target'] );
					}
				}
			}
		}
		return $atts;
	}

	/**
	 * Wraps the passed text in a screen reader only class.
	 *
	 * @since 4.0.0
	 *
	 * @param string $text The string of text to be wrapped in a screen reader class.
	 * @return string The string wrapped in a span with the class.
	 */
	private function wrap_for_screen_reader( $text = '' ) {
		if ( $text ) {
			// Bootstrap 6 dropped .sr-only; .visually-hidden is its replacement.
			$text = '<span class="visually-hidden">' . $text . '</span>';
		}
		return $text;
	}

	/**
	 * Returns the correct opening element and attributes for a linkmod.
	 *
	 * @since 4.0.0
	 *
	 * @param string $linkmod_type A string containing a linkmod type flag.
	 * @param string $attributes    A string of attributes to add to the element.
	 * @param string $extra_classes The item's classes to merge into the element's class.
	 * @return string A string with the opening tag for the element with attribibutes added.
	 */
	private function linkmod_element_open( $linkmod_type, $attributes = '', $extra_classes = '' ) {
		// start_el() removes `class` from $attributes for linkmods and passes the
		// item's other classes here, so the element gets a single class attribute.
		$class  = esc_attr( trim( $linkmod_type . ' ' . $extra_classes ) );
		$output = '';
		if ( 'menu-text' === $linkmod_type || 'menu-header' === $linkmod_type ) {
			// A span, not a real heading, so it doesn't confuse screen readers.
			$output .= '<span class="' . $class . '"' . $attributes . '>';
		} elseif ( 'menu-divider' === $linkmod_type ) {
			// A divider has no content; the title is not output (see linkmod_element_close).
			$output .= '<hr class="' . $class . '"' . $attributes . '>';
		}
		return $output;
	}

	/**
	 * Returns the correct closing tag for the linkmod element.
	 *
	 * @since 4.0.0
	 *
	 * @param string $linkmod_type A string containing a special linkmod type.
	 * @return string A string with the closing tag for this linkmod type.
	 */
	private function linkmod_element_close( $linkmod_type ) {
		$output = '';
		if ( 'menu-header' === $linkmod_type || 'menu-text' === $linkmod_type ) {
			$output .= '</span>';
		}
		return $output;
	}

	/**
	 * Flattens a multidimensional array to a simple array.
	 *
	 * @param array $array A multidimensional array.
	 * @return array A simple array.
	 */
	public function flatten( $an_array ) {
		$result = array();
		foreach ( $an_array as $element ) {
			if ( is_array( $element ) ) {
				array_push( $result, ...$this->flatten( $element ) );
			} else {
				$result[] = $element;
			}
		}
		return $result;
	}

	/**
	 * Retrieves the Bootstrap version to use.
	 *
	 * @param WP_Nav_Menu_Args $args An object of `wp_nav_menu()` arguments.
	 * @return int Major Bootstrap version
	 */
	private function get_bs_version( $args ) {
		if ( property_exists( $args, 'bs_version' ) && 5 === (int) $args->bs_version ) {
			return (int) $args->bs_version;
		}
		return $this->bs_version;
	}
}
