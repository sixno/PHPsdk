<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

class Controller
{
	public $load = NULL;
	public $data = NULL;
	public $view = TRUE;

	public function __construct()
	{
		global $d;
		global $controller;
		global $method;
		global $parameter;

		$this->load = new Loader($this);
		$this->data = &$d;

		if(!empty($_COOKIE['msg']))
		{
			$this->data['msg'] = $_COOKIE['msg'];

			unset_cookie('msg');
		}

		if(!empty($_REQUEST['formdata']))
		{
			$this->data['formdata'] = json_decode(base64_decode(str_replace(array('-','_'),array('+','/'),$_REQUEST['formdata'])),TRUE);
		}

		if($this->_prove_token())
		{
			if(substr($method,0,1) == '_')
			{
				message('The method is private!');
			}
			else
			{
				if(method_exists($this,'__common')) $this->__common();

				if(method_exists($this,$method))
				{
					call_user_func_array(array($this,$method),$parameter);

					if(!empty($_COOKIE['msg']))
					{
						$this->data['msg'] = $_COOKIE['msg'];

						unset_cookie('msg');
					}

					$this->__output();
				}
				else
				{
					$this->view = $controller.'/'.$method;

					if(is_file(load_view($this->view,4)))
					{
						$this->__output();
					}
					else
					{
						message('No such file!');
					}
				}
			}
		}
		else
		{
			$this->_error_token();
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

	public function __output($view = NULL)
	{
		if($view !== NULL) $this->view = $view;

		if($this->view)
		{
			if($this->view === TRUE)
			{
				$uri_1 = uri_rel(1);
				$uri_2 = uri_rel(2);
				
				$this->view = $uri_1.'/'.$uri_2;
			}

			load_view($this->view,2);
		}

		exit;
	}

	public function _prove_token()
	{
		$proved = TRUE;

		if(!empty($_POST))
		{
			if(!empty($_REQUEST['token']))
			{
				if($_REQUEST['token'] == saltedhash($_COOKIE['token'],$_REQUEST['token']))
				{
					if(!is_ajax())
					{
						$_COOKIE['token'] = uniqid();
						setcookie('token',$_COOKIE['token'],0,'/');
					}
				}
				else
				{
					$proved = FALSE;
				}
			}
			else
			{
				$proved = FALSE;
			}
		}

		$this->data['token'] = saltedhash($_COOKIE['token']);

		return $proved;
	}

	public function _error_token()
	{
		$this->_failure('表单令牌过期！');
	}

	public function _back($wait = FALSE,$url = NULL,$backfill = FALSE)
	{
		global $_protocol;
		global $site_name;

		$this->view = FALSE;

		if(empty($url))
		{
			if(empty($_REQUEST['backto']) || ($backfill && !empty($_POST)))
			{
				$url = (strpos(v($_SERVER['HTTP_REFERER'],''),$_protocol.$site_name) === 0) ? $_SERVER['HTTP_REFERER'] : site_url();
			}
			else
			{
				$url = $_REQUEST['backto'];
			}
		}
		else
		{
			$url = site_url($url);
		}

		if(!empty($_POST) && $backfill)
		{
			global $_enter_data;

			if(!isset($_enter_data)) $_enter_data = array();

			$fpos = strpos($url,'?formdata=');

			if($fpos === FALSE) $fpos = strpos($url,'&formdata=');

			if($fpos !== FALSE)
			{
				$epos = strpos($url,'&',$fpos+1);

				if($epos)
				{
					$url = substr_replace($url,'',$fpos,$epos-$fpos);
				}
				else
				{
					$url = substr($url,0,$fpos);
				}
			}

			$url = (strpos($url,'?') === FALSE ? '?' : '&').'formdata='.str_replace(array('+','/'),array('-','_'),trim(base64_encode(json_encode(array_merge($_POST,$_enter_data))),'='));
		}

		if($wait === FALSE)
		{
			header("Location:".$url);
		}
		else
		{
			header("Refresh:".$wait.";url=".$url);
		}

		exit;
	}

	public function _message($message,$url = NULL,$msg_type = 0)
	{
		if(!is_ajax())
		{
			setcookie('msg[type]',$msg_type,0,'/');
			setcookie('msg[content]',$message,0,'/');

			$backfill = ($msg_type == 0 ? TRUE : FALSE);

			$this->_back(FALSE,$url,$backfill);
		}
		else
		{
			$json = array();

			$json['out'] = (string)$msg_type;
			$json['msg'] = (string)$message;

			json_echo($json);
		}

		exit;
	}

	public function _success($message,$url = NULL)
	{
		$this->_message($message,$url,1);
	}

	public function _failure($message,$url = NULL)
	{
		$this->_message($message,$url,0);
	}
}

define('SYSMSG','aci_message');

function aci_message($content)
{
	$html = '';

	if(!is_ajax()) $html .= '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><meta name="viewport" content="width=360, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=0"><title>系统信息</title></head><body style="background-color: #fff;margin: 40px;font: 13px/20px normal Helvetica, Arial, sans-serif;color: #4F5155;">';
	$html .= '<div style="margin: 10px;border: 1px solid #D0D0D0;box-shadow: 0 0 8px #D0D0D0;"><h1 style="color: #444444;background-color: transparent;border-bottom: 1px solid #D0D0D0;font-size: 19px;font-weight: normal;margin: 0 0 14px 0;padding: 14px 15px 10px 15px;">MESSAGE</h1><p style="margin: 12px 15px 12px 15px;">'.$content.'</p></div>';
	if(!is_ajax()) $html .= '</body></html>';

	echo $html;
}

function is_ajax()
{
	if(isset($_SERVER['HTTP_X_REQUESTED_WITH']))
	{
		return strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
	}
	else
	{
		return FALSE;
	}
}

function load_aci($name)
{
	load_file('aci',$name);
}

function load_view($name,$mode = 6)
{
	global $d;
	global $_protocol;
	global $folder_controller;
	static $view_file;

	if(empty($view_file)) header('Content-type: text/html; charset=utf-8');

	if(!isset($view_file[$name]))
	{
		$view_file[$name] = VIEWSD.$folder_controller.(conf('lang_sense') ? conf('lang').'/' : '').$name.'.php';
	}

	switch($mode)
	{
		case 6:
			if(!is_ajax()) include $view_file[$name];

			return TRUE;
			break;
		
		case 5:
			include $view_file[$name];

			return TRUE;
			break;
		
		case 4:
			return $view_file[$name];
			break;
		
		case 3:
			$is_echo = TRUE;
			$is_ajax = TRUE;
			break;
		
		case 2:
			$is_echo = TRUE;
			$is_ajax = FALSE;
			break;
		
		case 1:
			$is_echo = FALSE;
			$is_ajax = TRUE;
			break;
		
		default:
			$is_echo = FALSE;
			$is_ajax = FALSE;
			break;
	}

	ob_start();

	if(!$is_ajax || !is_ajax())
	{
		include $view_file[$name];
	}

	$content = ob_get_contents();

	ob_end_clean();

	if($is_echo)
	{
		if($_protocol != 'https://')
		{
			echo $content;
		}
		else
		{
			echo str_replace('http://','https://',$content);
		}
	}
	else
	{
		if($_protocol != 'https://')
		{
			return $content;
		}
		else
		{
			return str_replace('http://','https://',$content);
		}
	}
}

function base_url($uri = '/')
{
	global $_protocol;
	global $_hostname;
	static $_base_url;

	if(!isset($_base_url))
	{
		$_base_url = $_protocol.$_hostname;
	}

	switch($uri)
	{
		case '':
			return $_base_url;
			break;
		
		case '/':
			return $_base_url.'/';
			break;
		
		default:
			if(substr($uri,0,7) != 'http://' && substr($uri,0,7) != 'https://')
			{
				return $_base_url.'/'.$uri;
			}
			else
			{
				return $uri;
			}
			break;
	}
}

function site_url($uri = '')
{
	global $_online;

	static $uri_prefix;
	static $index_file;
	static $index_hide;

	if(!isset($uri_prefix)) $uri_prefix = base_url('');
	if(!isset($index_file)) $index_file = conf('index_file');
	if(!isset($index_hide)) $index_hide = !empty($_online) ? conf('index_hide') : FALSE;

	if($index_hide)
	{
		return $uri_prefix.supstr('/',$uri);
	}
	else
	{
		return $uri_prefix.'/'.$index_file.supstr('/',$uri);
	}
}

function redirect($url = NULL,$wait = FALSE)
{
	if((strpos($url,'http://') !== 0) && (strpos($url,'https://') !== 0)) $url = site_url($url);

	if($wait === FALSE)
	{
		header("Location:".$url);
	}
	else
	{
		header("Refresh:".$wait.";url=".$url);
	}

	exit;
}

function backto()
{
	$backto = trim(v($_REQUEST['backto'],v($_SERVER['HTTP_REFERER'],'')),'/');

	// if($backto == site_url() || $backto == site_url(uri_string())) $backto = '';

	return $backto;
}

function formdata($key,$def = '')
{
	global $d;

	return isset($d['formdata'][$key]) ? $d['formdata'][$key] : $def;
}

?>