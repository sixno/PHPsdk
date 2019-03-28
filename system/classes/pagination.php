<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

load_lang('pagination');

class Pagination
{
	var $base_url             = ''; // The page we are linking to
	var $prefix               = ''; // A custom prefix added to the path.
	var $suffix               = ''; // A custom suffix added to the path.

	var $total_rows           =  0; // Total number of items (database results)
	var $per_page             = 20; // Max number of items you want shown per page
	var $num_links            =  4; // Number of "digit" links to show before/after the currently viewed page
	var $cur_page             =  0; // The current page being viewed
	var $use_page_numbers     = TRUE; // Use page number for segment instead of offset
	var $first_link           = '首页';
	var $next_link            = '下一页';
	var $prev_link            = '上一页';
	var $last_link            = '末页';
	var $uri_segment          =  3;
	var $full_tag_open        = '';
	var $full_tag_close       = '';
	var $first_tag_open       = '';
	var $first_tag_close      = '&nbsp;';
	var $last_tag_open        = '&nbsp;';
	var $last_tag_close       = '';
	var $first_url            = ''; // Alternative URL for the First Page.
	var $cur_tag_open         = '<strong>';
	var $cur_tag_close        = '</strong>';
	var $next_tag_open        = '&nbsp;';
	var $next_tag_close       = '&nbsp;';
	var $prev_tag_open        = '&nbsp;';
	var $prev_tag_close       = '';
	var $num_tag_open         = '&nbsp;';
	var $num_tag_close        = '';
	var $page_query_string    = FALSE;
	var $query_string_segment = 'page';
	var $display_pages        = TRUE;
	var $anchor_class         = '';

	public function __construct($config = array())
	{
		if(empty($config))
		{
			$config = conf('pagination',array());
		}
		else
		{
			$config = array_merge(conf('pagination',array()),$config);
		}

		if (count($config) > 0)
		{
			$this->initialize($config);
		}

		if ($this->anchor_class != '')
		{
			$this->anchor_class = 'class="'.$this->anchor_class.'" ';
		}
	}

	public function initialize($params = array())
	{
		if (count($params) > 0)
		{
			foreach ($params as $key => $val)
			{
				if (isset($this->$key))
				{
					$this->$key = $val;
				}
			}
		}
	}

