<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

class Model
{
	public $load = NULL;

	public $table = '';
	public $model = [];

	public $mysql_conf = [];
	public $redis_conf = [];

	public function __construct($table = '',$conf = [])
	{
		$this->load = new Loader($this);

		if($this->table == '') $this->table = strtolower(get_class($this));
		if($table != '') $this->table = $table;

		if(isset($conf['mysql']))
		{
			$this->mysql_conf = $conf['mysql'];

			unset($conf['mysql']);
		}

		if(isset($conf['redis']))
		{
			$this->redis_conf = $conf['redis'];

			unset($conf['redis']);
		}
	}

	public function __get($property)
	{
		switch($property)
		{
			case 'mysql':
				if(empty($this->mysql_conf))
				{
					$this->load->mysql();
				}
				else
				{
					$this->load->mysql($this->mysql_conf);
				}
				break;
			
			case 'redis':
				if(empty($this->redis_conf))
				{
					$this->load->redis();
				}
				else
				{
					$this->load->redis($this->redis_conf);
				}
				break;
			
			default:
				if(empty($this->model))
				{
					$this->load->model($property,'core');
				}
				elseif(isset($this->model[$property]))
				{
					if(is_string($this->model[$property]))
					{
						$this->load->model($this->model[$property],$property);
					}
					else
					{
						if(isset($this->model[$property]['@']))
						{
							$this->load->model($this->model[$property]['@'],$property,$this->model[$property]);
						}
						else
						{
							$this->load->model($property,$this->model[$property]);
						}
					}
				}
				else
				{
					if(array_search($property,$this->model) === FALSE)
					{
						$this->load->model($property,'core');
					}
					else
					{
						$this->load->model($property);
					}
				}
				break;
		}

		return $this->$property;
	}

	public function escape($str)
	{
		return $this->mysql->real_escape_string($str);
	}

