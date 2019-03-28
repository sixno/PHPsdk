<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

class Rar
{
	var $rardata	= '';
	var $directory	= '';
	var $entries	= 0;
	var $file_num	= 0;
	var $offset		= 0;
	var $now;

	public function __construct()
	{
		$this->now = time();
	}

	public function extract_data($rarfile,$to = './')
	{
		$rar_file = rar_open($rarfile);

		if(!$rar_file) return FALSE;

		$list = rar_list($rar_file);

		if(!$list) return FALSE;

		foreach($list as $file)
		{
			preg_match('/\".*\"/',$file,$matches,PREG_OFFSET_CAPTURE);  

			$path = $matches[0][0];  
			$path = str_replace("\"",'',$path);  

			$entry = rar_entry_get($rar_file,$path);

			if(!$entry) return FALSE;

			$entry->extract($to);
		}

		rar_close($rar_file);

		return TRUE;
	}
}

?>