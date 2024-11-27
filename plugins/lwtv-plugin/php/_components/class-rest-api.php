<?php
/*
 * Rest API
 */
namespace LWTV\_Components;

use LWTV\Rest_API\Whats_On_JSON;
use LWTV\Rest_API\What_Happened_JSON;
use LWTV\Rest_API\This_Year_JSON;
use LWTV\Rest_API\Stats_JSON;
use LWTV\Rest_API\Shows_Like_JSON;
use LWTV\Rest_API\OTD_JSON;
use LWTV\Rest_API\List_JSON;
use LWTV\Rest_API\IMDb_JSON;
use LWTV\Rest_API\Fresh_JSON;
use LWTV\Rest_API\Export_JSON;
use LWTV\Rest_API\BYQ;
use LWTV\Rest_API\Alexa_Skills;

class Rest_API implements Component, Templater {

	/*
	 * Init
	 */
	public function init(): void {
		new Alexa_Skills();
		new BYQ();
		new Export_JSON();
		new Fresh_JSON();
		new IMDb_JSON();
		new List_JSON();
		new OTD_JSON();
		new Shows_Like_JSON();
		new Stats_JSON();
		new This_Year_JSON();
		new What_Happened_JSON();
		new Whats_On_JSON();
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
			'get_whats_on_show' => array( $this, 'get_whats_on_show' ),
		);
	}

	/**
	 * Get when a show is on
	 *
	 * @param mixed $date
	 * @return array
	 */
	public function get_whats_on_show( $show ) {
		return ( new Whats_On_JSON() )->whats_on_show( $show );
	}
}
