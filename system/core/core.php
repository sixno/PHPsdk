<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

define('FILE_READ_MODE',  0644);
define('FILE_WRITE_MODE', 0666);
define('DIR_READ_MODE',   0755);
define('DIR_WRITE_MODE',  0777);

define('FOPEN_READ',							'rb');
define('FOPEN_READ_WRITE',						'r+b');
define('FOPEN_WRITE_CREATE_DESTRUCTIVE',		'wb'); // truncates existing file data, use with care
define('FOPEN_READ_WRITE_CREATE_DESTRUCTIVE',	'w+b'); // truncates existing file data, use with care
define('FOPEN_WRITE_CREATE',					'ab');
define('FOPEN_READ_WRITE_CREATE',				'a+b');
define('FOPEN_WRITE_CREATE_STRICT',				'xb');
define('FOPEN_READ_WRITE_CREATE_STRICT',		'x+b');

function is_php($version = '5.0.0')
{
	static $_is_php;

	$version = (string)$version;

	if(!isset($_is_php[$version]))
	{
		$_is_php[$version] = (version_compare(PHP_VERSION, $version) < 0) ? FALSE : TRUE;
	}

	return $_is_php[$version];
}

function is_really_writable($file)
{
	// If we're on a Unix server with safe_mode off we call is_writable
	if(DIRECTORY_SEPARATOR == '/' AND ini_get('safe_mode') == FALSE)
	{
		return is_writable($file);
	}

	// For windows servers and safe_mode "on" installations we'll actually
	// write a file then read it.  Bah...
	if(is_dir($file))
	{
		$file = rtrim($file, '/').'/'.md5(mt_rand(1,100).mt_rand(1,100));

		if(($fp = fopen($file, FOPEN_WRITE_CREATE)) === FALSE)
		{
			return FALSE;
		}

		fclose($fp);
		chmod($file, DIR_WRITE_MODE);
		unlink($file);
		return TRUE;
	}
	elseif(!is_file($file) OR ($fp = fopen($file, FOPEN_WRITE_CREATE)) === FALSE)
	{
		return FALSE;
	}

	fclose($fp);
	return TRUE;
}

class Loader
{
	public function __construct(&$obj)
	{
		$this->obj = $obj;
	}

	public function funx($name)
	{
		return load_funx($name);
	}

	public function func($name)
	{
		return load_func($name);
	}

	public function class($class,$alias = '',$items = array())
	{
		if(is_array($alias))
		{
			$items = $alias;
			$alias = '';
		}

		$_name = empty($alias) ? $class : $alias;

		if(!isset($this->obj->$_name))
		{
			$this->obj->$_name = NULL;

			$this->obj->$_name = &load_class($class,$items);
		}
	}

	public function model($model,$alias = '',$items = array())
	{
		if(is_array($alias))
		{
			$items = $alias;
			$alias = '';
		}

		if(empty($alias))
		{
			$_name = $model;
		}
		else
		{
			if($alias != 'core')
			{
				$_name = $alias;
			}
			else
			{
				$_name = $model;

				$items = 'core';
			}
		}

		if(!isset($this->obj->$_name))
		{
			$this->obj->$_name = NULL;

			$this->obj->$_name = &load_model($model,$items);
		}
	}

	public function mysql($config = array())
	{
		if(!isset($this->obj->mysql))
		{
			$this->obj->mysql = NULL;

			$this->obj->mysql = &load_mysql($config);
		}
	}

	public function redis($config = array())
	{
		if(!isset($this->obj->redis))
		{
			$this->obj->redis = NULL;

			$this->obj->redis = &load_redis($config);
		}
	}
}

class unexpect
{
	public $default = NULL;

	public function __construct($default = FALSE)
	{
		$this->default = $default;
	}

	public function __get($property)
	{
		return $this;
	}

	public function __call($method,$parameter)
	{
		return $this->default;
	}
}

function message($content)
{
	if(!defined('SYSMSG'))
	{
		echo 'System Message: '.$content."\r\n";
	}
	else
	{
		call_user_func(SYSMSG,$content);
	}

	exit;
}

function message_log($file,$content = NULL,$mode = '11',$is_stop = FALSE)
{
	static $log_dir;

	if(!isset($log_dir)) $log_dir = conf('log_dir','./logs');

	$file = $log_dir.'/'.$file;
	$dirn = dirname($file);

	if(!is_dir($dirn)) mkdir($dirn,0777,TRUE);

	if($content !== NULL)
	{
		list($write_mode,$write_time) = str_split(str_pad($mode,2,0,STR_PAD_RIGHT));

		$write_mode = $write_mode == '1' ? FILE_APPEND : NULL;
		$write_time = $write_time == '1' ? TRUE : FALSE;

		if(!is_string($content)) $content = var_export($content,TRUE);

		if($write_time) $content = "\r\n[".date('Y-m-d H:i:s')."]\r\n".$content."\r\n";

		file_put_contents($file,$content,$write_mode);

		if($is_stop) exit;
	}
	else
	{
		touch($file);

		$content = trim(file_get_contents($file));

		return $content;
	}
}

