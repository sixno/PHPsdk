<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

load_conf('qiniu');

class Qiniu
{
	public $up_host = 'up.qiniu.com';
	public $rs_host = 'rs.qiniu.com';
	public $bx_host = 'iovip.qbox.me';

	// see https://github.com/qiniu/php-sdk/blob/master/src/Qiniu/Zone.php

	public $accesskey = '';
	public $secretkey = '';

	public $blocksize = 4194304; // 4MB = 4*1024*1024

	public $policyFields = array(
		'callbackUrl',
		'callbackBody',
		'callbackHost',
		'callbackBodyType',
		'callbackFetchKey',

		'returnUrl',
		'returnBody',

		'endUser',
		'saveKey',
		'insertOnly',

		'detectMime',
		'mimeLimit',
		'fsizeLimit',

		'persistentOps',
		'persistentNotifyUrl',
		'persistentPipeline',
	);

	public $deprecatedPolicyFields = array(
		'asyncOps',
	);

	public function __construct($config = array())
	{
		$pre_config = conf('qiniu');

		if(empty($config))
		{
			$config = $pre_config;
		}
		else
		{
			$config = array_merge($pre_config,$config);
		}

		$this->initialize($config);
	}

	public function initialize($config = array())
	{
		if(!empty($config) && is_array($config))
		{
			foreach ($config as $key => $val)
			{
				if (isset($this->$key))
				{
					$this->$key = $val;
				}
			}
		}
	}

	public function _base64($data)
	{
		return str_replace(array('+','/'),array('-','_'),base64_encode($data));
	}

	public function _crc32($data)
	{
		$hash = hash('crc32b',$data);
		$array = unpack('N',pack('H*',$hash));

		return sprintf('%u',$array[1]);
	}

	public function _curl($url,$header = NULL,$data = NULL,$method = NULL,$is_multipart = FALSE)
	{
		if(!empty($this->error))
		{
			$this->result = FALSE;
			return FALSE;
		}

		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_HEADER,FALSE);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
		curl_setopt($ch,CURLOPT_AUTOREFERER,true);
		curl_setopt($ch,CURLOPT_TIMEOUT,30);

		if(strpos($url,'https://') === 0)
		{
			curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
			curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
		}

		if(!empty($header) && is_array($header))
		{
			if(isset($header['cookie']))
			{
				$cookie = $header['cookie'];
				unset($header['cookie']);
			}

			if(!empty($header))
			{
				$headers = array();

				foreach($header as $key => $value)
				{
					$headers[] = $key.': '.$value;
				}

				curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
			}

			if(!empty($cookie))
			{
				$cookie_str = '';

				if(!is_array($cookie))
				{
					$cookie_str = $cookie;
				}
				else
				{
					foreach($cookie as $key => $value)
					{
						$cookie_str .= $key.'='.$value.'; ';
					}
				}

				curl_setopt($ch,CURLOPT_COOKIE,$cookie_str);
			}
		}
		elseif(!empty($header) && is_string($header))
		{
			$is_multipart = $method;

			$method = $header;
		}

