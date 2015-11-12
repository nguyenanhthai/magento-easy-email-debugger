<?php

class Nat_Localmail_Model_System_Config_Source_Mode {
	const SHOW_CONTENT_AND_STOP = 1;
	const LOG_AS_FILE = 2;

	/**
	 * @return array
	 */
	public function toOptionArray() {
		return array(
			$this::SHOW_CONTENT_AND_STOP	=> 'Show the content of email and Stop',
			$this::LOG_AS_FILE						=> 'Log as files in var/email/',
		);
	}
}