	public function create_links($cur_page = FALSE)
	{
		// If our item count or per-page total is zero there is no need to continue.
		if ($this->total_rows == 0 OR $this->per_page == 0)
		{
			return '';
		}

		// Calculate the total number of pages
		$num_pages = ceil($this->total_rows / $this->per_page);

		// Is there only one page? Hm... nothing more to do here then.
		if ($num_pages == 1)
		{
			return '';
		}

		// Set the base page index for starting page number
		if ($this->use_page_numbers)
		{
			$base_page = 1;
		}
		else
		{
			$base_page = 0;
		}

		// Determine the current page number.
		if($cur_page === FALSE)
		{
			if ($this->page_query_string === TRUE)
			{
				if (get($this->query_string_segment) != $base_page)
				{
					$this->cur_page = get($this->query_string_segment);

					// Prep the current page - no funny business!
					$this->cur_page = (int) $this->cur_page;
				}
			}
			else
			{
				if (uri_segment($this->uri_segment) != $base_page)
				{
					$this->cur_page = uri_segment($this->uri_segment);

					// Prep the current page - no funny business!
					$this->cur_page = (int) $this->cur_page;
				}
			}
		}
		else
		{
			$this->cur_page = (int)$cur_page;
		}

		// Set current page to 1 if using page numbers instead of offset
		if ($this->use_page_numbers AND $this->cur_page == 0)
		{
			$this->cur_page = $base_page;
		}

		$this->num_links = (int)$this->num_links;

		if ($this->num_links < 1)
		{
			message('Your number of links must be a positive number.');
		}

		if ( ! is_numeric($this->cur_page))
		{
			$this->cur_page = $base_page;
		}

		// Is the page number beyond the result range?
		// If so we show the last page
		if ($this->use_page_numbers)
		{
			if ($this->cur_page > $num_pages)
			{
				$this->cur_page = $num_pages;
			}
		}
		else
		{
			if ($this->cur_page > $this->total_rows)
			{
				$this->cur_page = ($num_pages - 1) * $this->per_page;
			}
		}

		$uri_page_number = $this->cur_page;
		
		if ( ! $this->use_page_numbers)
		{
			$this->cur_page = floor(($this->cur_page/$this->per_page) + 1);
		}

		// Calculate the start and end numbers. These determine
		// which number to start and end the digit links with
		$start = (($this->cur_page - $this->num_links) > 0) ? $this->cur_page - ($this->num_links - 1) : 1;
		$end   = (($this->cur_page + $this->num_links) < $num_pages) ? $this->cur_page + $this->num_links : $num_pages;

		// Is pagination being used over GET or POST?  If get, add a per_page query
		// string. If post, add a trailing slash to the base URL if needed
		if ($this->page_query_string === TRUE)
		{
			$vp = strpos($this->base_url, '?') === FALSE ? '?' : '&';
			$rtrim_str = $vp.$this->query_string_segment.'=';
			$this->base_url = rtrim($this->base_url,'/').$rtrim_str;
			$this->first_url = $this->base_url.$base_page;
		}
		else
		{
			$rtrim_str = '/';
			$this->base_url = rtrim($this->base_url,'/').$rtrim_str;
			// $this->first_url = $this->base_url.$base_page;
		}

		// And here we go...
		$output = '';

		// Render the "First" link
		if  ($this->first_link !== FALSE AND $this->cur_page > ($this->num_links + 1))
		{
			$first_url = ($this->first_url == '') ? rtrim($this->base_url,$rtrim_str) : $this->first_url;
			$output .= $this->first_tag_open.'<a '.$this->anchor_class.'href="'.$first_url.($this->use_page_numbers && $this->query_string_segment == 'line' ? ','.$num_pages : '').'">'.$this->first_link.'</a>'.$this->first_tag_close;
		}

		// Render the "previous" link
		if  ($this->prev_link !== FALSE AND $this->cur_page != 1)
		{
			if ($this->use_page_numbers)
			{
				$i = $uri_page_number - 1;
			}
			else
			{
				$i = $uri_page_number - $this->per_page;
			}

			if ($i == 0 && $this->first_url != '')
			{
				$output .= $this->prev_tag_open.'<a '.$this->anchor_class.'href="'.$this->first_url.($this->use_page_numbers && $this->query_string_segment == 'line' ? ','.$num_pages : '').'">'.$this->prev_link.'</a>'.$this->prev_tag_close;
			}
			else
			{
				$i = ($i == 0) ? '' : $this->prefix.$i.$this->suffix;

				$output .= $this->prev_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$i.($this->use_page_numbers && $this->query_string_segment == 'line' ? ','.$num_pages : '').'">'.$this->prev_link.'</a>'.$this->prev_tag_close;
			}

		}

		// Render the pages
		if ($this->display_pages !== FALSE)
		{
			// Write the digit links
			for ($loop = $start -1; $loop <= $end; $loop++)
			{
				if ($this->use_page_numbers)
				{
					$i = $loop;
				}
				else
				{
					$i = ($loop * $this->per_page) - $this->per_page;
				}

				if ($i >= $base_page)
				{
					if ($this->cur_page == $loop)
					{
						$output .= $this->cur_tag_open.$loop.$this->cur_tag_close; // Current page
					}
					else
					{
						$n = ($i == $base_page) ? '' : $i;

						if ($n == '' && $this->first_url != '')
						{
							$output .= $this->num_tag_open.'<a '.$this->anchor_class.'href="'.$this->first_url.($this->use_page_numbers && $this->query_string_segment == 'line' ? ','.$num_pages : '').'">'.$loop.'</a>'.$this->num_tag_close;
						}
						else
						{
							$n = ($n == '') ? '' : $this->prefix.$n.$this->suffix;

							$output .= $this->num_tag_open.'<a '.$this->anchor_class.'href="'.rtrim($this->base_url.$n,$rtrim_str).($this->use_page_numbers && $this->query_string_segment == 'line' ? ','.$num_pages : '').'">'.$loop.'</a>'.$this->num_tag_close;
						}
					}
				}
			}
		}

		// Render the "next" link
		if ($this->next_link !== FALSE AND $this->cur_page < $num_pages)
		{
			if ($this->use_page_numbers)
			{
				$i = $this->cur_page + 1;
			}
			else
			{
				$i = ($this->cur_page * $this->per_page);
			}

			$output .= $this->next_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$this->prefix.$i.$this->suffix.($this->use_page_numbers && $this->query_string_segment == 'line' ? ','.$num_pages : '').'">'.$this->next_link.'</a>'.$this->next_tag_close;
		}

		// Render the "Last" link
		if ($this->last_link !== FALSE AND ($this->cur_page + $this->num_links) < $num_pages)
		{
			if ($this->use_page_numbers)
			{
				$i = $num_pages;
			}
			else
			{
				$i = (($num_pages * $this->per_page) - $this->per_page);
			}
			$output .= $this->last_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$this->prefix.$i.$this->suffix.($this->use_page_numbers && $this->query_string_segment == 'line' ? ','.$num_pages : '').'">'.$this->last_link.'</a>'.$this->last_tag_close;
		}

		// Kill double slashes.  Note: Sometimes we can end up with a double slash
		// in the penultimate link so we'll kill all double slashes.
		$output = preg_replace("#([^:])//+#", "\\1/", $output);

		// Add the wrapper HTML if exists
		$output = $this->full_tag_open.$output.$this->full_tag_close;

		return $output;
	}

