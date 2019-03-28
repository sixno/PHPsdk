<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

load_conf('aliyun');

class Aliyun_sms
{
	public $apidomain = '';
	public $accesskey = '';
	public $secretkey = '';

	public function __construct($config = array())
	{
		$pre_config = conf('aliyun.sms');

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

	public function send($signName,$templateCode,$phoneNumbers,$templateParam = NULL,$outId = NULL,$smsUpExtendCode = NULL)
	{
		$params = array(
			'RegionId'     => 'cn-hangzhou',
			'Action'       => 'SendSms',
			'Version'      => '2017-05-25',
			'PhoneNumbers' => $phoneNumbers,
			'SignName'     => $signName,
			'TemplateCode' => $templateCode,
		);

		if($templateParam) $params['TemplateParam'] = is_array($templateParam) ? json_encode($templateParam) : $templateParam;

		if($outId) $params['OutId'] = $outId;

		if($smsUpExtendCode) $params['SmsUpExtendCode'] = $smsUpExtendCode;

		return $this->_request($params);
	}

	public function send_list($phoneNumbers,$sendDate,$pageSize = 10,$currentPage = 1,$bizId = NULL)
	{
		$params = array(
			'RegionId'    => 'cn-hangzhou',
			'Action'      => 'QuerySendDetails',
			'Version'     => '2017-05-25',
			'PhoneNumber' => $phoneNumbers,
			'SendDate'    => $sendDate,
			'PageSize'    => $pageSize,
			'CurrentPage' => $currentPage,
		);

		if($bizId) $params['BizId'] = $bizId;

		return $this->_request($params);
	}

	public function _request($para)
	{
		$base = array(
			'SignatureMethod'  => 'HMAC-SHA1',
			'SignatureNonce'   => uniqid(mt_rand(0,0xffff),TRUE),
			'SignatureVersion' => '1.0',
			'AccessKeyId'      => $this->accesskey,
			'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
			'Format'           => 'JSON',
		);

		$para = array_merge($base,$para);

		ksort($para);

		$para_str = '';

		foreach($para as $key => $val)
		{
			$para_str .= '&'.$this->_encode($key).'='.$this->_encode($val);
		}

		$sign_str = 'GET&%2F&'.$this->_encode(substr($para_str,1));

		$sign = base64_encode(hash_hmac('sha1',$sign_str,$this->secretkey.'&',TRUE));

		$url = 'http://'.$this->apidomain.'/?Signature='.$this->_encode($sign).$para_str;

		$result = $this->_curl($url);

		return json_decode($result,TRUE);
	}

	public function _encode($str)
	{
		$res = urlencode($str);
		$res = preg_replace('/\+/', '%20', $res);
		$res = preg_replace('/\*/', '%2A', $res);
		$res = preg_replace('/%7E/', '~', $res);

		return $res;
	}

	public function _curl($url)
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_HTTPHEADER,['x-sdk-client: php/2.0.0']);

		$re = curl_exec($ch);

		curl_close($ch);

		return $re;
	}
}

?>