<?php

/**
* For when the API returns an error object.
**/

namespace wpbw_Blipfoto\wpbw_Exceptions;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use wpbw_Blipfoto\wpbw_Exceptions\BaseException;

class wpbw_ApiResponseException extends wpbw_BaseException {

}