	public function links($total,$per,$seg = 0,$p = FALSE)
	{
		$conf = array();
		$conf['display_pages'] = TRUE;

		$cur_page = FALSE;

		//path of [pagins()] : ./system/core/common.php
		//here the "seg" is "prev" in [pagins()]

		if($seg === FALSE)
		{
			global $uri_arr;

			$base = NULL;
			for($i = 1;$i < count($uri_arr);$i++)
			{
				$base .= $uri_arr[$i].'/';
			}
			$base = substr($base,0,-1);
			$base = site_url($base);

			$conf['base_url'] = $base;
			$conf['total_rows'] = $total;
			$conf['per_page'] = $per;
			$conf['page_query_string'] = TRUE;
		}
		elseif(is_numeric($seg))
		{
			$seg = pagins($seg,0);

			$base = NULL;
			for($i = 1;$i < $seg;$i++)
			{
				$base .= uri_segment($i).'/';
			}
			$base = substr($base,0,-1);
			$base = site_url($base);

			$conf['uri_segment'] = $seg;
			$conf['base_url'] = $base;
			$conf['total_rows'] = $total;
			$conf['per_page'] = $per;
		}
		else
		{
			$conf['base_url'] = $seg;
			$conf['total_rows'] = $total;
			$conf['per_page'] = $per;

			if($p === FALSE)
			{
				$cur_page = pagins(0,1);
			}
			elseif(is_int($p))
			{
				$cur_page = $p;
			}
			else
			{
				$cur_page = pagins((int)$p,1);
			}
		}

		if(!empty($this->base_url)) unset($conf['base_url']);

		$this->initialize($conf);

		return $this->create_links($cur_page);
	}

	public function condition_links($total,$per,$seg = 0,$p = FALSE)
	{
		if(empty($_POST) && empty($_GET))
		{
			return $this->links($total,$per,$seg,$p);
		}
		else
		{
			$condition = array_merge($_GET,$_POST);

			if(isset($condition['token'])) unset($condition['token']);
			if(isset($condition['p'])) unset($condition['p']);
			
			$condition_str = http_build_query($condition);

			if($seg !== FALSE)
			{
				$condition_str = '?'.$condition_str;
			}
			else
			{
				$condition_str = '&'.$condition_str;
			}

			$links = $this->links($total,$per,$seg,$p);

			return preg_replace('/href="(.*?)"/','href="$1'.$condition_str.'"', $links);
		}
	}
}

?>