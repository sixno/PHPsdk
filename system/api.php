<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

require CUSTOM.'config/config.php';
require SYSTEM.'core/core.php';
require SYSTEM.'core/api.php';

$_protocol = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
$_hostname = $_SERVER['SERVER_NAME'];

$_uri_string = trim($_SERVER['REQUEST_URI'],'/');
$_php_script = trim($_SERVER['SCRIPT_NAME'],'/');

if(strpos($_uri_string,$_php_script) === 0) $_uri_string = trim(substr($_uri_string,strlen($_php_script)),'/');
if((strpos($_uri_string,'"') !== FALSE) || (strpos($_uri_string,"'") !== FALSE)) exit('There is something bad in your URL!');

$uri_str = preg_replace('/(\/)+/i','/',trim(substr($_uri_string,0,strpos($_uri_string.'?','?')),'/'));
$uri_arr = explode('/',trim($_php_script.'/'.$uri_str,'/'));

$index_file_pos = array_search(conf('api_gateway','api.php'),$uri_arr);

if($index_file_pos > 0)
{
	$_hostname .= '/'.implode('/',array_splice($uri_arr,0,$index_file_pos));
}

$uri_rel = $uri_arr;

$_online = FALSE;

if(!empty($conf['online']))
{
	if($conf['online'][0] == '*' || in_array($_hostname,$conf['online'])) $_online = TRUE;
}

if(!empty($conf['native']) && in_array($_hostname,$conf['native']))
{
	$_online = FALSE;
}

$folder_api = '';

if(!empty(conf('folders_api')) && isset($uri_rel[1]))
{
	if(in_array($uri_rel[1],conf('folders_api')))
	{
		$folder_api = $uri_rel[1].'/';
		array_splice($uri_rel,1,1);
	}
}

$api         = NULL;
$method      = NULL;
$parameter   = array();
$file_loaded = array();

$ca_num = count($uri_rel);

if($ca_num > 2)
{
	$api       = ucfirst(strtolower($uri_rel[1]));
	$method    = strtolower($uri_rel[2]);
	$parameter = array_slice($uri_rel,3);
}
elseif($ca_num > 1)
{
	$api        = ucfirst(strtolower($uri_rel[1]));
	$method     = $conf['_api_method'];
	$uri_rel[2] = $method;
}
else
{
	$api        = ucfirst($conf['default_api']);
	$method     = $conf['_api_method'];
	$uri_rel[1] = $conf['default_api'];
	$uri_rel[2] = $conf['_api_method'];
}

if((substr($api,0,1) == '_')) message('The api is private!');

ini_cookie();

if(!empty(conf('api_require')))
{
	foreach(conf('api_require') as $key => $value)
	{
		call_user_func_array('load_file',explode(':',$value));
	}
}

$api_file = CUSTOM.'api/'.$folder_api.strtolower($api).'.php';

if(is_file($api_file))
{
	include $api_file;

	new $api();
}
else
{
	message('The api is not found!');
}

?>