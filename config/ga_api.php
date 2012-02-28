<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$config['profile_id']	= '3432423424'; // GA profile id
$config['email']		= 'example@gmail.com'; // GA Account mail
$config['password']		= 'password'; // GA Account password

$config['cache_data']	= false; // request will be cached
$config['cache_folder']	= '/cache'; // read/write
$config['clear_cache']	= array('date', '1 day ago'); // keep files 1 day
	
$config['debug']		= false; // print request url if true