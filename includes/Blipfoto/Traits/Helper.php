<?php

namespace wpbw_Blipfoto\wpbw_Traits;

trait wpbw_Helper {

	/**
	 * Get and optionally set the value for a property.
	 *
	 * @param string $property
	 * @param array $args
	 */
	public function getset($property, $args) {
		if (count($args)) {
			$this->$property = $args[0];
		}
		return $this->$property;
	}

}