function json_echo($data)
{
	header('Cache-Control: no-cache');
	header('Pragma: no-cache');
	header('Expires: 0');
	header('Content-type: application/json; charset=utf-8');

	echo json_encode($data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	exit;
}

function load_file($where,$name = '')
{
	static $file_loaded;

	$fkey = $name != '' ? '~'.$where.':'.$name : $where;

	if(!isset($file_loaded[$fkey]))
	{
		$file_loaded[$fkey] = TRUE;

		$path = '';

		switch($where)
		{
			case 'aci':
				$path = CUSTOM.'controllers/'.$name.'.php';
				break;
			
			case 'api':
				$path = CUSTOM.'api/'.$name.'.php';
				break;
			
			case 'cli':
				$path = CUSTOM.'cli/'.$name.'.php';
				break;
			
			case 'core':
				$path = SYSTEM.'core/'.$name.'.php';
				break;
			
			case 'funx':
				$path = SYSTEM.'functions/'.$name.'.php';
				break;
			
			case 'conf':
				global $conf;

				$path = CUSTOM.'config/'.$name.'.php';
				break;
			
			case 'lang':
				global $lang;

				$path = CUSTOM.'language/'.conf('lang').'/'.$name.'.php';
				break;
			
			case 'func':
				$path = CUSTOM.'functions/'.$name.'.php';
				break;
			
			case 'class':
				$path = SYSTEM.'classes/'.$name.'.php';
				break;
			
			case 'model':
				$path = CUSTOM.'models/'.$name.'.php';

				if(!is_file($path)) $file_loaded[$fkey] = FALSE;
				break;
			
			default:
				$path = $where;
				break;
		}

		if($file_loaded[$fkey]) include $path;
	}

	return $file_loaded[$fkey];
}

function load_core($name)
{
	return load_file('core',$name);
}

function load_funx($name)
{
	return load_file('funx',$name);
}

function load_conf($name)
{
	return load_file('conf',$name);
}

function load_lang($name)
{
	return load_file('lang',$name);
}

function load_func($name)
{
	return load_file('func',$name);
}

function set_conf($item,$value)
{
	global $conf;

	if(strpos($item,'.') === FALSE)
	{
		$conf[$item] = $value;
	}
	else
	{
		$cop = &$conf;

		foreach(explode('.',$item) as $ckey)
		{
			if(!isset($cop[$ckey])) $cop[$ckey] = NULL;

			$cop = &$cop[$ckey];
		}

		$cop = $value;
	}

	return TRUE;
}

function set_lang($item,$value)
{
	global $lang;

	if(strpos($item,'.') === FALSE)
	{
		$lang[$item] = $value;
	}
	else
	{
		$lop = &$lang;

		foreach(explode('.',$item) as $lkey)
		{
			if(!isset($lop[$lkey])) $lop[$lkey] = NULL;

			$lop = &$lop[$lkey];
		}

		$lop = $value;
	}

	return TRUE;
}

function conf($item = '',$def = FALSE)
{
	global $conf;

	if(!empty($item))
	{
		if(strpos($item,'.') === FALSE)
		{
			return isset($conf[$item]) ? $conf[$item] : $def;
		}
		else
		{
			$cop = &$conf;

			foreach(explode('.',$item) as $ckey)
			{
				if(isset($cop[$ckey]))
				{
					$cop = &$cop[$ckey];
				}
				else
				{
					return $def;
				}
			}

			return $cop;
		}
	}
	else
	{
		return $conf;
	}
}

function lang($item = '',$def = FALSE)
{
	global $lang;

	if(!empty($item))
	{
		if(strpos($item,'.') === FALSE)
		{
			return isset($lang[$item]) ? $lang[$item] : $def;
		}
		else
		{
			$lop = &$lang;

			foreach(explode('.',$item) as $lkey)
			{
				if(isset($lop[$lkey]))
				{
					$lop = &$lop[$lkey];
				}
				else
				{
					if($def === FALSE)
					{
						return str_replace('_',' ',substr($item,strrpos($item,'.') + 1));
					}
					else
					{
						return $def;
					}
				}
			}

			return $lop;
		}
	}
	else
	{
		return $lang;
	}
}

function &load_class($name,$config = array())
{
	static $class;

	$id = $name.':'.(empty($config) ? 'default' : md5(json_encode($config)));

	if(!isset($class[$id]))
	{
		load_file('class',$name);

		$class_name = ucfirst($name);

		$class[$id] = new $class_name($config);
	}

	return $class[$id];
}

function &load_model($name,$config = array(),$core_config = array())
{
	static $model;

	if($config != 'core')
	{
		$id = $name.':'.(empty($config) ? 'default' : md5(json_encode($config)));
	}
	else
	{
		if(empty($core_config))
		{
			if(!isset($model[$name.':default']))
			{
				$id = $name.':core';
			}
			else
			{
				return $model[$name.':default'];
			}
		}
		else
		{
			$id = $name.':core:'.md5(json_encode($core_config));
		}
	}

	if(!isset($model[$id]))
	{
		load_file('core','model');

		if($config != 'core')
		{
			if(load_file('model',$name))
			{
				$model_name = ucfirst($name);

				$model[$id] = new $model_name();

				if(!empty($config) && $model[$id])
				{
					foreach($config as $key => $value)
					{
						$model[$id]->_conf[$key] = $value;
					}
				}
			}
			else
			{
				if(!isset($model[$name.':core']))
				{
					$model[$id] = new Model($name);
				}
				else
				{
					return $model[$name.':core'];
				}
			}
		}
		else
		{
			$model[$id] = new Model($name,$core_config);
		}
	}

	return $model[$id];
}

function &load_mysql($config = array())
{
	global $_online;

	static $mysql;

	$id = empty($config) ? 'primary' : md5(json_encode($config));

	if(!isset($mysql[$id]))
	{
		$conf = !empty($_online) ? conf('mysql.online',array()) : conf('mysql.native',array());

		if(!empty($config)) $conf = array_merge($conf,$config);

		if(!empty($conf))
		{
			$mysql[$id] = @ new mysqli($conf['hostname'],$conf['username'],$conf['password'],$conf['database'],$conf['hostport']);

			if(!$mysql[$id]->connect_error)
			{
				if(version_compare($mysql[$id]->get_server_info(),'5.0.7','>='))
				{
					$mysql[$id]->set_charset($conf['char_set']);
				}
				else
				{
					$mysql[$id]->query('SET NAMES \''.$conf['char_set'].'\' COLLATE \''.$conf['dbcollat'].'\'');
				}
			}
			else
			{
				message('mysql: Connect Error ('.$mysql[$id]->connect_errno.') - '.$mysql[$id]->connect_error);
			}
		}
		else
		{
			message('mysql: no config data.');
		}
	}

	return $mysql[$id];
}

function &load_redis($config = array())
{
	global $_online;

	static $redis;

	$id = empty($config) ? 'primary' : md5(json_encode($config));

	if(!isset($redis[$id]))
	{
		$conf = !empty($_online) ? conf('redis.online',array()) : conf('redis.native',array());

		if(!empty($config)) $conf = array_merge($conf,$config);

		$redis[$id] = new Redis();

		if(!empty($conf))
		{
			if($redis[$id]->connect($conf['host'],$conf['port'],3))
			{
				$redis[$id]->select($conf['dbno']);
			}
			else
			{
				$redis[$id] = new unexpect(FALSE);
			}
		}
		else
		{
			$redis[$id] = new unexpect(FALSE);
		}
	}

	return $redis[$id];
}

function wday($n = 0)
{
	$c = (int)date('w');

	if($n == 0)
	{
		return $c;
	}
	else
	{
		$p = $c + $n;

		if($p >= 0 && $p <= 6)
		{
			return $p;
		}
		else
		{
			if($p < 0)
			{
				return 7 + $p;
			}
			else
			{
				return 7 - $p;
			}
		}
	}
}

function ini_cookie($ini_data = FALSE)
{
	if(http_header('no-cookie')) return FALSE;

	if(empty($_COOKIE['PHPSESSID']))
	{
		$_COOKIE['PHPSESSID'] = md5(remote_ip().uniqid(mt_rand(0,999999999),TRUE));
		setcookie('PHPSESSID',$_COOKIE['PHPSESSID'],0,'/',(conf('sess_host') ? root_host() : NULL));

		$sess_name = conf('sess_name');

		if(!empty($_COOKIE[$sess_name]))
		{
			$sess_str = str_decode(substr($_COOKIE[$sess_name],0,-32));
			$sess_arr = explode(',',$sess_str);

			if(count($sess_arr) == 3)
			{
				global $session;

				$sess_host = conf('sess_host') ? root_host() : NULL;

				$session = array('id' => $sess_arr[0],'line' => $sess_arr[1],'reme' => $sess_arr[2]);

				set_cookie($sess_name,str_encode($sess_str).md5($_COOKIE['PHPSESSID'].':'.$sess_arr[1]),'/',$sess_arr[2] == '1' ? conf('sess_time') : 0,$sess_host);
			}
			else
			{
				del_session();
			}
		}
	}

	if(empty($_COOKIE['token']))
	{
		$_COOKIE['token'] = uniqid();
		setcookie('token',$_COOKIE['token'],0,'/');
	}

	if($ini_data)
	{
		global $d;

		if(empty($d['token'])) $d['token'] = saltedhash($_COOKIE['token']);
	}

	return TRUE;
}

function saltedhash($string,$salt = NULL,$saltLength = 8)
{
	if($salt == NULL)
	{
		$salt = substr(md5(time()),0,$saltLength);
	}
	else
	{
		$salt = substr($salt,0,$saltLength);
	}

	return $salt.sha1($salt.$string);
}

function uri_string()
{
	global $_uri_string;

	return $_uri_string;
}

function uri_str()
{
	global $uri_str;

	return $uri_str;
}

function uri_arr($n = 0,$def = FALSE)
{
	global $uri_arr;

	if(isset($uri_arr[$n]))
	{
		return $uri_arr[$n];
	}
	else
	{
		return $def;
	}
}

function uri_rel($n = 0,$def = FALSE)
{
	global $uri_rel;

	if(isset($uri_rel[$n]))
	{
		return $uri_rel[$n];
	}
	else
	{
		return $def;
	}
}

function uri_rel_str()
{
	global $uri_rel;
	static $uri_rel_str;

	if(isset($uri_rel_str))
	{
		return $uri_rel_str;
	}
	else
	{
		$uri_rel_str =  implode('/',array_slice($uri_rel,1));

		return $uri_rel_str;
	}
}

function uri_want($n,$want)
{
	global $uri_rel;

	$uri_str = '';

	if(!is_array($n))
	{
		$uri_str = (isset($uri_rel[$n]) ? $uri_rel[$n] : '');
	}
	else
	{
		foreach($n as $m)
		{
			if(isset($uri_rel[$m]))
			{
				$uri_str .= '/'.$uri_rel[$m];
			}
		}

		if(!empty($uri_str)) $uri_str = substr($uri_str,1);
	}

	if(!is_array($want))
	{
		return $uri_str == $want;
	}
	else
	{
		return in_array($uri_str,$want);
	}
}

function usec($microtime = NULL,$date_format = '')
{
	if($microtime === NULL)
	{
		$mt = explode(' ',microtime());

		return $mt[1].sprintf('%06d',$mt[0] * 1000000);
	}
	else
	{
		$time = substr($microtime,0,-6);

		if(empty($date_format))
		{
			return $time;
		}
		else
		{
			return date($date_format,$time);
		}
	}
}

function root_host()
{
	static $root_host;

	if(!isset($root_host))
	{
		$root_host = conf('root_host','');

		if(!empty($root_host))
		{
			if(is_array($root_host))
			{
				if(isset($_SERVER['SERVER_NAME']))
				{
					foreach($root_host as $host)
					{
						if(strpos($_SERVER['SERVER_NAME'],$host) !== FALSE)
						{
							$root_host = $host;

							return $root_host;
						}
					}

					$root_host = '';
				}
				else
				{
					$root_host = '';
				}
			}
		}
		else
		{
			if(isset($_SERVER['SERVER_NAME']))
			{
				$root_host = implode('.',array_slice(explode('.',$_SERVER['SERVER_NAME']),-2));
			}
		}
	}

	return $root_host;
}

function res_url($uri)
{
	global $_protocol;
	global $_online;

	if(substr($uri,0,7) != 'http://' && substr($uri,0,7) != 'https://')
	{
		$res_host = conf('res_host');

		if($_online)
		{
			return $_protocol.reset($res_host).'/'.$uri;
		}
		else
		{
			return $_protocol.end($res_host).'/'.$uri;
		}
	}
	else
	{
		return $uri;
	}
}

function v(&$var,$def = NULL)
{
	if(isset($var))
	{
		return $var;
	}
	else
	{
		return $def;
	}
}

function vo(&$var,$def = NULL)
{
	if(!empty($var))
	{
		return $var;
	}
	elseif(isset($var))
	{
		return (strval($var) === '0') ? $var : $def;
	}
	else
	{
		return $def;
	}
}

function vzo(&$var,$def = NULL)
{
	if(!empty($var))
	{
		return $var;
	}
	else
	{
		return $def;
	}
}

function bro($str)
{
	return str_replace(["\r\n","\n","\r"],'<br>',$str);
}

function base64_encrypt($str,$key)
{
	if(!$key) return '';

	$key = base64_bin($key);

	$encrypt_key = md5(mt_rand(0,99999));

	$encrypt_key = base64_bin($encrypt_key);

	$str = base64_encode($str);

	$str = base64_bin($str);

	$ctr = 0;
	$tmp = '';

	for($i = 0;$i < strlen($str);$i++)
	{
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= (int)$encrypt_key[$ctr].($str[$i] ^ (int)$encrypt_key[$ctr++]);
	}

	$tmp = base64_key($tmp,$key);

	return base64_bin($tmp,FALSE);
}

function base64_decrypt($str,$key)
{
	if(!$key) return '';

	$key = base64_bin($key);

	$str = base64_bin($str);

	$str = base64_key($str,$key);

	$len = strlen($str);

	if($len%2 != 0) return '';

	$tmp = '';

	for($i = 0;$i < $len;$i++)
	{
		$tmp .= (int)$str[$i] ^ (int)$str[++$i];
	}

	$tmp = base64_bin($tmp,FALSE);

	return base64_decode($tmp);
}

function base64_key($str,$encrypt_key)
{
	$ctr = 0;
	$tmp = '';

	for($i = 0; $i < strlen($str); $i++)
	{
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= (int)$str[$i] ^ (int)$encrypt_key[$ctr++];
	}

	return $tmp;
}

function base64_bin($str,$encode = TRUE)
{
	$char_table = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

	static $char_use_1;
	static $char_use_2;

	$tmp = '';

	if($encode)
	{
		if(!isset($char_use_1))
		{
			$char_use_1 = [];

			for($i = 0;$i < strlen($char_table);$i++)
			{
				$char_use_1[$char_table[$i]] = substr('000000'.decbin($i),-6);
			}
		}

		for($i = 0;$i < strlen($str);$i++)
		{
			if($str[$i] == '=') break;

			$tmp .= $char_use_1[$str[$i]];
		}

		return $tmp;
	}
	else
	{
		if(!isset($char_use_2))
		{
			$char_use_2 = [];

			for($i = 0;$i < strlen($char_table);$i++)
			{
				$char_use_2[substr('000000'.decbin($i),-6)] = $char_table[$i];
			}
		}

		for($i = 0;$i < strlen($str);$i += 6)
		{
			$tmp .= $char_use_2[substr($str,$i,6)];
		}

		$len = strlen($tmp) % 4;

		if($len)
		{
			for($i = 0;$i < 4-$len;$i++)
			{
				$tmp .= '=';
			}
		}

		return $tmp;
	}
}

function str_encrypt($txt,$key = NULL)
{
	if(empty($key))
	{
		$key = conf('encryption_key');
	}

	$encrypt_key = md5(mt_rand(0,99999));

	$ctr = 0;
	$tmp = '';

	for($i = 0;$i < strlen($txt);$i++)
	{
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= $encrypt_key[$ctr].($txt[$i] ^ $encrypt_key[$ctr++]);
	}

	return base64_encode(str_key($tmp,$key));
}

function str_decrypt($txt,$key = NULL)
{
	if(empty($key))
	{
		$key = conf('encryption_key');
	}

	$txt = str_key(base64_decode($txt), $key);
	$len = strlen($txt);
	if($len%2 != 0) return '';

	$tmp = '';

	for($i = 0;$i < $len;$i++)
	{
		$tmp .= $txt[$i] ^ $txt[++$i];
	}

	return $tmp;
}

function str_key($txt,$encrypt_key)
{
	$encrypt_key = md5($encrypt_key);

	$ctr = 0;
	$tmp = '';

	for($i = 0; $i < strlen($txt); $i++)
	{
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= $txt[$i] ^ $encrypt_key[$ctr++];
	}

	return $tmp;
}

function str_encode($str,$key = NULL)
{
	if(empty($key))
	{
		$key = conf('encryption_key');
	}

	$string_rand = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$encrypt_len = 4;
	$encrypt_key = '';

	for($i = 0;$i < $encrypt_len;$i++)
	{
		$encrypt_key .= $string_rand[mt_rand(0,61)];
	}

	$ctr = 0;
	$tmp = '';

	for($i = 0;$i < strlen($str);$i++)
	{
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= $str[$i] ^ $encrypt_key[$ctr++];
	}

	$tmp = $encrypt_key.$tmp;

	return str_replace(array('+','/'),array('-','_'),trim(base64_encode(str_key($tmp,$key)),'='));
}

function str_decode($str,$key = NULL)
{
	if(empty($key))
	{
		$key = conf('encryption_key');
	}

	$str = str_key(base64_decode(str_replace(array('-','_'),array('+','/'),$str)),$key);

	$encrypt_len = 4;
	$encrypt_key = substr($str,0,$encrypt_len);

	$str = substr($str,$encrypt_len);

	$ctr = 0;
	$tmp = '';

	for($i = 0;$i < strlen($str);$i++)
	{
		$ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
		$tmp .= $str[$i] ^ $encrypt_key[$ctr++];
	}

	return $tmp;
}

function url_encode(&$var)
{
	if(!is_array($var))
	{
		$var = urlencode($var);
	}
	else
	{
		foreach($var as $key => $value)
		{
			url_encode($var[$key]);
		}
	}
}

function url_decode(&$var)
{
	if(!is_array($var))
	{
		$var = urldecode($var);
	}
	else
	{
		foreach($var as $key => $value)
		{
			url_decode($var[$key]);
		}
	}
}

function url_get_clean(&$url)
{
	if(FALSE !== $len = strpos($url, '?')) $url = substr($url, 0, $len);
}

function http_user_agent($default = 'client')
{
	if(isset($_SERVER["HTTP_USER_AGENT"]))
	{
		return $_SERVER["HTTP_USER_AGENT"];
	}
	else
	{
		return $default;
	}
}

function http_method($def = '')
{
	$method = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : $def;

	return $method;
}

function http_header($ind = '',$def = '')
{
	$header = array();

	if(!empty($_SERVER))
	{
		if(!empty($ind))
		{
			$ins = explode(',',$ind);

			foreach($ins as $in)
			{
				$inh = 'HTTP_'.strtoupper(str_replace('-','_',$in));

				if(isset($_SERVER[$inh]))
				{
					$header[$in] = $_SERVER[$inh];
				}
				else
				{
					$header[$in] = $def;
				}
			}

			if(count($ins) == 1) return $header[$ind];
		}
		else
		{
			foreach($_SERVER as $key => $value)
			{
				$key = strtolower($key);

				if(substr($key,0,5) == 'http_')
				{
					$header[substr($key,5)] = $value;
				}
			}
		}
	}

	return $header;
}

function http_entity($force_array = TRUE)
{
	static $_http_entity;

	if(!isset($_http_entity))
	{
		if($force_array)
		{
			$_http_entity = json_decode(file_get_contents("php://input"),TRUE);

			if(!is_array($_http_entity)) $_http_entity = array();
		}
		else
		{
			$_http_entity = file_get_contents("php://input");
		}
	}

	return $_http_entity;
}

function http_request($url,$entity = array(),$header = array(),$method = '',$is_json = TRUE,$timeout = 30)
{
	if(is_array($url))
	{
		foreach($url as $k => $v)
		{
			${$k} = $v;
		}
	}
	else
	{
		if(is_string($entity))
		{
			$method = $entity;
			$entity = array();
		}
		elseif(is_bool($entity))
		{
			$is_json = $entity;
			$entity  = array();
		}
		elseif(is_int($entity))
		{
			$timeout = $entity;
			$entity  = array();
		}

		if(is_string($header))
		{
			$method = $header;
			$header = array();
		}
		elseif(is_bool($header))
		{
			$is_json = $header;
			$header  = array();
		}
		elseif(is_int($header))
		{
			$timeout = $header;
			$header  = array();
		}

		if(is_bool($method))
		{
			$is_json = $method;
			$method  = '';
		}
		elseif(is_int($method))
		{
			$timeout = $method;
			$method  = '';
		}

		if(is_int($is_json))
		{
			$timeout = $is_json;
			$is_json = TRUE;
		}
	}

	$ch = curl_init();

	curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
	curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
	curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);

	if(!empty($header))
	{
		$http_header = array();

		foreach($header as $key => $value)
		{
			$http_header[] = strtoupper($key).': '.$value;
		}

		curl_setopt($ch,CURLOPT_HTTPHEADER,$http_header);
	}

	if(!empty($entity))
	{
		if(empty($method))
		{
			curl_setopt($ch,CURLOPT_POST,TRUE);
			curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($entity));
		}
		else
		{
			switch($method)
			{
				case 'get':
					curl_setopt($ch,CURLOPT_URL,$url.'?'.http_build_query($entity));
					break;
				
				case 'post':
					$method = '';

					curl_setopt($ch,CURLOPT_POST,TRUE);
					curl_setopt($ch,CURLOPT_POSTFIELDS,$entity);
					break;
				
				default:
					if($method == 'json')
					{
						$method = '';

						curl_setopt($ch,CURLOPT_POST,TRUE);
					}

					curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($entity));
					break;
			}
		}
	}

	if(!empty($method))
	{
		curl_setopt($ch,CURLOPT_CUSTOMREQUEST,strtoupper($method));
	}

	$result = curl_exec($ch);

	curl_close($ch);

	if($is_json)
	{
		$result = json_decode($result,TRUE);
	}

	return $result;
}