		if(!empty($data) && is_array($data))
		{
			if(empty($method)) curl_setopt($ch,CURLOPT_POST,TRUE);

			if(!$is_multipart)
			{
				if(empty($data['&body']))
				{
					curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));
				}
				else
				{
					curl_setopt($ch,CURLOPT_POSTFIELDS,$data['&body']);
				}
			}
			else
			{
				if(version_compare(PHP_VERSION,'7.0.0') != -1)
				{
					foreach($data as $key => $value)
					{
						if(substr($value,0,1) == '@')
						{
							$data[$key] = new CURLFile(substr($value,1));
						}
					}
				}
				else
				{
					if(defined('CURLOPT_SAFE_UPLOAD')) curl_setopt($ch,CURLOPT_SAFE_UPLOAD,FALSE);
				}

				curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
			}
		}
		elseif(!empty($data) && is_string($data))
		{
			$method = $data;
		}

		if(!empty($method) && is_string($method))
		{
			curl_setopt($ch,CURLOPT_CUSTOMREQUEST,strtoupper($method));
		}
		
		$result = curl_exec($ch);

		curl_close($ch);

		$result_array = json_decode($result,TRUE);

		return !empty($result_array) ? $result_array : $result;
	}

	public function copyPolicy(&$policy,$originPolicy,$strictPolicy)
	{
		if($originPolicy === NULL) return array();

		foreach($originPolicy as $key => $value)
		{
			if(in_array((string)$key,$this->deprecatedPolicyFields,TRUE))
			{
				message('qiniu::'.$key.' has deprecated');
			}

			if(!$strictPolicy || in_array((string) $key, $this->policyFields,TRUE))
			{
				$policy[$key] = $value;
			}
		}

		return $policy;
	}

	public function sign($data)
	{
		$hmac = hash_hmac('sha1',$data,$this->secretkey,TRUE);
		return $this->accesskey.':'.$this->_base64($hmac);
	}

	public function signWithData($data)
	{
		$data = $this->_base64($data);

		return $this->sign($data).':'.$data;
	}

	public function signRequest($urlString, $body, $contentType = NULL)
	{
		$url = parse_url($urlString);

		$data = '';

		if(array_key_exists('path', $url))
		{
			$data = $url['path'];
		}

		if(array_key_exists('query', $url))
		{
			$data .= '?' . $url['query'];
		}

		$data .= "\n";

		if ($body !== NULL && $contentType === 'application/x-www-form-urlencoded')
		{
			$data .= $body;
		}

		return $this->sign($data);
	}

	public function authorization($url,$body = NULL,$contentType = NULL)
	{
		$authorization = 'QBox '.$this->signRequest($url,$body,$contentType);

		return array('Authorization' => $authorization);
	}

	public function uploadToken($bucket)
	{
		$scope        = $bucket;
		$key          = NULL;
		$expire       = 3600;
		$policy       = NULL;
		$strictPolicy = TRUE;

		switch(func_num_args())
		{
			case 2:
				$policy = func_get_arg(1);
				break;

			case 3:
				$expire = func_get_arg(1);
				$policy = func_get_arg(2);
				break;

			case 4:
				$key    = func_get_arg(1);
				$expire = func_get_arg(2);
				$policy = func_get_arg(3);
				break;

			case 5:
				$key          = func_get_arg(1);
				$expire       = func_get_arg(2);
				$policy       = func_get_arg(3);
				$strictPolicy = func_get_arg(4);
				break;
		}

		if(!empty($key)) $scope .= ':'.$key;

		$args = array();
		$args = $this->copyPolicy($args,$policy,$strictPolicy);

		$args['scope']    = $scope;
		$args['deadline'] = time() + $expire;

		$astr = json_encode($args);

		return $this->signWithData($astr);
	}

	public function entry($bucket,$key)
	{
		$en = $bucket;

		if(!empty($key))
		{
			$en = $bucket . ':' . $key;
		}

		return $this->_base64($en);
	}

	public function private_url($url,$expires = 3600)
	{
		$deadline = time() + $expires;

		if(strpos($url, '?') !== FALSE)
		{
			$url .= '&e=';
		}
		else
		{
			$url .= '?e=';
		}

		$url .= $deadline;

		return $url.'&token='.$this->sign($url);
	}

	public function watered_url($url,$water_img,$water_opt = array())
	{
		$url .= '?watermark/1/image/'.$this->_base64($water_img);

		if(!empty($water_opt['dissolve'])) $url .= '/dissolve/'.$water_opt['dissolve'];

		if(!empty($water_opt['gravity']))
		{
			$url .= '/gravity/'.$water_opt['gravity'];
		}
		else
		{
			$url .= '/gravity/Center';
		}

		if(!empty($water_opt['dx'])) $url .= '/dx/'.$water_opt['dx'];

		if(!empty($water_opt['dy'])) $url .= '/dy/'.$water_opt['dy'];

		if(!empty($water_opt['ws'])) $url .= '/ws/'.$water_opt['ws'];

		return $url;
	}

	public function resized_url($url,$width,$height,$opt = array())
	{
		$url .= '?imageView2/'.(isset($opt['mode']) ? $opt['mode'] : '2');

		if(!empty($width)) $url .= '/w/'.$width;

		if(!empty($height)) $url .= '/h/'.$height;

		if(isset($opt['format'])) $url .= '/format/'.$opt['format'];

		if(isset($opt['interlace'])) $url .= '/interlace/'.$opt['interlace'];

		if(isset($opt['q'])) $url .= '/q/'.$opt['q'];

		if(isset($opt['ignore-error'])) $url .= '/ignore-error/'.$opt['ignore-error'];

		return $url;
	}

	public function upload($file_path,$bucket,$key = '',$expire = 3600,$progress_callback = '')
	{
		$file = fopen($file_path,'rb');

		$info = fstat($file);

		if(empty($key)) $key = $file_path;

		if($info['size'] <= $this->blocksize)
		{
			$url = 'http://'.$this->up_host.'/';

			$data['token'] = $this->uploadToken($bucket);
			$data['key']   = $key;
			$data['file']  = '@'.realpath($file_path);

			$result = $this->_curl($url,'post',$data,TRUE);
		}
		else
		{
			if(is_callable($expire))
			{
				$progress_callback = $expire;

				$expire = 3600;
			}

			$uploaded = 0;
			$contexts = array();
			$uploadtk = $this->uploadToken($bucket,$key,$expire,NULL);

			while($uploaded < $info['size'])
			{
				$blocksize = $info['size'] < $uploaded + $this->blocksize ? $info['size'] - $uploaded : $this->blocksize;

				$file_data = fread($file,$blocksize);

				if($file_data === FALSE) return '';

				$crc = $this->_crc32($file_data);

				$ret = $this->_curl('http://'.$this->up_host.'/mkblk/'.$blocksize,['Authorization' => 'UpToken '.$uploadtk],['&body' =>  $file_data],'post');

				if(empty($ret) || !is_array($ret) || !isset($ret['crc32']) || $crc != $ret['crc32'])
				{
					$ret = $this->_curl('http://'.$this->up_host.'/mkblk/'.$blocksize,['Authorization' => 'UpToken '.$uploadtk],['&body' => $file_data],'post');
				}

				if(empty($ret) || !is_array($ret) || !isset($ret['crc32']) || $crc != $ret['crc32']) return '';

				array_push($contexts,$ret['ctx']);

				$uploaded += $blocksize;

				if(is_callable($progress_callback))
				{
					$progress_callback($uploaded,$info['size']);
				}
			}

			$url  = 'http://'.$this->up_host.'/mkfile/'.$info['size'];
			$url .= '/mimeType/'.$this->_base64('application/octet-stream');
			$url .= !empty($key) ? '/key/'.$this->_base64($key) : '';
			$url .= '/fname/'.$this->_base64($file_path);

			$result = $this->_curl($url,['Authorization' => 'UpToken '.$uploadtk],['&body' => implode(',',$contexts)],'post');
		}

		if(is_array($result))
		{
			if(empty($result['error']))
			{
				return $result['key'];
			}
			else
			{
				return '';
			}
		}
		else
		{
			return '';
		}
	}

	public function fetch($file_url,$bucket,$key)
	{
		$url = 'http://'.$this->bx_host.'/fetch/'.$this->_base64($file_url).'/to/'.$this->entry($bucket,$key);

		$header = $this->authorization($url,NULL,'application/x-www-form-urlencoded');

		$result = $this->_curl($url,$header,'post');

		if(is_array($result))
		{
			if(empty($result['error']))
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}
		else
		{
			return empty($result) ? TRUE : FALSE;
		}
	}

	public function chtype($bucket,$key,$type)
	{
		// type: 0 standard, 1 low frequency
		$url = 'http://'.$this->rs_host.'/chtype/'.$this->entry($bucket,$key).'/type/'.$type;

		$header = $this->authorization($url,NULL,'application/x-www-form-urlencoded');

		$result = $this->_curl($url,$header,'post');

		if(is_array($result))
		{
			if(empty($result['error']))
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}
		else
		{
			return empty($result) ? TRUE : FALSE;
		}
	}

	public function rename($from_bucket,$from_key,$dest_key,$dest_bucket = '',$force = FALSE)
	{
		if(is_bool($dest_bucket))
		{
			$force = $dest_bucket;

			$dest_bucket = '';
		}

		if($dest_bucket === '')
		{
			$dest_bucket = $from_bucket;
		}

		$url = 'http://'.$this->rs_host.'/move/'.$this->entry($from_bucket,$from_key).'/'.$this->entry($dest_bucket,$dest_key);

		if($force) $url .= '/force/true';

		$header = $this->authorization($url,NULL,'application/x-www-form-urlencoded');

		$result = $this->_curl($url,$header,'post');

		if(is_array($result))
		{
			if(empty($result['error']))
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}
		else
		{
			return empty($result) ? TRUE : FALSE;
		}
	}

	public function delete($bucket,$key)
	{
		$url = 'http://'.$this->rs_host.'/delete/'.$this->entry($bucket,$key);

		$header = $this->authorization($url,NULL,'application/x-www-form-urlencoded');

		$result = $this->_curl($url,$header,'post');

		if(is_array($result))
		{
			if(empty($result['error']))
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}
		else
		{
			return empty($result) ? TRUE : FALSE;
		}
	}
}

?>