	public function where($map = FALSE)
	{
		if($map !== FALSE)
		{
			if(is_array($map))
			{
				$sql        = '';
				$need_logic = FALSE;

				foreach($map as $key => $val)
				{
					$front_char = substr($key,0,1);

					if($front_char == '#') continue;

					if($sql == '') $sql = 'WHERE ';

					if($front_char == '^')
					{
						$sql .= $val.' ';

						continue;
					}

					if(FALSE === $pos = strpos($key,'#'))
					{
						$logic = 'and';
					}
					else
					{
						if($pos == 0) continue;

						$logic = substr($key,0,$pos);
						$pos = strrpos($key,'#');
						$key = substr($key,$pos+1);
					}

					$key = str_replace(array(' ','\'','"'),'',$key);

					$chr = strpos($key,'`') === FALSE ? '`' : '';
					$chl = strlen($chr);

					$key = $chr.((strpos($key,'.') === FALSE) ? $key : ($chr != '' ? str_replace('.',$chr.'.'.$chr,$key) : $key)).$chr;

					switch(substr($key,-1-$chl,1))
					{
						case '=':
							switch (substr($key,-2-$chl,2))
							{
								case '<=':
									$key = substr($key,0,-2-$chl).$chr.' <= ';
									break;
								
								case '>=':
									$key = substr($key,0,-2-$chl).$chr.' >= ';
									break;
								
								case '!=':
									$key = substr($key,0,-2-$chl).$chr.' != ';
									break;
								
								default:
									$key = substr($key,0,-1-$chl).$chr.' = ';
									break;
							}
							break;
						
						case '<':
							$key = substr($key,0,-1-$chl).$chr.' < ';
							break;
						
						case '>':
							$key = substr($key,0,-1-$chl).$chr.' > ';
							break;
						
						default:
							$key = ($chl ? substr($key,0,-$chl) : $key).$chr.' = ';
							break;
					}

					if(strpos($logic,'in') === FALSE)
					{
						if(is_array($val)) continue;

						if($val !== NULL)
						{
							$val = '\''.$this->escape($val).'\'';

							if(strpos($logic,'like') !== FALSE)
							{
								$val = str_replace('%','\%',$val);
							}
						}
						else
						{
							if(strpos($key,'!=') === FALSE)
							{
								$key = str_replace(' = ',' IS ',$key);
							}
							else
							{
								$key = str_replace(' != ',' IS NOT ',$key);
							}

							$val = 'NULL';
						}
					}
					else
					{
						if(is_array($val))
						{
							if(count($val) != count($val,1)) continue;

							foreach($val as &$v)
							{
								$v = $this->escape($v);
							}

							$val = '(\''.implode('\',\'',$val).'\')';
						}
						else
						{
							$val = '(\''.str_replace(',','\',\'',$this->escape($val)).'\')';
						}
					}

					switch($logic)
					{
						case 'and':
							$lgc = 'AND';
							break;

						case 'or':
							$lgc = 'OR';
							break;

						case 'like':
							$lgc = 'AND';

							$key = str_replace(' = ',' LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='],$key));

							if(strpos($val,'REVERSE(') === FALSE)
							{
								$val = substr_count($val,'%') != substr_count($val,'\\%') ? $val : '\'%'.trim($val,'\'').'%\'';
							}
							else
							{
								$val = substr(str_replace('\\','',$val),1,-1);
							}
							break;

						case 'or_like':
							$lgc = 'OR';

							$key = str_replace(' = ',' LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='],$key));

							if(strpos($val,'REVERSE(') === FALSE)
							{
								$val = substr_count($val,'%') != substr_count($val,'\\%') ? $val : '\'%'.trim($val,'\'').'%\'';
							}
							else
							{
								$val = substr(str_replace('\\','',$val),1,-1);
							}
							break;

						case 'not_like':
							$lgc = 'AND';

							$key = str_replace(' = ',' NOT LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='],$key));

							if(strpos($val,'REVERSE(') === FALSE)
							{
								$val = substr_count($val,'%') != substr_count($val,'\\%') ? $val : '\'%'.trim($val,'\'').'%\'';
							}
							else
							{
								$val = substr(str_replace('\\','',$val),1,-1);
							}
							break;

						case 'or_not_like':
							$lgc = 'OR';

							$key = str_replace(' = ',' NOT LIKE ',strpos($key,',') === FALSE ? $key : 'CONCAT_WS(\' \','.str_replace([',',' ='],['`,`',') ='],$key));

							if(strpos($val,'REVERSE(') === FALSE)
							{
								$val = substr_count($val,'%') != substr_count($val,'\\%') ? $val : '\'%'.trim($val,'\'').'%\'';
							}
							else
							{
								$val = substr(str_replace('\\','',$val),1,-1);
							}
							break;

						case 'in':
							$lgc = 'AND';

							if(strpos($val,',') !== FALSE)
							{
								$key = str_replace(' = ',' IN ',$key);
							}
							else
							{
								$val = substr($val,1,-1);
							}
							break;

						case 'or_in':
							$lgc = 'OR';

							if(strpos($val,',') !== FALSE)
							{
								$key = str_replace(' = ',' IN ',$key);
							}
							else
							{
								$val = substr($val,1,-1);
							}
							break;

						case 'not_in':
							$lgc = 'AND';

							if(strpos($val,',') !== FALSE)
							{
								$key = str_replace(' = ',' NOT IN ',$key);
							}
							else
							{
								$key = $key.' != ';
								$val = substr($val,1,-1);
							}
							break;

						case 'or_not_in':
							$lgc = 'OR';

							if(strpos($val,',') !== FALSE)
							{
								$key = str_replace(' = ',' NOT IN ',$key);
							}
							else
							{
								$key = $key.' != ';
								$val = substr($val,1,-1);
							}
							break;

						default:
							$lgc = 'AND';
							break;
					}

					$adjust = '';

					while(substr($sql,-2) == '( ')
					{
						$sql = substr($sql,0,-2);

						$adjust .= '( ';
					}

					if($need_logic)
					{
						$sql .= $lgc.' '.$adjust.$key.$val;
					}
					else
					{
						$need_logic = TRUE;

						$sql .= $adjust.$key.$val;
					}

					$sql .= ' ';
				}

				return $sql;
			}
			else
			{
				return 'WHERE `'.$this->table.'`.`id` = \''.$this->escape($map).'\'';
			}
		}
		else
		{
			return '';
		}
	}

	public function count($map = array())
	{
		$sql = 'SELECT COUNT(*) FROM '.$this->table.' ';

		if(isset($map['#unite']))
		{
			foreach(explode(';',$map['#unite']) as $join_str)
			{
				$join_arr = explode(',',$join_str);

				if(isset($join_arr[2]))
				{
					$sql .= strtoupper($join_arr[2]).' JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'],$join_arr[1]).'` ';
				}
				else
				{
					$sql .= 'JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'],$join_arr[1]).'` ';
				}
			}
		}

		$sql .= $this->where($map);

		$result = $this->mysql->query($sql)->fetch_row();

		return $result[0];
	}

	public function create($data,$batch = FALSE)
	{
		$sql = '';

		if($batch === FALSE)
		{
			$sql = 'INSERT INTO `'.$this->table.'` ';

			$field = '';
			$value = '';

			foreach($data as $key => $val)
			{
				$field .= '`'.$key.'`,';
				$value .= '\''.$this->escape($val).'\',';
			}

			$sql .= '('.substr($field,0,-1).') VALUES ('.substr($value,0,-1).')';

			$result = $this->mysql->query($sql);

			return $this->mysql->insert_id ? $this->mysql->insert_id : $result;
		}
		else
		{
			$sql = 'INSERT IGNORE INTO `'.$this->table.'` ';

			$field = '';
			$value = '';

			foreach($data as $k => $v)
			{
				$tmp = '';

				foreach($v as $key => $val)
				{
					if($k == 0)
					{
						$field .= '`'.$key.'`,';
					}

					$tmp .= '\''.$this->escape($val).'\',';
				}

				$value .= '('.substr($tmp,0,-1).'), ';
			}

			$sql .= '('.substr($field,0,-1).') VALUES '.substr($value,0,-2);

			$result = $this->mysql->query($sql);

			return $result ? $this->mysql->affected_rows : $result;
		}
	}

	public function update($where = array(),$data = array())
	{
		$sql = 'UPDATE `'.$this->table.'` SET ';

		foreach($data as $key => $value)
		{
			$sql .= '`'.$key.'` = \''.$this->escape($value).'\',';
		}

		$sql = trim($sql,',').' ';

		$sql .= $this->where($where);

		$result = $this->mysql->query($sql);

		return $result;
	}

	public function delete($map = array())
	{
		$sql = 'DELETE FROM `'.$this->table.'` ';

		$sql .= $this->where($map);

		$result = $this->mysql->query($sql);

		return $result;
	}

	public function read($map = array(),$where = '',$limit = '',$order = '')
	{
		$sql = '';

		if(!is_array($map))
		{
			$field = $map;

			$map = array();

			if($field != '') $map['#field'] = $field;

			if(is_array($where))
			{
				$map = array_merge($map,$where);
			}
			else
			{
				$order = $limit;
				$limit = $where;
			}

			if($limit != '') $map['#limit'] = $limit;
			if($order != '') $map['#order'] = $order;
		}

		if(isset($map['#field']))
		{
			$map['#field'] = '`'.str_ireplace(array('.',' AS ',','),array('`.`','` AS `','`,`'),$map['#field']).'`';
			$map['#field'] = str_replace(['``','`*`'],['','*'],$map['#field']);
			$map['#field'] = str_ireplace('`distinct ','DISTINCT `',$map['#field']);

			$sql = 'SELECT '.$map['#field'].' FROM `'.$this->table.'` ';
		}
		else
		{
			$sql = 'SELECT * FROM `'.$this->table.'` ';
		}

		if(isset($map['#unite']))
		{
			foreach(explode(';',$map['#unite']) as $join_str)
			{
				$join_arr = explode(',',$join_str);

				if(isset($join_arr[2]))
				{
					$sql .= strtoupper($join_arr[2]).' JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'],$join_arr[1]).'` ';
				}
				else
				{
					$sql .= 'JOIN `'.$join_arr[0].'` ON `'.str_replace([' ','.','='],['','`.`','` = `'],$join_arr[1]).'` ';
				}
			}
		}

		$sql .= $this->where($map);

		if(isset($map['#order']))
		{
			$sql .= 'ORDER BY `'.str_ireplace([' ','desc','asc','.',',',';'],['','DESC','ASC','`.`','` ',',`'],$map['#order']).' ';

			$sql = str_ireplace(['`rand()','rand()`'],['RAND()','RAND()'],$sql);
		}

		if(isset($map['#limit']))
		{
			list($limit,$ofset) = explode(',',$map['#limit']) + [0,0];

			$limit = (int)$limit;
			$ofset = (int)$ofset;

			if($limit > 0) $sql .= 'LIMIT '.$ofset.','.$limit.' ';
		}

		$result = $this->mysql->query($sql);

		if($result)
		{
			if(isset($map['#indby']))
			{
				$data = array();

				foreach($result->fetch_all(MYSQLI_ASSOC) as $key => $value)
				{
					$data[$value[$map['#indby']]] = $value;
				}

				return $data;
			}
			else
			{
				return $result->fetch_all(MYSQLI_ASSOC);
			}
		}
		else
		{
			return $result;
		}
	}

	public function find($map = array(),$where = array(),$order = '',$limit = '')
	{
		if(!is_array($map))
		{
			$field = $map;

			$map = array();

			if($field != '') $map['#field'] = $field;

			if(is_array($where))
			{
				if(!empty($where)) $map = array_merge($map,$where);
			}
			else
			{
				$map['id'] = $where;
			}

			if($order != '') $map['#order'] = $order;
			if($limit != '') $map['#limit'] = '1,0';
		}
		else
		{
			if(empty($map['#limit'])) $map['#limit'] = '1,0';
		}

		$list = $this->read($map);

		if(!empty($list))
		{
			$data = array_shift($list);

			return (count($data) > 1) ? $data : array_pop($data);
		}
		else
		{
			return FALSE;
		}
	}

	public function increase($where = '',$item = '',$step = 0)
	{
		if($step == 0 && is_numeric($item))
		{
			$step = $item;
			$item = $where;

			$where = '';
		}

		$sql = 'UPDATE `'.$this->table.'` SET ';

		if(is_string($item))
		{
			if($step == 0) return FALSE;

			foreach(explode(',',$item) as $key => $value)
			{
				$sql .= '`'.$value.'` = `'.$value.'`'.($step > 0 ? '+'.$step : (string)$step).', ';
			}
		}
		else
		{
			foreach($item as $key => $value)
			{
				$sql .= '`'.$key.'` = `'.$key.'`'.($value > 0 ? '+'.$value : (string)$value).', ';
			}
		}

		$sql = substr($sql,0,-2).' '.$this->where($where);

		$result = $this->mysql->query($sql);

		return $result;
	}
}

?>