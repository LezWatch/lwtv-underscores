<?php
/**
 * Class Plugin
 *
 * @package LWTV
 */

namespace LWTV;

use LWTV\_Components;
use LWTV\_Helpers\Utils;

/**
 * Class Plugin
 *
 * All methods listed below may be called via lwtv_plugin()->METHOD()
 * i.e. lwtv_plugin()->display_scores( $post_id );
 *
 * They are intended to be used in the theme files ONLY.
 *
 * CPTs
 * @method array  get_cpt_related_posts( $show_id )                     \_Components\CPTs
 * @method string get_related_archive_header( $tag_id )                 \_Components\CPTs
 * @method array  get_shows_like_this_show( $show_id )                  \_Components\CPTs
 * @method bool   has_cpt_related_posts( $show_id )                     \_Components\CPTs
 * @method void   hide_actor_data( $post_id )                           \_Components\CPTs
 * @method string the_actor_privacy_warning( $post_id )                 \_Components\CPTs
 *
 * GRADING
 * @method string display_scores( $show_id )        \_Components\Grading
 * @method array  get_all_scores( $show_id )        \_Components\Grading
 *
 * OTD
 * @method string get_wp_version()         \_Components\Of_The_Day
 * @method string get_rss_otd_last_build() \_Components\Of_The_Day
 * @method string get_rss_otd_feed()       \_Components\Of_The_Day
 *
 * PLUGINS
 * @method array  jetpack_post_meta()                 \_Components\Plugins
 *
 * QUEERY / LOOPS
 * @method bool   is_actor_queer( $the_id )           \_Components\Queeries
 *
 * REST API
 * @method string generate_tvshow_calendar( $date )                 \_Components\Rest_API
 * @method array  get_json_statistics( $stat_type, $format, $page ) \_Components\Rest_API
 * @method mixed  get_json_similar_show( $show_slug )               \_Components\Rest_API
 * @method array  get_json_export( $type, $item, $tax, $term )      \_Components\Rest_API
 * @method array  get_json_last_death()                             \_Components\Rest_API
 * @method array  get_list_of_dead_characters( $loop )              \_Components\Rest_API
 * @method array  get_whats_on_date( $date )                        \_Components\Rest_API
 * @method array  get_whats_on_show( $show )                        \_Components\Rest_API
 * @method mixed  get_what_happened_on_date( $date )                \_Components\Rest_API
 *
 * STATISTICS
 * @method array  generate_shows_count( $type, $tax, $term )                               \_Components\Statistics
 * @method string generate_stats_block( $attributes )                                      \_Components\Statistics
 * @method string generate_stats_block_actor( $attributes )                                \_Components\Statistics
 * @method mixed  generate_statistics( $subject, $data, $format, $post_id, $custom_array ) \_Components\Statistics
 *
 * SYMBOLICONS
 * @method string get_icon_svg( string $slug )   \_Components\Symbolicons
 * @method string get_symbolicon( string $slug ) \_Components\Symbolicons
 *
 * THEME
 * @method string get_actor_age( $actor_id )                                \_Components\Theme
 * @method string get_actor_birthday( $actor_id )                           \_Components\Theme
 * @method mixed  get_actor_characters( $actor_id )                         \_Components\Theme
 * @method array  get_actor_data( $actor_id, $data )                        \_Components\Theme
 * @method string get_actor_dead( $actor_id )                               \_Components\Theme
 * @method string get_actor_gender( $actor_id )                             \_Components\Theme
 * @method string get_actor_pronouns( $actor_id )                           \_Components\Theme
 * @method string get_actor_sexuality( $actor_id )                          \_Components\Theme
 * @method void   get_admin_tools( $post_id )                               \_Components\Theme
 * @method string get_author_social( $author_id )                           \_Components\Theme
 * @method string get_author_favorite_shows( $author_id )                   \_Components\Theme
 * @method array  get_character_data( $character_id, $data )                \_Components\Theme
 * @method mixed  get_characters_list( $post_id, $format )                  \_Components\Theme
 * @method string get_chars_for_show( $show_id )                            \_Components\Theme
 * @method string get_chars_relationships( $post_id, $format )              \_Components\Theme
 * @method string get_last_updated( $post_id )                              \_Components\Theme
 * @method string get_last_death( $post_id )                                \_Components\Theme
 * @method string get_show_content_warning( $show_id )                      \_Components\Theme
 * @method string get_microformats_fix( $post_id )                          \_Components\Theme
 * @method string get_post_types_by_taxonomy( $tax )                        \_Components\Theme
 * @method string get_show_stars( $show_id )                                \_Components\Theme
 * @method string get_stats_symbolicon( $stat_type )                        \_Components\Theme
 * @method array  get_tax_archive_title( $location, $post_type, $taxonomy ) \_Components\Theme
 * @method string get_tvmaze_episodes( $show_id )                           \_Components\Theme
 * @method string get_ways_to_watch( $show_id )                             \_Components\Theme
 * @method bool   is_actor_birthday( $actor_id )                            \_Components\Theme
 *
 * THIS YEAR
 * @method string get_this_year_display( $year ) \_Components\This_Year
 *
 * TRANSIENTS
 * @method string get_transient( string $slug ) \_Components\Transients
 *
 */