function download($url,$file_path = '',$allow_type = '')
{
	if (empty($url)) return FALSE;

	if(substr($file_path,-1) == '/')
	{
		$dir = substr($file_path,0,-1);

		$file_name = '';
		$file_type = '';
	}
	else
	{
		$dir = dirname($file_path);

		$file_name = substr($file_path,strlen($dir)+1);

		list($file_name,$file_type) = explode('.',$file_name)+['',''];
	}

	if(!is_dir($dir)) mkdir($dir,0777,TRUE);

	if($file = @fopen($url,'rb'))
	{
		$bin = fread($file,2);

		fclose($file);
	}
	else
	{
		return FALSE;
	}

	$str = unpack('C2chars',$bin);
	$int = intval($str['chars1'].$str['chars2']);

	switch($int)
	{
		case 255216: $detect_type = 'jpg';  break;
		case 13780:  $detect_type = 'png';  break;
		case 7173:   $detect_type = 'gif';  break;
		case 6677:   $detect_type = 'bmp';  break;
		case 8075:   $detect_type = 'zip';  break;
		case 8297:   $detect_type = 'rar';  break;
		case 7790:   $detect_type = 'exe';  break;
		case 7784:   $detect_type = 'midi'; break;

		default: $detect_type = 'non'; break;
	}

	if(!empty($allow_type))
	{
		if(!in_array($detect_type,explode('|',$allow_type))) return FALSE;

		$file_type = $detect_type;
	}
	else
	{
		if(!empty($file_type))
		{
			if($detect_type != 'non' && $detect_type != $file_type) return FALSE;
		}
		else
		{
			$file_type = $detect_type;
		}
	}

	if(empty($file_name))
	{
		$file_name = md5(uniqid().usec(10000,99999)).'.'.$file_type;
	}
	elseif(strpos($file_name,'.') === FALSE)
	{
		$file_name .= '.'.$file_type;
	}

	ob_start();
	readfile($url);
	$file_body = ob_get_contents();
	ob_end_clean();

	$fp = fopen($dir.'/'.$file_name,'a');
	fwrite($fp,$file_body);
	fclose($fp);

	return $dir.'/'.$file_name;
}

