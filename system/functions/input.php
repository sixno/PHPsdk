<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

$_input_data = array();
$_enter_data = array();
$_input_errs = FALSE;

function remove_xss($str,$reserve = '')
{
	// if(strpos($str,'&') !== FALSE) $str = str_replace('&','&amp;',$str);
	if(strpos($str,'<') === FALSE && strpos($str,'>') === FALSE) return $str;

	$never_allowed_tags = array('form','javascript','vbscript','expression','applet','meta','xml','blink','link','style','script','embed','object','iframe','frame','frameset','ilayer','layer','bgsound','title','base');
	$never_allowed_acts = array('onabort','onactivate','onafterprint','onafterupdate','onbeforeactivate','onbeforecopy','onbeforecut','onbeforedeactivate','onbeforeeditfocus','onbeforepaste','onbeforeprint','onbeforeunload','onbeforeupdate','onblur','onbounce','oncellchange','onchange','onclick','oncontextmenu','oncontrolselect','oncopy','oncut','ondataavailable','ondatasetchanged','ondatasetcomplete','ondblclick','ondeactivate','ondrag','ondragend','ondragenter','ondragleave','ondragover','ondragstart','ondrop','onerror','onerrorupdate','onfilterchange','onfinish','onfocus','onfocusin','onfocusout','onhelp','onkeydown','onkeypress','onkeyup','onlayoutcomplete','onload','onlosecapture','onmousedown','onmouseenter','onmouseleave','onmousemove','onmouseout','onmouseover','onmouseup','onmousewheel','onmove','onmoveend','onmovestart','onpaste','onpropertychange','onreadystatechange','onreset','onresize','onresizeend','onresizestart','onrowenter','onrowexit','onrowsdelete','onrowsinserted','onscroll','onselect','onselectionchange','onselectstart','onstart','onstop','onsubmit','onunload');

	if(!empty($reserve))
	{
		if(!is_array($reserve)) $reserve = explode(',',$reserve);

		foreach($reserve as $rsv)
		{
			if(stripos($str,$rsv) === FALSE) continue;

			$str = preg_replace_callback('/(\<'.preg_quote($rsv).'[\s\S]*?\>)([\s\S]*?)(\<\/'.preg_quote($rsv).'\>)/i',create_function('$matches','return $matches[1].str_replace(array("<",">"),array("&lt;","&gt;"),$matches[2]).$matches[3];'),$str);
		}
	}

	if(strpos($str,'?')) $str = preg_replace('/\<\?([\s\S]*?)\>/','',$str);

	foreach($never_allowed_tags as $tag)
	{
		if(stripos($str,$tag) === FALSE) continue;

		$str = preg_replace('/\<([\s]*?)'.preg_quote($tag).'([\s\S]*?)\<\/([\s]*?)'.preg_quote($tag).'([\s]*?)\>/i','',$str);
		$str = preg_replace('/\<([\s]*?)'.preg_quote($tag).'([\s\S]*?)\>/i','',$str);
		$str = preg_replace('/\<\/([\s]*?)'.preg_quote($tag).'([\s]*?)\>/i','',$str);
	}

	foreach($never_allowed_acts as $act)
	{
		if(stripos($str,$act) === FALSE) continue;

		$str = preg_replace('/'.$act.'([\s]*?)=["\']([\s\S]*?)["\']/','',$str);
	}

	if(strpos($str,'<') !== FALSE)
	{
		$escape = array(array('<' => '*$','>' => '$*'),array('<' => '*(','>' => ')*'),array('<' => '%*','>' => '*%'),array('<' => '^&','>' => '&^'),array('<' => '|\\','>' => '/|'),array('<' => '|/','>' => '\\|'));

		if(strpos($str,'>') !== FALSE)
		{
			foreach($escape as $key => $val)
			{
				if((strpos($str,$val['<']) !== FALSE) || (strpos($str,$val['>']) !== FALSE)) continue;

				$esc = $val;

				break;
			}

			if(!empty($esc))
			{
				$str = preg_replace('/<([a-zA-Z]+[\s\S]*?)>/',$esc['<'].'$1'.$esc['>'],$str);
				$str = preg_replace('/<(\/[\s\S]*?)>/',$esc['<'].'$1'.$esc['>'],$str);
			}
		}
	}

	$str = str_replace(array('<','>'),array('&lt;','&gt;'),$str);

	if(!empty($esc))
	{
		$str = str_replace(array($esc['<'],$esc['>']),array('<','>'),$str);
	}

	return $str;
}

