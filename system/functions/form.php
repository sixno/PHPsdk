<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

$_input_error = FALSE;

function remove_xss($str,$reserve = '')
{
	if(strpos($str,'&') !== FALSE) $str = str_replace('&','&amp;',$str);
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
			foreach($escape as $key => $value)
			{
				if((strpos($str,$value['<']) !== FALSE) || (strpos($str,$value['>']) !== FALSE)) continue;

				$esc = $value;

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

		foreach($escape as $key => $value)
		{
			if((strpos($str,$value['<']) !== FALSE) || (strpos($str,$value['>']) !== FALSE)) continue;

			$esc = $value;

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

function set_value($field = NULL,$default = NULL)
{
	echo form_var($field,$default);
}

function set_checked($field = NULL,$value = NULL,$is_default = FALSE)
{
	$ret = FALSE;

	if(form_var($field) == $value)
	{
		$ret = TRUE;
	}
	elseif(empty($_POST) && $is_default)
	{
		$ret = TRUE;
	}

	if($ret)
	{
		echo 'checked="checked"';
	}
}

function set_selected($field = NULL,$value = NULL,$is_default = FALSE)
{
	$ret = FALSE;

	if(form_var($field) == $value)
	{
		$ret = TRUE;
	}
	elseif(empty($_POST) && $is_default)
	{
		$ret = TRUE;
	}

	if($ret)
	{
		echo 'selected="selected"';
	}
}

function form_var($field = NULL,$default = NULL)
{
	if(empty($field)) return $default;

	if(FALSE === $pos = strpos($field,'['))
	{
		return post($field,$default);
	}
	else
	{
		$var_name = substr($field,0,$pos);

		if($var = post($var_name))
		{
			$matches = array();
			preg_match_all('/\[(.*?)\]/',$field,$matches);

			$indexes = $matches[1];

			for($i = 0;$i < count($indexes);$i++)
			{
				if(isset($var[$indexes[$i]]))
				{
					$var = $var[$indexes[$i]];
				}
				else
				{
					return $default;
				}
			}
			return $var;
		}
		else
		{
			return $default;
		}
	}
}

function set_input_error($field = NULL,$message = NULL)
{
	global $_input_error;

	if(empty($field) || empty($message)) return FALSE;

	$_input_error[$field] = $message;

	return TRUE;
}

function input_error($field = NULL)
{
	global $_input_error;

	if(empty($_input_error))
	{
		return '';
	}
	else
	{
		if(empty($field))
		{
			return array_shift($_input_error);
		}

		if(!isset($_input_error[$field]))
		{
			return '';
		}
		else
		{
			return $_input_error[$field];
		}
	}
}

// --------------------------------------------------------------------

function required($str,$mod = 'normal',$num = 1)
{
	switch($mod)
	{
		case 'normal':
			if ( ! is_array($str))
			{
				return (tags_filter($str) === '') ? FALSE : TRUE;
			}
			else
			{
				if($num != 0)
				{
					$array = array_filter($str,'tags_filter');
					return (count($array) == $num);
				}
				else
				{
					$array = array_filter($str,'tags_filter');
					return ((count($str) == count($array)) && count($str));
				}
			}
			break;
		
		case 'allow_img':
			if ( ! is_array($str))
			{
				return (tags_allow_img_filter($str) === '') ? FALSE : TRUE;
			}
			else
			{
				if($num != 0)
				{
					$array = array_filter($str,'tags_allow_img_filter');
					return (count($array) == $num);
				}
				else
				{
					$array = array_filter($str,'tags_allow_img_filter');
					return ((count($str) == count($array)) && count($str));
				}
			}
			break;

		default:
			return FALSE;
			break;
	}
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
	$fstr = form_var($field);

	return ($str !== $fstr) ? FALSE : TRUE;
}

function greater_to($str, $field)
{
	$fstr = form_var($field);

	return ($str >= $fstr);
}

function later_to($str, $field)
{
	$fstr = form_var($field);

	if(!is_numeric($str)) $str = strtotime($str);
	if(!is_numeric($fstr)) $fstr = strtotime($fstr);

	return ($str >= $fstr);
}

// Match one field to another
function is_unique($str, $field)
{
	if((string)$str === '') return TRUE;

	$db = load_database();

	list($table, $field) = explode('.', $field);

	if($pos = strpos($field,'#'))
	{
		if($str == substr($field,$pos+1)) return TRUE;

		$field = substr($field,0,$pos);
	}

	$query = $db->limit(1)->get_where($table, array($field => $str));
	
	return $query->num_rows() === 0;
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

?>