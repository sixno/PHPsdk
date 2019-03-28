<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

if(php_sapi_name() != 'cli' && !defined('STDIN'))
{
	$_protocol = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
	$_hostname = $_SERVER['SERVER_NAME'];
}
else
{
	$_protocol = 'php-cli://';
	$_hostname = getcwd();
}

require CUSTOM.'config/config.php';
require SYSTEM.'core/core.php';

$_online = FALSE;

if(!empty($conf['online']))
{
	if($conf['online'][0] == '*' || in_array($_hostname,$conf['online'])) $_online = TRUE;
}

if(!empty($conf['native']) && in_array($_hostname,$conf['native']))
{
	$_online = FALSE;
}

ini_cookie();

if(!empty(conf('api_require')))
{
	foreach(conf('api_require') as $key => $value)
	{
		call_user_func_array('load_file',explode(':',$value));
	}
}

class workprocess
{
	public $model = array();

	public function model($model_name)
	{
		if(!isset($this->model[$model_name]))
		{
			$this->model[$model_name] = load_model($model_name);
		}

		return $this->model[$model_name];
	}
}

return new workprocess();

?>