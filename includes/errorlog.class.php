<?php
/**
 * Output activation messages to log
 * @param $stringData
 * @return unknown_type
 */

class zErrorLog {
	var $debug=false;
	
	function zErrorLog($clear=false,$debug=true) {
		if ($clear) $this->clear();
		$this->debug=$debug;
	}

	function log($severity, $msg, $filename="", $linenum=0) {
		if (is_array($msg)) $msg=print_r($msg,true);
		$toprint=date('Y-m-d h:i:s').' '.$msg.' ('.$filename.'-'.$linenum.')';
		//file logging disabled
		if ($this->debug) echo $toprint.'<br />';
	}

	function msg($msg) {
		$this->log(0,$msg);
	}
	
	function clear() {
		//file logging disabled
	}
}
?>