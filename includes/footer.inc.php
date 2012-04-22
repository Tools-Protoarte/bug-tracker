<?php

if (!function_exists('zing_footers')) {
	function zing_footers($nodisplay='') {
		global $zing_footer,$zing_footers;

		$bail_out = ( ( defined( 'WP_ADMIN' ) && WP_ADMIN == true ) || ( strpos( $_SERVER[ 'PHP_SELF' ], 'wp-admin' ) !== false ) );
		if ( $bail_out ) return $footer;

		if (!$zing_footer) {
			$msg='<center style="margin-top:0px;font-size:x-small">';
			$msg.='Powered by <a href="http://www.zingiri.com">Zingiri</a>';
			if (count($zing_footers) >0) {
				foreach ($zing_footers as $foot) {
					$msg.=', <a href="'.$foot[0].'">'.$foot[1].'</a>';
				}
			}
			$msg.='</center>';
			$zing_footer=true;
			if ($nodisplay===true) return $msg;
			else echo $msg;
		}

	}
}
?>