function remove_hms($str,$allow_tag = '')
{
	if(strpos($str,'&') !== FALSE) $str = str_replace('&','&amp;',$str);
	if(strpos($str,'<') === FALSE && strpos($str,'>') === FALSE) return $str;

	$has_allow_tag = FALSE;

	if(!empty($allow_tag))
	{
		$escape = array(array('<' => '*$','>' => '$*'),array('<' => '*(','>' => ')*'),array('<' => '%*','>' => '*%'),array('<' => '^&','>' => '&^'),array('<' => '|\\','>' => '/|'),array('<' => '|/','>' => '\\|'));

		foreach($escape as $key => $val)
		{
			if((strpos($str,$val['<']) !== FALSE) || (strpos($str,$val['>']) !== FALSE)) continue;

			$esc = $val;

			break;
		}

		if(!empty($esc))
		{
			foreach(explode(',',$allow_tag) as $tag)
			{
				if(strpos($str,'<'.$tag) === FALSE) continue;
				if(!$has_allow_tag) $has_allow_tag = TRUE;

				$str = preg_replace('/<('.$tag.' .*?)>/i',$esc['<'].'$1'.$esc['>'],$str);
				$str = str_ireplace('</'.$tag.'>',$esc['<'].'/'.$tag.$esc['>'],$str);
			}
		}
	}

	if(strpos($str,'<') !== FALSE)
	{
		if(strpos($str,'?')) $str = preg_replace('/\<\?([\s\S]*?)\>/','',$str);

		$str = preg_replace('/<[a-zA-Z]+[\s\S]*?>/','',$str);
		$str = preg_replace('/<\/[\s\S]*?>/','',$str);
	}

	$str = str_replace(array('<','>'),array('&lt;','&gt;'),$str);

	if($has_allow_tag)
	{
		$str = str_replace(array($esc['<'],$esc['>']),array('<','>'),$str);
	}

	return $str;
}

function rsa_decrypt($enc,$field = '')
{
	$pos = strpos($enc,':');

	if(empty($pos)) return '';

	$rsa = substr($enc,0,$pos);
	$enc = substr($enc,$pos+1);

	load_conf('rsa');
	$prk = conf('rsa.'.$rsa.'.private_key');

	if($prk == '') return '';

	$enc = base64_decode($enc);
	$dec = '';

	$private_key = openssl_pkey_get_private($prk);

	openssl_private_decrypt($enc,$dec,$private_key);

	return $dec;
}

function input_original($field,$route = 'json',$default = '')
{
	global $_input_data;

	switch($route)
	{
		case 'get':
			if(!isset($_input_data['get']))
			{
				if(is_array($_GET))
				{
					if(count($_GET) == count($_GET,1))
					{
						$_input_data['get'] = $_GET;
					}
					else
					{
						$_input_data['get'] = array();
					}
				}
				else
				{
					$_input_data['get'] = array();
				}
			}
			break;
		
		case 'post':
			if(!isset($_input_data['post']))
			{
				if(is_array($_POST))
				{
					if(count($_POST) == count($_POST,1))
					{
						$_input_data['post'] = $_POST;
					}
					else
					{
						$_input_data['post'] = array();
					}
				}
				else
				{
					$_input_data['post'] = array();
				}
			}
			break;
		
		case 'json':
			if(!isset($_input_data['json']))
			{
				$_input_data['json'] = json_decode(file_get_contents("php://input"),TRUE,2);

				if(is_array($_input_data['json']))
				{
					foreach($_input_data['json'] as $key => $val)
					{
						$_input_data['json'][$key] = strval($val);
					}
				}
				else
				{
					$_input_data['json'] = array();
				}
			}
			break;
		
		default:
			return FALSE;
			break;
	}

	return isset($_input_data[$route][$field]) ? $_input_data[$route][$field] : $default;
}

