<?php
	
	/*
		The folder "adaptive-images" must have the right 755.
		Place the WordPress rewrite rules in the .htaccess from the root directory.
		In some cases "Options +FollowSymlinks" causes a server error. Then just comment out.
			Observed at the provider "Internet24".
	*/
	
	
	$config['resolutions']		= array(320, 480, 600, 768, 960, 1024);
	$config['breakpoints']		= array('default' => 0, 'micro' => 320, 'mini' => 480, 'small' => 600, 'medium' => 768, 'normal' => 1024, 'large' => 1100);
	$config['cache_path']		= "ai-cache"; 
	$config['jpg_quality']		= 80;
	$config['watch_cache']		= TRUE;
	$config['browser_cache']	= 1;
	$config['debug_mode']		= FALSE;
	$config['prevent_cache']	= FALSE;
	$config['sharpen']['status'] = TRUE;
	$config['sharpen']['amount'] = '30'; // max 500
	
	
	/*
		Just use it like that:
		<img src="image.jpg?size=term" />
	*/
	$setup['term']['breakpoints']['default'] = '100%';
	$setup['term']['breakpoints']['normal'] = '960px';
	$setup['term']['ratio'] = '6:1';
	$setup['term']['jpg_quality'] = 95;
	$setup['term']['sharpen']['amount'] = '10';
	
	
?>