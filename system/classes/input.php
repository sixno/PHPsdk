<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

load_lang('input_label');
load_lang('input_error');
load_funx('input');

class Input
{
	public $rules      = array();
	public $def_method = 'json';
	public $input_data = NULL; //get post json
	public $enter_data = NULL;
	public $input_errs = NULL;

	public $wrap_start = '';
	public $wrap_close = '';

	public function __construct($def_method = '')
	{
		global $_input_data;
		global $_enter_data;
		global $_input_errs;

		$this->input_data = &$_input_data;
		$this->enter_data = &$_enter_data;
		$this->input_errs = &$_input_errs;

		if(!empty($def_method)) $this->def_method = $def_method;
	}

	public function get_original($field,$route = '',$default = '')
	{
		if(empty($route)) $route = $this->def_method;

		return input_original($field,$route,$default);
	}

	public function get_data($item,$default = '')
	{
		if(is_string($item))
		{
			list($field,$force,$route) = (explode(':',$item) + array('','',$this->def_method));

			return input_data($field.':'.$force.':'.$route,$default);
		}
		else
		{
			return input_data($item);
		}
	}

	public function bat_data($items,$limited = FALSE)
	{
		$field = array();

		foreach($items as $item)
		{
			if($limited)
			{
				$pos = strpos($item,':');

				$field[] = $pos ? substr($item,0,$pos) : $item;
			}

			$this->get_data($item);
		}

		return $this->data($field);
	}

	public function data($field = '',$default = '')
	{
		return input_enter_data($field,$default);
	}

	public function set_rule($field = '',$force = '',$route = '',$value = '',$rules = '',$error = '')
	{
		if(is_array($field))
		{
			$this->rules = $field;
		}
		else
		{
			if($route != 'input')
			{
				$this->rules[] = array(
					'field' => $field,
					'force' => $force,
					'route' => $route,
					'value' => $value,
					'rules' => $rules,
					'error' => $error,
				);
			}
			else
			{
				load_conf('input');

				$this->rules = conf('input.'.$field);
			}
		}
	}

	public function run($rule_set = NULL)
	{
		if(!empty($rule_set)) $this->set_rule($rule_set);

		if(empty($this->rules))
		{
			message('No rules have been set.');
		}

		return $this->validate();
	}

	public function validate()
	{
		global $lang;

		$input_error_tpl = &$lang['input_error'];

		$rules  = $this->rules;
		$label  = array();

		foreach($rules as $key => $value)
		{
			if(empty($value['alias']))
			{
				$label[$value['field']] = lang('input_label.'.$value['field']);
			}
			else
			{
				$label[$value['field']] = lang('input_label.'.$value['alias']);
			}

			$this->get_data($value);
		}

		foreach($rules as $key => $value)
		{
			if(empty($value['rules'])) continue;

			$validate_func_array = explode('|',$value['rules']);

			foreach($validate_func_array as $func)
			{
				$para_posi = strpos($func,'[');
				$func_para = array();
				if($para_posi > 0)
				{
					$real_func = substr($func,0,$para_posi);
					$func_para = explode('][',substr($func,$para_posi+1,-1));
				}
				else
				{
					$real_func = $func;
				}

				if(isset($this->enter_data[$value['field']]))
				{
					array_unshift($func_para,$this->enter_data[$value['field']]);
				}
				else
				{
					array_unshift($func_para,FALSE);
				}

				if($real_func == 'needed')
				{
					if(!isset($this->enter_data[$value['field']])) break;

					if($this->enter_data[$value['field']] !== FALSE)
					{
						$real_func = 'required';
					}
					else
					{
						unset($this->enter_data[$value['field']]);

						break;
					}
				}

				if(function_exists($real_func))
				{
					if(!call_user_func_array($real_func,$func_para))
					{
						if(!isset($input_error_tpl[$real_func])) $input_error_tpl[$real_func] = 'The validation function ['.$real_func.'] has no error description';

						$error_tpl = isset($value['error'][$real_func]) ? $value['error'][$real_func] : $input_error_tpl[$real_func];
						$sec_label = $sec_label = !isset($func_para[1]) ? NULL : vo($label[$func_para[1]],$func_para[1]);

						$this->input_errs[$value['field']] = $this->wrap_start.sprintf($error_tpl,$label[$value['field']],$sec_label).$this->wrap_close;
						break;
					}
				}
				else
				{
					$this->input_errs[$value['field']] = $this->wrap_start.'The validation function ['.$real_func.'] does not exist'.$this->wrap_close;

					break;
				}
			}
		}

		return empty($this->input_errs);
	}
}

?>