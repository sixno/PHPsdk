<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

if(php_sapi_name() != 'cli' && !defined('STDIN')) exit('The cli can only run under command line!');

require CUSTOM.'config/config.php';
require SYSTEM.'core/core.php';
require SYSTEM.'core/cli.php';

$_protocol = 'php-cli://';
$_hostname = getcwd();

$uri_arr = $_SERVER['argv'];

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

$folder_cli = '';

if(!empty(conf('folders_cli')) && isset($uri_rel[1]))
{
	if(in_array($uri_rel[1],conf('folders_cli')))
	{
		$folder_cli = $uri_rel[1].'/';
		array_splice($uri_rel,1,1);
	}
}

$cli         = NULL;
$method      = NULL;
$parameter   = array();
$file_loaded = array();

$ca_num = count($uri_rel);

if($ca_num > 2)
{
	$cli       = strtolower($uri_rel[1]);
	$method    = strtolower($uri_rel[2]);
	$parameter = array_slice($uri_rel,3);
}
elseif($ca_num > 1)
{
	$cli        = strtolower($uri_rel[1]);
	$method     = $conf['_cli_method'];
	$uri_rel[2] = $method;
}
else
{
	$cli        = $conf['default_cli'];
	$method     = $conf['_cli_method'];
	$uri_rel[1] = $conf['default_cli'];
	$uri_rel[2] = $conf['_cli_method'];
}

if((substr($cli,0,1) == '_')) message('The cli is private!');

if(!empty(conf('cli_require')))
{
	foreach(conf('cli_require') as $key => $value)
	{
		call_user_func_array('load_file',explode(':',$value));
	}
}

$cli_file = CUSTOM.'cli/'.$folder_cli.strtolower($cli).'.php';

if(is_file($cli_file))
{
	include $cli_file;

	new $cli();
}
else
{
	message('The cli is not found!');
}

?>