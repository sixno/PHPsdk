<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

require CUSTOM.'config/config.php';
require SYSTEM.'core/core.php';
require SYSTEM.'core/controller.php';

$_protocol = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
$_hostname = $_SERVER['SERVER_NAME'];

$_uri_string = trim($_SERVER['REQUEST_URI'],'/');
$_php_script = trim($_SERVER['SCRIPT_NAME'],'/');

if(strlen($_php_script) <= strlen($_uri_string))
{
	if(substr($_uri_string,0,strlen($_php_script)) == $_php_script) $_uri_string = trim(substr($_uri_string,strlen($_php_script)),'/');
}
else
{
	if(substr($_php_script,0,strlen($_uri_string)) == $_uri_string) $_uri_string = '';
}
if((strpos($_uri_string,'"') !== FALSE) || (strpos($_uri_string,"'") !== FALSE)) exit('There is something bad in your URL!');

$uri_str = preg_replace('/(\/)+/i','/',trim(substr($_uri_string,0,strpos($_uri_string.'?','?')),'/'));
$uri_arr = explode('/',trim($_php_script.'/'.$uri_str,'/'));

$index_file_pos = array_search(conf('index_file','index.php'),$uri_arr);

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

if(!empty($conf['route']) && $uri_str != '')
{
	if(isset($conf['route'][$uri_str]))
	{
		$uri_rel = array_merge(array($uri_arr[0]),explode('/',$conf['route'][$uri_str]));
	}
	else
	{
		foreach($conf['route'] as $key => $val)
		{
			$key = str_replace(':any','.+',str_replace(':num','[0-9]+',$key));

			if(preg_match('#^'.$key.'$#',$uri_str))
			{
				if(strpos($val,'$') !== FALSE AND strpos($key,'(') !== FALSE)
				{
					$val = preg_replace('#^'.$key.'$#',$val,$uri_str);
				}

				$uri_rel = array_merge(array($uri_arr[0]),explode('/',$val));
				break;
			}
		}
	}
}

$folder_controller = '';

if(!empty(conf('folders_controller')) && isset($uri_rel[1]))
{
	if(in_array($uri_rel[1],conf('folders_controller')))
	{
		$folder_controller = $uri_rel[1].'/';
		array_splice($uri_rel,1,1);
	}
}

$d = array();

$controller  = NULL;
$method      = NULL;
$parameter   = array();

$ca_num = count($uri_rel);

if($ca_num > 2)
{
	$controller = strtolower($uri_rel[1]);
	$method     = strtolower($uri_rel[2]);
	$parameter  = array_slice($uri_rel,3);
}
elseif($ca_num > 1)
{
	$controller = strtolower($uri_rel[1]);
	$method     = $conf['_controller_method'];
	$uri_rel[2] = $method;
}
else
{
	$controller = $conf['default_controller'];
	$method     = $conf['_controller_method'];
	$uri_rel[1] = $conf['default_controller'];
	$uri_rel[2] = $conf['_controller_method'];
}

ini_cookie(TRUE);

if((substr($controller,0,1) == '_')) message('It is private which you are trying to access!');

if(!empty(conf('controller_require')))
{
	foreach(conf('controller_require') as $key => $value)
	{
		call_user_func_array('load_file',explode(':',$value));
	}
}

if(conf('controller_autoexe') != '') call_user_func(conf('controller_autoexe'));

$ctrl_file = CUSTOM.'controllers/'.$folder_controller.$controller.'.php';

if(is_file($ctrl_file))
{
	include $ctrl_file;

	$ctrl_name = ucfirst($controller);

	new $ctrl_name();
}
else
{
	if($ca_num < 3 && is_file(load_view($controller,4)))
	{
		unset($uri_rel[2]);

		load_view($controller,2);
	}
	else
	{
		if(substr($method,0,1) == '_') message('The view is private!');

		if(is_file(load_view($controller.'/'.$method,4)))
		{
			load_view($controller.'/'.$method,2);
		}
		else
		{
			message('No such file!');
		}
	}
}

?>