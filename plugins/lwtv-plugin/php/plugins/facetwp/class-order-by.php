<?php
/*
 * Description: FacetWP Order By
 *
 * https://facetwp.com/help-center/developers/hooks/querying-hooks/facetwp_facet_orderby/
 */

namespace LWTV\Plugins\FacetWP;

class Order_By {
	/**
	 * Constructor
	 */
	public function __construct() {

		// Filter data before saving it
		add_filter( 'facetwp_facet_orderby', array( $this, 'facetwp_order_by' ), 10, 2 );
	}

	/**
	 * FacetWP Order By
	 *
	 * @param array $orderby
	 * @param array $facet
	 * @return array
	 */
	public function facetwp_order_by( $orderby, $facet ) {
		switch ( $facet['name'] ) {
			case 'show_start_date':
				$orderby = $this->facetwp_order_by_show_start_date( $orderby );
				break;
		}

		return $orderby;
	}

	/**
	 * FacetWP Order by Show Start Date
	 *
	 * @param array $orderby
	 * @return array
	 */
	public function facetwp_order_by_show_start_date( $orderby ) {
		// Switches the default ASC order to DESC in a hierarchical facet set to order by "Display value".
		$orderby = 'f.depth, f.facet_display_value DESC';

		// Make orderby hierarchical for show start date

		return $orderby;
	}
}
