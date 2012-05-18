<?php
	
	$config['resolutions']		= array(320, 480, 600, 768, 960, 1024, 1400);
	$config['breakpoints']		= array('default' => 0, 'micro' => 320, 'mini' => 480, 'small' => 600, 'medium' => 768, 'normal' => 1024, 'large' => 1100);
	$config['cache_path']		= "ai-cache"; 
	$config['jpg_quality']		= 75;
	$config['watch_cache']		= TRUE;
	$config['browser_cache']	= 60*60*24*7;
	$config['debug_mode']		= TRUE;
	$config['prevent_cache']	= TRUE;
	$config['sharpen']['status'] = FALSE;
	$config['sharpen']['amount'] = '40'; // max 500
	
	//$setup['test']['breackpoints']['default'] = '100%';
	//$setup['test']['breackpoints']['small'] = '200px';
	//$setup['test']['ratio'] = '4:3';
	
?>