class Plugin {
	/**
	 * List of components.
	 *
	 * @var _Components\Component[]
	 */
	private $components;

	/**
	 * Templater instances.
	 *
	 * @var _Components\Templater[]
	 */
	private $template_tags;

	/**
	 * Specify list of supported components.
	 *
	 * The components are called in order (top down), so if a component is used by another,
	 * it must be on top. Otherwise, alphabetical is fine.
	 *
	 * @return string[]
	 */
	protected function core_components(): array {
		return array(
			_Components\Admin_Menu::class,
			_Components\Plugins::class, // Needs to be higher up for requirements later.
			_Components\Block_Types_Allowed::class,
			_Components\Blocks::class,
			_Components\Calendar::class,
			_Components\CPTs::class,
			_Components\Dashboard_Widgets::class,
			_Components\Debugger::class,
			_Components\Features::class,
			_Components\Grading::class,
			_Components\Of_The_Day::class,
			_Components\Queeries::class,
			_Components\Rest_API::class,
			_Components\Statistics::class,
			_Components\Symbolicons::class,
			_Components\Theme::class,
			_Components\This_Year::class,
			_Components\Transients::class,
			_Components\WP_CLI::class,
		);
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$components = $this->core_components();
		foreach ( $components as $component ) {
			$instance = new $component( $this );

			Utils::throw_if_not_of_type( $instance, _Components\Component::class );

			$this->components[ $component ] = $instance;
		}

		$this->template_tags = array_filter(
			$this->components,
			static function ( _Components\Component $component ) {
				return $component instanceof _Components\Templater;
			}
		);
	}

	/**
	 * Add a new component to the list.
	 *
	 * @param _Components\Component $component The new Component to register.
	 *
	 * @throws \RuntimeException Throws an exception if component is already registered.
	 *
	 * @return void
	 */
	public function add_component( _Components\Component $component ) {
		$component_slug = get_class( $component );

		if ( isset( $this->components[ $component_slug ] ) ) {
			throw new \RuntimeException( 'A Component named ' . esc_html( $component_slug ) . ' has already been registered' );
		}

		$this->components[ $component_slug ] = $component;
		$component->init();

		if ( $component instanceof _Components\Templater ) {
			$this->template_tags[ $component_slug ] = $component;
		}
	}

	/**
	 * Init the theme and its components.
	 *
	 * @return void
	 */
	public function init() {
		foreach ( $this->components as $component ) {
			$component->init();
		}
	}

	/**
	 * Magic call method.
	 *
	 *  Will proxy to the template tag $method, unless it is not available, in which case an exception will be thrown.
	 *
	 * @param string $method Template tag name.
	 * @param array  $args   Template tag arguments.
	 *
	 * @return mixed Template tag result, or null if template tag only outputs markup.
	 *
	 * @throws \RuntimeException Thrown if the template tag does not exist.
	 */
	public function __call( $method, array $args ) {
		foreach ( $this->template_tags as $component ) {
			$tags = $component->get_template_tags();
			if ( isset( $tags[ $method ] ) && is_callable( $tags[ $method ] ) ) {
				return call_user_func_array( $tags[ $method ], $args );
			}
		}

		throw new \RuntimeException(
			sprintf(
				'The template tag %s does not exist.',
				'lwtv_plugin()->' . esc_html( $method ) . '()'
			),
		);
	}
}
