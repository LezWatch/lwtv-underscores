<?php
/**
 * Name: Transient Error
 * Description: A retryable failure talking to Google (network error, 429 or 5xx).
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Transient_Error extends \RuntimeException {}