function size_img($url,$base = '')
{
	$step = 1024;
	$curn = 1;
	$maxn = 30;

	$body = '';

	if(empty($base))
	{
		if((substr($url,0,7) != 'http://') && (substr($url,0,8) != 'https://')) return FALSE;
	}
	else
	{
		if(strpos($base,',') === FALSE)
		{
			if(substr($url,0,strlen($base)) != $base) return FALSE;
		}
		else
		{
			$stop = TRUE;

			foreach(explode(',',$base) as $b)
			{
				if(substr($url,0,strlen($b)) == $b)
				{
					$stop = FALSE;

					break;
				}
			}

			if($stop) return FALSE;
		}
	}

	$ch = curl_init($url);

	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,3);
	curl_setopt($ch,CURLOPT_TIMEOUT,5);
	curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
	curl_setopt($ch,CURLOPT_AUTOREFERER,1);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);

	do
	{
		if($curn > $maxn) break;

		curl_setopt($ch,CURLOPT_RANGE,(($curn - 1) * $step).'-'.($curn * $step - 1));

		$body .= curl_exec($ch);

		if(empty($body)) break;

		$size = getimagesize('data://image/unknown;base64,'.base64_encode($body));

		$curn++;
	}while(empty($size));

	curl_close($ch);

	return !empty($size) ? array('w' => $size[0],'h' => $size[1],'t' => $size[2]) : FALSE;
}

