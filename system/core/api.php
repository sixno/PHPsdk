<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

class Api
{
	public $load = NULL;
	public $line = '';
	public $json = array('out' => '','msg' => '','data' => '','code' => '','line' => '');

	public $list_length = 0;
	public $list_offset = 0;

	public function __construct()
	{
		global $controller;
		global $method;
		global $parameter;

		$this->load = new Loader($this);

		$this->__origin();

		if(substr($method,0,1) == '_')
		{
			$this->_failure('The method is private!','9999');
		}
		else
		{
			if(method_exists($this,$method))
			{
				if(method_exists($this,'__common')) $this->__common();

				$result = call_user_func_array(array($this,$method),$parameter);

				if($result !== NULL) $this->__output($result);
			}
			else
			{
				$this->_failure('No such method!','9999');
			}
		}
	}

	public function __get($property)
	{
		switch($property)
		{
			case 'mysql':
				$this->load->mysql();
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

	public function __origin()
	{
		global $_protocol;
		global $_hostname;

		$allow_origin = conf('_api_origin');

		if(!empty($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] != $_protocol.$_hostname && !empty($allow_origin))
		{
			if(strpos($_SERVER['HTTP_ORIGIN'],'://') !== FALSE)
			{
				list($scheme,$origin) = explode('://',$_SERVER['HTTP_ORIGIN']);

				$scheme .= '://';
			}
			else
			{
				$scheme = '';
				$origin = $_SERVER['HTTP_ORIGIN'];
			}

			foreach($allow_origin as $_allow)
			{
				if($origin == $_allow || $scheme == $_allow || substr($origin,-1 - strlen($_allow)) == '.'.$_allow)
				{
					header('Access-Control-Allow-Origin: '.$scheme.$origin);
					header('Access-Control-Allow-Methods: OPTIONS,GET,POST');
					header('Access-Control-Allow-Credentials: true');
					header('Access-Control-Allow-Headers:x-requested-with,content-type,if-modified-since,no-cookie,'.conf('sess_with'));
					header('Access-Control-Expose-Headers: '.conf('sess_with'));

					if(http_method() == 'options')
					{
						header('Access-Control-Max-Age: 86400');

						exit;
					}

					break;
				}
			}
		}
	}

	public function __output($out = '',$msg = '',$data = '',$code = '')
	{
		if(!is_array($out))
		{
			if($out !== '') $this->json['out'] = (string)$out;
			if($msg !== '') $this->json['msg'] = (string)$msg;

			if($data !== '') $this->json['data'] = $data;
			if($code !== '') $this->json['code'] = (string)$code;
		}
		else
		{
			$this->json = array_merge($this->json,$out);
		}

		json_echo($this->json);
	}

	public function _success($msg = '',$data = '',$code = '')
	{
		if(!is_array($data))
		{
			$code = $data;
			$data = '';
		}

		if(is_array($msg))
		{
			$data = $msg;
			$msg  = '';
		}

		$this->__output('1',$msg,$data,$code);
	}

	public function _failure($msg = '',$data = '',$code = '')
	{
		if(!is_array($data))
		{
			$code = $data;
			$data = '';
		}

		if(is_array($msg))
		{
			$data = $msg;
			$msg  = '';
		}

		$this->__output('0',$msg,$data,$code);
	}
}

define('SYSMSG','api_message');

function api_message($content)
{
	json_echo(['out' => '0','msg' => $content,'data' => '','code' => '9999','line' => '']);
}

function load_api($name)
{
	load_file('api',$name);
}

?>