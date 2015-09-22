<?php

/**
* The base class for all Blipfoto exceptions.
**/

namespace wpbw_Blipfoto\wpbw_Exceptions;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use \ErrorException;

class wpbw_BaseException extends ErrorException {
	
}