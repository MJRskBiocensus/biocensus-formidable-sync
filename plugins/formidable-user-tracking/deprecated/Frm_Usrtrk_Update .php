<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class Frm_Usrtrk_Update {
	public function __construct() {
		_deprecated_function( __METHOD__, '2.0' );
		new FrmUsrTrkUpdate();
	}
}
