<?php
	error_reporting(0);	
	ini_set('display_errors','0');
	include(dirname(__FILE__).'/../../config/config.inc.php');
	include(dirname(__FILE__).'/../../init.php');
	include(dirname(__FILE__).'/ogoship.php');

	$module = new Ogoship();	
	$module->getAllOrders();	

	die ('Success : OK');
?>