<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

class Cli
{
	public function __construct()
	{
		if(!$this->_is_cli_request()) message('can`t run without cli!');

		global $controller;
		global $method;
		global $parameter;

		$this->load = new Loader($this);

		if(substr($method,0,1) == '_')
		{
			$this->_echo('The method is private!');
		}
		else
		{
			if(method_exists($this,$method))
			{
				call_user_func_array(array($this,$method),$parameter);
			}
			else
			{
				$this->_echo('no such method:'.$method.'!');
			}
		}
	}

	public function __get($property)
	{
		switch($property)
		{
			case 'db':
				$this->load->database();
				break;
			
			case 'redis':
				$this->load->redis();
				break;
			
			default:
				$this->load->model($property,'core');
				break;
		}

		return $this->$property;
	}

	public function _is_cli_request()
	{
		return (php_sapi_name() === 'cli' OR defined('STDIN'));
	}

	public function _echo($str = '',$isstop = TRUE,$prefix = '')
	{
		if(!is_array($str) && !is_object($str))
		{
			echo (is_string($str) ? $str : var_export($str,TRUE))."\r\n";
		}
		else
		{
			foreach($str as $key => $value)
			{
				if(!is_array($value) && !is_object($value))
				{
					$this->_echo($prefix.$key.': '.(is_string($value) ? $value : var_export($value,TRUE)),FALSE);
				}
				else
				{
					$this->_echo($prefix.$key.': ',FALSE);

					$this->_echo($value,FALSE,$prefix.'  ');
				}
			}
		}

		if($isstop) exit;
	}

	public function _echo_e($str = '',$isstop = TRUE,$prefix = '')
	{
		if(!is_array($str) && !is_object($str))
		{
			print exec('echo -ne \''.(is_string($str) ? $str : var_export($str,TRUE)).'\'')."\r\n";
		}
		else
		{
			foreach($str as $key => $value)
			{
				if(!is_array($value) && !is_object($value))
				{
					$this->_echo_e($prefix.'\\033[1;35m'.$key.':\\033[0m '.(is_string($value) ? $value : var_export($value,TRUE)),FALSE);
				}
				else
				{
					$this->_echo_e($prefix.'\\033[1;35m'.$key.':\\033[0m ',FALSE);

					$this->_echo_e($value,FALSE,$prefix.'  ');
				}
			}
		}

		if($isstop) exit;
	}

	public function _echo_t($str = '',$mode = '110',$prefix = '')
	{
		list($isdie,$color,$state) = str_split(str_pad($mode,3,0,STR_PAD_RIGHT));

		$isstop = $isdie == '1' ? TRUE : FALSE;
		$colour = $color == '1' ? TRUE : FALSE;
		$status = $state == '1' ? '\\033[1;32m' : ($state == '2' ? '\\033[1;31m' : '\\033[1;33m');

		if(!is_array($str) && !is_object($str))
		{
			if($colour)
			{
				print exec('echo -ne \''.$status.'['.date('Y-m-d H:i:s').']\\033[0m: '.(is_string($str) ? $str : var_export($str,TRUE)).'\'')."\r\n";
			}
			else
			{
				echo '['.date('Y-m-d H:i:s').']: '.(is_string($str) ? $str : var_export($str,TRUE))."\r\n";
			}
		}
		else
		{
			foreach($str as $key => $value)
			{
				if(!is_array($value) && !is_object($value))
				{
					$this->_echo_t($prefix.($colour ? '\\033[1;35m' : '').$key.($colour ? ':\\033[0m ' : ': ').(is_string($value) ? $value : var_export($value,TRUE)),'0'.$color.$state);
				}
				else
				{
					$this->_echo_t($prefix.($colour ? '\\033[1;35m' : '').$key.($colour ? ':\\033[0m ' : ': '),'0'.$color.$state);

					$this->_echo_t($value,'0'.$color.$state,$prefix.'  ');
				}
			}
		}

		if($isstop) exit;
	}

	public function _log($file,$content = NULL,$mode = '11',$is_stop = FALSE)
	{
		return message_log($file,$content,$mode,$is_stop);
	}
}

function load_cli($name)
{
	load_file('cli',$name);
}

?>