function input_data($item,$default = '')
{
	global $_input_data;
	global $_enter_data;

	if(!is_array($item))
	{
		list($field,$force,$route,$value) = (explode(':',$item) + array('','','json',$default));
	}
	else
	{
		$field = '';
		$force = '';
		$route = 'json';
		$value = $default;

		foreach($item as $key => $val)
		{
			$$key = $val;
		}
	}

	if(empty($field)) return $default;

	if(isset($_enter_data[$field])) return $_enter_data[$field];

	$_enter_data[$field] = input_original($field,$route,$value);

	if($_enter_data[$field] !== FALSE)
	{
		switch($force)
		{
			case 'strval':
				$_enter_data[$field] = htmlentities($_enter_data[$field]);
				break;

			case 'txtval':
				$_enter_data[$field] = remove_xss($_enter_data[$field]);
				break;

			case 'intval':
				$_enter_data[$field] = strval($_enter_data[$field]);

				if($_enter_data[$field] !== '')
				{
					$_enter_data[$field] = strval(intval($_enter_data[$field]));
				}
				break;

			case 'number':
				$_enter_data[$field] = strval($_enter_data[$field]);

				if($_enter_data[$field] !== '')
				{
					$_enter_data[$field] = strval(floatval($_enter_data[$field]));
				}
				break;

			case 'putext':
				$_enter_data[$field] = remove_hms($_enter_data[$field]);
				break;

			case 'imtext':
				$_enter_data[$field] = remove_xss(remove_hms($_enter_data[$field],'img'));
				break;

			case 'ictext':
				$_enter_data[$field] = remove_xss(remove_hms($_enter_data[$field],'img,code'));
				break;
			
			case 'rsastr':
				$_enter_data[$field] = rsa_decrypt($_enter_data[$field],$field);
				break;
			
			case 'rsatss':
				$_enter_data[$field] = rsa_decrypt($_enter_data[$field],$field);

				$pos = strrpos($_enter_data[$field],'@');

				if(!empty($pos))
				{
					$time = (int)substr($_enter_data[$field],$pos+1);

					if(abs(time() - $time) < 300)
					{
						$_enter_data[$field] = substr($_enter_data[$field],0,$pos);
					}
					else
					{
						$_enter_data[$field] = '';
					}
				}
				else
				{
					$_enter_data[$field] = '';
				}
				break;
			
			case 'rsatsi':
				$storage_key = md5($_enter_data[$field]);

				$_enter_data[$field] = rsa_decrypt($_enter_data[$field],$field);

				$pos = strrpos($_enter_data[$field],'@');

				if(!empty($pos))
				{
					$time = (int)substr($_enter_data[$field],$pos+1);

					if(get_storage('rsatss_'.$storage_key) != $time)
					{
						if(abs(time() - $time) < 300)
						{
							$_enter_data[$field] = substr($_enter_data[$field],0,$pos);

							put_storage('rsatss_'.$storage_key,$time,300);
						}
						else
						{
							$_enter_data[$field] = '';
						}
					}
					else
					{
						$_enter_data[$field] = '';
					}
				}
				else
				{
					$_enter_data[$field] = '';
				}
				break;
			
			default:
				$_enter_data[$field] = strval($_enter_data[$field]);
				break;
		}

		return $_enter_data[$field];
	}
	else
	{
		unset($_enter_data[$field]);

		return FALSE;
	}
}

function input_enter_data($field = '',$default = '')
{
	global $_enter_data;

	if(empty($field))
	{
		return $_enter_data;
	}
	else
	{
		if(!is_array($field))
		{
			return isset($_enter_data[$field]) ? $_enter_data[$field] : $default;
		}
		else
		{
			$result = array();

			foreach ($field as $item)
			{
				$result[$item] = isset($_enter_data[$item]) ? $_enter_data[$item] : $default;
			}

			return $result;
		}
	}
}

function input_error($field = '',$default = 'input_error.unexpected_input_error')
{
	global $_input_errs;

	if(!empty($_input_errs))
	{
		if(empty($field))
		{
			return array_shift($_input_errs);
		}
		else
		{
			if(isset($_input_errs[$field]))
			{
				return $_input_errs[$field];
			}
			else
			{
				return lang($default);
			}
		}
	}
	else
	{
		return lang($default);
	}
}

function set_input_error($field = NULL,$message = NULL)
{
	global $_input_errs;

	if(empty($field) || empty($message)) return FALSE;

	$_input_errs[$field] = $message;

	return TRUE;
}

// --------------------------------------------------------------------

function required($str)
{
	return ($str === '') ? FALSE : TRUE;
}

// Performs a Regular Expression match test.
function regex_match($str, $regex)
{
	if ( ! preg_match($regex, $str))
	{
		return FALSE;
	}

	return  TRUE;
}

// Match one field to another
function matches($str, $field)
{
	$fstr = input_enter_data($field);

	return ($str !== $fstr) ? FALSE : TRUE;
}

function greater_to($str, $field)
{
	$fstr = input_enter_data($field);

	return ($str >= $fstr);
}

function later_to($str, $field)
{
	$fstr = input_enter_data($field);

	if(!is_numeric($str)) $str = strtotime($str);
	if(!is_numeric($fstr)) $fstr = strtotime($fstr);

	return ($str >= $fstr);
}

function is_unique($str, $field,$prefix = '',$suffix = '')
{
	if((string)$str === '') return TRUE;

	if($prefix != '' && substr($prefix,0,1) == '#') $prefix = input_enter_data(substr($prefix,1));

	if($suffix != '' && substr($suffix,0,1) == '#') $suffix = input_enter_data(substr($suffix,1));

	$str = $prefix.$str.$suffix;

	list($table, $field) = explode('.', $field);

	if($pos = strpos($field,'#'))
	{
		if($str == substr($field,$pos+1)) return TRUE;

		$field = substr($field,0,$pos);
	}

	$model = &load_model($table,'core');

	return $model->find($field,[$field => $str]) === FALSE;
}