function sure_url($url,$base = '')
{
	if(empty($base))
	{
		if((substr($url,0,7) != 'http://') && (substr($url,0,8) != 'https://')) return FALSE;
	}
	else
	{
		if(strpos($base,',') === FALSE)
		{
			if(substr($url,0,strlen($base)) != $base) return FALSE;
		}
		else
		{
			$stop = TRUE;

			foreach(explode(',',$base) as $b)
			{
				if(substr($url,0,strlen($b)) == $b)
				{
					$stop = FALSE;

					break;
				}
			}

			if($stop) return FALSE;
		}
	}

	$ch = curl_init();

	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_NOBODY,1);
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,3);
	curl_setopt($ch,CURLOPT_TIMEOUT,3);
	curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
	curl_setopt($ch,CURLOPT_AUTOREFERER,1);

	curl_exec($ch);

	$cd = curl_getinfo($ch,CURLINFO_HTTP_CODE);

	curl_close($ch);

	return $cd == 200;
}

function set_cookie($data,$path = '/',$time = 0,$doma = NULL)
{
	if(!is_array($data))
	{
		$para = func_get_args();
		$data = array($para[0] => $para[1]);
		$path = isset($para[2]) ? $para[2] : '/';
		$time = isset($para[3]) ? $para[3] : 0;
		$doma = isset($para[4]) ? $para[4] : NULL;
	}

	if(is_int($path))
	{
		$time = $path;
		$path = '/';
	}

	if(is_string($time))
	{
		$doma = $time;
		$time = 0;
	}

	$time = $time == 0 ? $time : time()+$time;

	foreach($data as $key => $value)
	{
		if(!is_array($value))
		{
			if(strpos($key,'.') === FALSE)
			{
				if(isset($_COOKIE[$key]))
				{
					if(is_array($_COOKIE[$key]))
					{
						$cookie_str = http_build_query(array($key => $_COOKIE[$key]));
						$cookie_arr = explode('&',$cookie_str);

						foreach($cookie_arr as $cookie)
						{
							$a_cookie = explode('=',$cookie);
							setcookie(urldecode($a_cookie[0]),NULL,-1,$path,$doma);
						}

						$_COOKIE[$key] = NULL;
					}
				}

				setcookie($key,$value,$time,$path,$doma);

				$_COOKIE[$key] = $value;
			}
			else
			{
				$cop = &$_COOKIE;
				$cox = substr_count($key,'.');

				foreach(explode('.',$key) as $ckk => $ckey)
				{
					if($ckk > 0)
					{
						$cookie_key .= '['.$ckey.']';
					}
					else
					{
						$cookie_key = $ckey;
					}

					if($ckk < $cox)
					{
						if(isset($cop[$ckey]))
						{
							if(!is_array($cop[$ckey]))
							{
								setcookie($cookie_key,NULL,-1,$path,$doma);

								$cop[$ckey] = NULL;
							}
						}
						else
						{
							$cop[$ckey] = NULL;
						}

						$cop = &$cop[$ckey];
					}
					else
					{
						if(isset($cop[$ckey]))
						{
							if(is_array($cop[$ckey]))
							{
								$cookie_str = http_build_query(array($cookie_key => $cop[$ckey]));
								$cookie_arr = explode('&',$cookie_str);

								foreach($cookie_arr as $cookie)
								{
									$a_cookie = explode('=',$cookie);
									setcookie(urldecode($a_cookie[0]),NULL,-1,$path,$doma);
								}

								$cop[$ckey] = NULL;
							}
						}
						else
						{
							$cop[$ckey] = NULL;
						}

						$cop = &$cop[$ckey];
					}
				}

				setcookie($cookie_key,$value,$time,$path,$doma);

				$cop = $value;
			}
		}
		else
		{
			$x_cookie_str = http_build_query($value);
			$x_cookie_arr = explode('&',$x_cookie_str);

			foreach($x_cookie_arr as $x_cookie)
			{
				$a_cookie = explode('=',$x_cookie);

				if(isset($a_cookie[1]))
				{
					set_cookie($key.'.'.str_replace(array('[',']'),array('.',''),urldecode($a_cookie[0])),urldecode($a_cookie[1]),$time,$path,$doma);
				}
			}
		}
	}
}

