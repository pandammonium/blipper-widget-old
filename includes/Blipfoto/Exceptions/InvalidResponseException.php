<?php

/**
* For cases where the API response can't be understood.
**/

namespace wpbw_Blipfoto\wpbw_Exceptions;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use wpbw_Blipfoto\wpbw_Exceptions\BaseException;

class wpbw_InvalidResponseException extends wpbw_BaseException {
	
}