function min_length($str, $val)
{
	if((string)$str === '') return TRUE;

	if (preg_match("/[^0-9]/", $val))
	{
		return FALSE;
	}

	if (function_exists('mb_strlen'))
	{
		return (mb_strlen($str,'utf8') < $val) ? FALSE : TRUE;
	}

	return (strlen($str) < $val) ? FALSE : TRUE;
}

function max_length($str, $val)
{
	if((string)$str === '') return TRUE;

	if (preg_match("/[^0-9]/", $val))
	{
		return FALSE;
	}

	if (function_exists('mb_strlen'))
	{
		return (mb_strlen($str,'utf8') > $val) ? FALSE : TRUE;
	}

	return (strlen($str) > $val) ? FALSE : TRUE;
}

function exact_length($str, $val)
{
	if((string)$str === '') return TRUE;

	if (preg_match("/[^0-9]/", $val))
	{
		return FALSE;
	}

	if (function_exists('mb_strlen'))
	{
		return (mb_strlen($str,'utf8') != $val) ? FALSE : TRUE;
	}

	return (strlen($str) != $val) ? FALSE : TRUE;
}

function valid_email($str)
{
	if((string)$str === '') return TRUE;

	return ( ! preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $str)) ? FALSE : TRUE;
}

function valid_emails($str)
{
	if (strpos($str, ',') === FALSE)
	{
		return valid_email(trim($str));
	}

	foreach (explode(',', $str) as $email)
	{
		if (trim($email) != '' && valid_email(trim($email)) === FALSE)
		{
			return FALSE;
		}
	}

	return TRUE;
}

function valid_ip($ip, $which = '')
{
	$which = strtolower($which);

	if ($which !== 'ipv6' && $which !== 'ipv4')
	{
		if (strpos($ip, ':') !== FALSE)
		{
			$which = 'ipv6';
		}
		elseif (strpos($ip, '.') !== FALSE)
		{
			$which = 'ipv4';
		}
		else
		{
			return FALSE;
		}
	}

	// First check if filter_var is available
	if (is_callable('filter_var'))
	{
		switch ($which) {
			case 'ipv4':
				$flag = FILTER_FLAG_IPV4;
				break;
			case 'ipv6':
				$flag = FILTER_FLAG_IPV6;
				break;
			default:
				$flag = '';
				break;
		}

		return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flag);
	}
	else
	{
		switch ($which) {
			case 'ipv4':
				valid_ipv4($ip);
				break;
			case 'ipv6':
				valid_ipv6($ip);
				break;
			default:
				return FALSE;
				break;
		}
	}
}

function alpha($str)
{
	return ( ! preg_match("/^([a-z])+$/i", $str)) ? FALSE : TRUE;
}

function alpha_numeric($str)
{
	return ( ! preg_match("/^([a-z0-9])+$/i", $str)) ? FALSE : TRUE;
}

function alpha_dash($str)
{
	return ( ! preg_match("/^([-a-z0-9_-])+$/i", $str)) ? FALSE : TRUE;
}

function numeric($str)
{
	if(empty($str)) return TRUE;
	return (bool)preg_match( '/^[\-+]?[0-9]*\.?[0-9]+$/', $str);
}

function integer($str)
{
	return (bool) preg_match('/^[\-+]?[0-9]+$/', $str);
}

function decimal($str)
{
	return (bool) preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $str);
}

function greater_than($str, $min)
{
	if((string)$str === '') return TRUE;
	if(!is_numeric($str)) return FALSE;

	return $str > $min;
}

function less_than($str, $max)
{
	if((string)$str === '') return TRUE;
	if(!is_numeric($str)) return FALSE;

	return $str < $max;
}

function is_natural($str)
{
	if((string)$str === '') return TRUE;

	return (bool) preg_match( '/^[0-9]+$/', $str);
}

function is_natural_no_zero($str)
{
	if((string)$str === '') return TRUE;
	if(!preg_match( '/^[0-9]+$/', $str)) return FALSE;
	if($str == 0) return FALSE;

	return TRUE;
}

function valid_base64($str)
{
	return (bool) ! preg_match('/[^a-zA-Z0-9\/\+=]/', $str);
}

function valid_url($str)
{
	return (bool) preg_match("/^http:\/\/[_a-zA-Z0-9-]+(.[_a-zA-Z0-9-]+)*$/", $str);
}

function is_file_url($str,$base = '')
{
	if((string)$str === '') return TRUE;

	return sure_url($str,$base);
}

function is_image_url($str,$base = '')
{
	if((string)$str === '') return TRUE;

	return size_img($str,$base) ? TRUE : FALSE;
}

function tags_filter($str,$control = NULL)
{
	if(!is_bool($str))
	{
		if(!is_array($str) && !is_object($str))
		{
			$str = strip_tags($str,$control);
			$str = trim($str);
		}
		else
		{
			return '';
		}
	}
	
	return $str;
}

?>