function cookie($key = NULL,$def = FALSE)
{
	if(!empty($key))
	{
		if(strpos($key,'.') === FALSE)
		{
			if(isset($_COOKIE[$key]))
			{
				return $_COOKIE[$key];
			}
			else
			{
				return $def;
			}
		}
		else
		{
			$cop = &$_COOKIE;

			foreach(explode('.',$key) as $ckey)
			{
				if(isset($cop[$ckey]))
				{
					$cop = &$cop[$ckey];
				}
				else
				{
					return $def;
				}
			}

			return $cop;
		}
	}
	else
	{
		return $_COOKIE;
	}
}

function unset_cookie($key = NULL,$path = '/',$doma = NULL)
{
	if(!empty($key))
	{
		if(strpos($key,'.') === FALSE)
		{
			if(isset($_COOKIE[$key]))
			{
				if(!is_array($_COOKIE[$key]))
				{
					setcookie($key,NULL,-1,$path,$doma);
				}
				else
				{
					$cookie_str = http_build_query(array($key => $_COOKIE[$key]));
					$cookie_arr = explode('&',$cookie_str);

					foreach($cookie_arr as $cookie)
					{
						$a_cookie = explode('=',$cookie);
						setcookie(urldecode($a_cookie[0]),NULL,-1,$path,$doma);
					}
				}

				unset($_COOKIE[$key]);
			}
		}
		else
		{
			$cop = &$_COOKIE;
			$ckeys = explode('.',$key);
			$pop_ckey = array_pop($ckeys);

			foreach($ckeys as $ckk => $ckey)
			{
				if($ckk > 0)
				{
					$cookie_key .= '['.$ckey.']';
				}
				else
				{
					$cookie_key = $ckey;
				}

				if(isset($cop[$ckey]))
				{
					$cop = &$cop[$ckey];
				}
				else
				{
					return;
				}
			}

			if(isset($cop[$pop_ckey]))
			{
				if(!is_array($cop[$pop_ckey]))
				{
					setcookie($cookie_key.'['.$pop_ckey.']',NULL,-1,$path,$doma);
				}
				else
				{
					$cookie_str = http_build_query(array($cookie_key.'['.$pop_ckey.']' => $cop[$pop_ckey]));
					$cookie_arr = explode('&',$cookie_str);

					foreach($cookie_arr as $cookie)
					{
						$a_cookie = explode('=',$cookie);
						setcookie(urldecode($a_cookie[0]),NULL,-1,$path,$doma);
					}
				}

				unset($cop[$pop_ckey]);
			}
		}
	}
	else
	{
		if(!empty($_COOKIE))
		{
			$cookie_str = http_build_query($_COOKIE);
			$cookie_arr = explode('&',$cookie_str);

			foreach($cookie_arr as $cookie)
			{
				$a_cookie = explode('=',$cookie);
				setcookie(urldecode($a_cookie[0]),NULL,-1,$path,$doma);
			}

			$_COOKIE = array();
		}
	}
}

