<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

load_conf('weixin');

class Weixin
{
	public $appid = '';
	public $appsc = '';

	public function __construct($config = array())
	{
		$pre_config = conf('weixin');

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

	public function _curl($url,$header = NULL,$data = NULL,$method = NULL,$is_multipart = FALSE)
	{
		$ch = curl_init();

		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_HEADER,TRUE);
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

			if(!empty($header)) curl_setopt($ch,CURLOPT_HTTPHEADER,$header);

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
			$method = $header;
		}

		if(!empty($data) && is_array($data))
		{
			if(empty($method)) curl_setopt($ch,CURLOPT_POST,TRUE);

			if(!$is_multipart)
			{
				curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));
			}
			else
			{
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
		
		$ch_result = curl_exec($ch);
		$ch_getinf = curl_getinfo($ch);

		curl_close($ch);

		$ch_header_size = $ch_getinf['header_size'];

		return json_decode(trim(substr($ch_result,$ch_header_size)),TRUE);
	}

	public function access_token()
	{
		$access_token = get_storage('weixin_access_token');

		if(empty($access_token))
		{
			$result = $this->_curl('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->appsc);

			if(!empty($result))
			{
				$access_token = $result['access_token'];

				put_storage('weixin_access_token',$access_token,7000);
			}
		}

		return $access_token;
	}

	public function js_signature($noncestr,$timestamp,$url)
	{
		$access_token = $this->access_token();

		if(empty($access_token)) return FALSE;

		$jsapi_ticket = $this->_curl('https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi');

		if(isset($jsapi_ticket['ticket']))
		{
			return sha1('jsapi_ticket='.$jsapi_ticket['ticket'].'&noncestr='.$noncestr.'&timestamp='.$timestamp.'&url='.$url);
		}
		else
		{
			return FALSE;
		}
	}
}

?>