<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

load_conf('rsa');

class Rsa
{
	public $key = array();

	public function __construct($config = array())
	{
		if(!empty($config))
		{
			list($pair) = $config + array('');

			$this->set_key($pair);
		}
	}

	public function set_key($pair)
	{
		if(empty($pair)) return FALSE;

		$this->key = conf('rsa.'.$pair);

		return TRUE;
	}

	public function encrypt($dec,$type = 'public',$safe = FALSE)
	{
		if($dec == '') return '';
		if(empty($this->key[$type.'_key'])) return '';

		$enc = '';

		switch ($type)
		{
			case 'public':
				$public_key = openssl_pkey_get_public($this->key['public_key']);

				openssl_public_encrypt($dec,$enc,$public_key);
				break;
			
			case 'private':
				$private_key = openssl_pkey_get_private($this->key['private_key']);

				openssl_private_encrypt($dec,$enc,$private_key);
				break;
			
			default:
				return '';
				break;
		}

		if($enc != '')
		{
			$enc = base64_encode($enc);

			if($safe) $enc = str_replace(['+','/'],['-','_'],$enc);
		}

		return $enc;
	}

	public function decrypt($enc,$type = 'private',$safe = FALSE)
	{
		if($enc == '') return '';
		if(empty($this->key[$type.'_key'])) return '';

		if($safe) $enc = str_replace(['-','_'],['+','/'],$enc);

		$enc = base64_decode($enc);
		$dec = '';

		switch($type)
		{
			case 'private':
				$private_key = openssl_pkey_get_private($this->key['private_key']);

				openssl_private_decrypt($enc,$dec,$private_key);
				break;
			
			case 'public':
				$public_key = openssl_pkey_get_public($this->key['public_key']);

				openssl_public_decrypt($enc,$dec,$public_key);
				break;
			
			default:
				return '';
				break;
		}

		return $dec;
	}
}

?>