function set_session($id = 0,$line = 0,$reme = 0)
{
	global $session;

	$sess_name = conf('sess_name');
	$sess_host = conf('sess_host') ? root_host() : NULL;

	if(empty($id)) return FALSE;
	if(empty($line)) $line = time();

	$time = ($reme == 1) ? conf('sess_time') : 0;

	$sess_code = str_encode($id.','.$line.','.$reme);

	if(!empty($_COOKIE['PHPSESSID']))
	{
		set_cookie($sess_name,$sess_code.md5($_COOKIE['PHPSESSID'].':'.$line),'/',$time,$sess_host);
	}

	header(conf('sess_with').': '.$sess_code);

	$session = array('id' => (string)$id,'line' => (string)$line,'reme' => (string)$reme);

	return $sess_code;
}

function get_session($key = NULL)
{
	global $session;

	if(empty($session))
	{
		if($session === FALSE) return FALSE;

		$sess_name = conf('sess_name');
		$sess_host = conf('sess_host') ? root_host() : NULL;

		if(!empty($_COOKIE[$sess_name]))
		{
			$session_str = substr($_COOKIE[$sess_name],0,-32);
			$verify_line = substr($_COOKIE[$sess_name],-32);
		}
		else
		{
			$sess_with = conf('sess_with');

			if(!empty(http_header($sess_with)))
			{
				$session_str = http_header($sess_with);
			}
			elseif(!empty($_GET[$sess_with]))
			{
				$session_str = $_GET[$sess_with];
			}
		}

		if(empty($session_str))
		{
			del_session();

			return FALSE;
		}

		$sess_str = str_decode($session_str);
		$sess_arr = explode(',',$sess_str);

		if(count($sess_arr) != 3)
		{
			del_session();

			return FALSE;
		}

		if(isset($verify_line))
		{
			if($verify_line != md5($_COOKIE['PHPSESSID'].':'.$sess_arr[1]))
			{
				del_session();

				return FALSE;
			}
		}

		if(empty($_COOKIE[$sess_name]) && !empty($_COOKIE['PHPSESSID']))
		{
			set_cookie($sess_name,$session_str.md5($_COOKIE['PHPSESSID'].':'.$sess_arr[1]),'/',$sess_arr[2] == '1' ? conf('sess_time') : 0,$sess_host);
		}

		$session = array('id' => $sess_arr[0],'line' => $sess_arr[1],'reme' => $sess_arr[2]);
	}

	if(!empty($key))
	{
		if(isset($session[$key]))
		{
			return $session[$key];
		}
		else
		{
			return FALSE;
		}
	}
	else
	{
		return isset($session) ? $session : FALSE;
	}
}

function del_session()
{
	global $session;

	$sess_name = conf('sess_name');

	if(!empty($_COOKIE[$sess_name]))
	{
		$sess_host = conf('sess_host') ? root_host() : NULL;

		unset_cookie($sess_name,'/',$sess_host);
	}

	header(conf('sess_with').': ');

	$session = FALSE;

	return TRUE;
}

function put_storage($key,$content,$expire = FALSE)
{
	$storage_config = conf('storage',array('driver' => 'file','prefix' => 'storage','expire' => 3600));

	$key     = $storage_config['prefix'].'/'.$key;
	$content = json_encode($content);
	$expire  = $expire === FALSE ? $storage_config['expire'] : $expire;

	switch($storage_config['driver'])
	{
		case 'redis':
			$redis = load_redis();

			if($redis)
			{
				if($expire > 0)
				{
					return $redis->setex($key,$expire,$content);
				}
				else
				{
					return $redis->set($key,$content);
				}
			}
			else
			{
				return FALSE;
			}
			break;

		default:
			$expire = !empty($expire) ? (time() + $expire) : (time() + 31536000);
			return file_put_contents('./'.$key,$expire.'#'.$content);
			break;
	}
}

function get_storage($key)
{
	$storage_config = conf('storage',array('driver' => 'file','prefix' => 'storage','expire' => 3600));

	$key = $storage_config['prefix'].'/'.$key;

	switch($storage_config['driver'])
	{
		case 'redis':
			$redis = load_redis();

			if($redis)
			{
				$result = $redis->get($key);

				return !empty($result) ? json_decode($result,TRUE) : FALSE;
			}
			else
			{
				return FALSE;
			}
			break;

		default:
			if(!is_file('./'.$key)) return FALSE;

			$file_content = file_get_contents('./'.$key);

			if($pos = strpos($file_content,'#'))
			{
				$line = substr($file_content,0,$pos);

				if($line > time())
				{
					return json_decode(substr($file_content,$pos+1),TRUE);
				}
				else
				{
					return FALSE;
				}
			}
			else
			{
				return FALSE;
			}
			break;
	}
}

function del_storage($key)
{
	$storage_config = conf('storage',array('driver' => 'file','prefix' => 'storage','expire' => 3600));

	$key = $storage_config['prefix'].'/'.$key;

	switch($storage_config['driver'])
	{
		case 'redis':
			$redis = load_redis();

			if($redis)
			{
				$redis->del($key);

				return TRUE;
			}
			else
			{
				return FALSE;
			}
			break;

		default:
			if(!is_file('./'.$key)) return FALSE;

			unlink('./'.$key);

			return TRUE;
			break;
	}
}

function remote_ip()
{
	if(empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
	{
		$remote_ip = v($_SERVER["REMOTE_ADDR"],'');
	}
	else
	{
		$remote_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}

	return $remote_ip;
}

function valid_ipv4($ip)
{
	$ip_segments = explode('.', $ip);

	// Always 4 segments needed
	if(count($ip_segments) !== 4)
	{
		return FALSE;
	}
	// IP can not start with 0
	if($ip_segments[0][0] == '0')
	{
		return FALSE;
	}

	// Check each segment
	foreach($ip_segments as $segment)
	{
		// IP segments must be digits and can not be
		// longer than 3 digits or greater then 255
		if($segment == '' OR preg_match("/[^0-9]/", $segment) OR $segment > 255 OR strlen($segment) > 3)
		{
			return FALSE;
		}
	}

	return TRUE;
}

function valid_ipv6($str)
{
	// 8 groups, separated by :
	// 0-ffff per group
	// one set of consecutive 0 groups can be collapsed to ::

	$groups = 8;
	$collapsed = FALSE;

	$chunks = array_filter(
		preg_split('/(:{1,2})/', $str, NULL, PREG_SPLIT_DELIM_CAPTURE)
	);

	// Rule out easy nonsense
	if(current($chunks) == ':' OR end($chunks) == ':')
	{
		return FALSE;
	}

	// PHP supports IPv4-mapped IPv6 addresses, so we'll expect those as well
	if(strpos(end($chunks), '.') !== FALSE)
	{
		$ipv4 = array_pop($chunks);

		if(!valid_ipv4($ipv4))
		{
			return FALSE;
		}

		$groups--;
	}

	while($seg = array_pop($chunks))
	{
		if($seg[0] == ':')
		{
			if(--$groups == 0)
			{
				return FALSE;	// too many groups
			}

			if(strlen($seg) > 2)
			{
				return FALSE;	// long separator
			}

			if($seg == '::')
			{
				if($collapsed)
				{
					return FALSE;	// multiple collapsed
				}

				$collapsed = TRUE;
			}
		}
		elseif(preg_match("/[^0-9a-f]/i", $seg) OR strlen($seg) > 4)
		{
			return FALSE; // invalid segment
		}
	}

	return $collapsed OR $groups == 1;
}

function remove_invisible_characters($str,$url_encoded = TRUE)
{
	$non_displayables = array();
	
	// every control character except newline (dec 10)
	// carriage return (dec 13), and horizontal tab (dec 09)
	
	if($url_encoded)
	{
		$non_displayables[] = '/%0[0-8bcef]/';	// url encoded 00-08, 11, 12, 14, 15
		$non_displayables[] = '/%1[0-9a-f]/';	// url encoded 16-31
	}
	
	$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

	do
	{
		$str = preg_replace($non_displayables, '', $str, -1, $count);
	}
	while($count);

	return $str;
}

function supstr()
{
	$paras = func_get_args();

	if(!empty($paras))
	{
		$str = '';

		foreach($paras as $para)
		{
			$para = (string)$para;
			if($para === '') return '';

			$str .= $para;
		}

		return $str;
	}
	else
	{
		return '';
	}
}

function cutstr($string,$sublen = 300,$start = 0,$endwith = '……',$code = 'UTF-8')
{
	$sublen = (int)$sublen;
	$string = strip_tags($string);

	if($code == 'UTF-8')
	{
		$pa = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/";
		preg_match_all($pa,$string,$t_string);

		if((count($t_string[0])-$start) > $sublen)
		{
			return join('',array_slice($t_string[0],$start,$sublen)).$endwith;
		}
		else
		{
			return join('',array_slice($t_string[0],$start,$sublen));
		}
	}
	else
	{
		$start = $start*2;
		$sublen = $sublen*2;
		$strlen = strlen($string);
		$tmpstr = '';

		for($i = 0; $i < $strlen; $i++)
		{
			if($i >= $start && $i < ($start+$sublen))
			{
				if(ord(substr($string,$i,1)) > 129)
				{
					$tmpstr .= substr($string,$i,2);
				}
				else
				{
					$tmpstr .= substr($string,$i,1);
				}
			}
			if(ord(substr($string,$i,1)) > 129) $i++;
		}
		if(strlen($tmpstr) < $strlen) $tmpstr .= $endwith;

		return $tmpstr;
	}
}

?>