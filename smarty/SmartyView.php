<?php

class SmartyView {
	
	private $_smarty;
	
	private $_data = array();
	
	private $_content;
	
	private $_languages;
	
	public $version = '0.1';
	
	public $expDate;
	
	public function __construct($content) {
		
		// create object
		$cls = get_called_class();
// 		$this -> maintemplate = 'default';
		$this -> viewtemplate = strtolower(substr(get_called_class(), strrpos(get_called_class(), '\\'), -4)) . '.tpl';
		$this -> _smarty = new \Smarty();
		$ds = DIRECTORY_SEPARATOR;
		$this -> _smarty -> addTemplateDir(BASEPATH . "bright{$ds}site{$ds}templates{$ds}")
						 -> setCacheDir(BASEPATH . "bright{$ds}cache{$ds}smarty")
						 -> setCompileDir(BASEPATH . "bright{$ds}cache{$ds}smarty_c")
						 -> enableSecurity()
						 -> php_handling = \Smarty::PHP_REMOVE;
		
		
		if(is_dir(BASEPATH . "bright{$ds}site{$ds}smarty{$ds}plugins"))
			$this -> _smarty -> addPluginsDir(BASEPATH . "bright{$ds}site{$ds}smarty{$ds}plugins");
		
		$this -> _smarty -> error_reporting = E_ALL & ~E_NOTICE;
		$this -> _smarty -> caching = LIVESERVER;
		$this -> _content = $content;

		$this -> language = $_SESSION['language'];
		
		$this -> _languages = explode(',', AVAILABLELANG);
// 		array_shift($this -> _languages);
		foreach($this -> _content -> page -> content as $key => $value) {
			if(property_exists($this, $key)) {
				$found = false;
				if(isset($value -> {$this->language})) {
					$found = true;
					$this -> {$key} = $value -> {$this->language};
				} else if($this -> _languages) {
					
					foreach($this -> _languages as $lang) {
						if(isset($value -> $lang)) {
							$this -> {$key} = $value -> {$lang};
							$found = true;
							break;
						}
					}
				}
				if(!$found) {
					$this -> {$key} = $value;
				}
			}
		}
		$this -> registerPlugin(SMARTY::PLUGIN_MODIFIER,"dateformat", array($this, "dateformat"));
		$this -> registerPlugin(SMARTY::PLUGIN_FUNCTION,"getUrl", array($this, "getUrl"));
		$this -> registerPlugin(SMARTY::PLUGIN_FUNCTION,"script", array($this, "script"));
		$this -> registerPlugin(SMARTY::PLUGIN_FUNCTION,"css", array($this, "css"));
		$this -> registerPlugin(SMARTY::PLUGIN_FUNCTION,"l10n", array($this, "l10n"));
		
		$this -> registerPlugin(SMARTY::PLUGIN_FUNCTION,"debugger", array($this, "debug_sm"));
		$this -> registerPlugin(SMARTY::PLUGIN_MODIFIER,"toupper", 'strtoupper');
		$this -> registerPlugin(SMARTY::PLUGIN_MODIFIER,"tolower", 'strtoupper');
		
		
		$this -> expDate = time();
		if(isset($content -> page) && isset($content -> page -> lifetime) && !headers_sent()) {
			$this -> expDate = strtotime($content -> page -> lifetime);
			
			// Set Cache header
			header('Expires: ' . date('r', $this -> expDate));
			header('Cache-control: max-age=' . max(0,$this -> expDate - time()));
			header('Last-Modified: ' . date('r', $this -> modificationdate));
		}
		
		register_shutdown_function(array($this, '_fatal_handler'));
	}
	
	public function __set($name, $value) {
		switch ($name) {
			case 'maintemplate':
			case 'viewtemplate':
				if(!BrightUtils::endsWith($value, '.tpl')) {
					$value .= '.tpl';
				}
			break;
		}
		$this -> _data[$name] = $value;
	}
	
	public function __get($name) {
		if(array_key_exists($name, $this -> _data))
			return $this -> _data[$name];
		
		if(isset($this -> _content -> page -> content -> $name)) {
			if(isset($this -> _content -> page -> content -> {$name} -> {$this -> language})) {
				return $this -> _content -> page -> content -> {$name} -> {$this -> language};
			} else {
				foreach($this -> _languages as $lang) {
					if(isset($this -> _content -> page -> content -> {$name} -> {$lang})) {
						return $this -> _content -> page -> content -> {$name} -> {$lang};
					}
				}
				// Object is probably already localized
				return $this -> _content -> page -> content -> {$name};
			}
		
		} else if(isset($this -> _content -> page -> $name)) {
			return $this -> _content -> page ->{$name};
		
		} else if(isset($this -> _content -> $name)) {
			return $this -> _content -> {$name};
		}
		return null;
	}
	
	/**
	 * Gets the url of the current page
	 * @param boolean $relative When true, the domain is omitted
	 * @param boolean $includeParameters When false, the GET parameters are omitted
	 * @return string The url of the current page
	 */
	public function getPageUrl($relative = false, $includeParameters = true) {
		if($relative) {
			$url = '';
		} else {
			$url = 'http';
			if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
				$url .= 's';
			
			$url .= '://' . $_SERVER['SERVER_NAME'];
			
			if(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80) {
				$url .= ':80';
			}
		}
		$url .= $_SERVER['REQUEST_URI'];
		if($includeParameters === false) {
			$ua = explode('?', $url);
			if(count($ua) > 1) {
				array_pop($ua);
			}
			$url = join('?', $ua);
		}
		return $url;
	}
	
	public function output() {
		$this -> _smarty->assign('this', $this);
		// display it
		if(!file_exists(BASEPATH . '/bright/site/templates/' . $this -> viewtemplate))
			$this -> viewtemplate = 'default.tpl';
		
		// Request uri might not be available when called from CLI
		$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		if(strpos($uri, '?'))
			$uri = substr($uri, 0, strpos($uri, '?'));
		
		ob_start();
		$this -> _smarty->registerFilter('output',array($this, '_postFilter'));
		try {
			$this -> _smarty->display(BASEPATH . '/bright/site/templates/' . $this -> viewtemplate,'page_' . $uri . '_' . md5(print_r($_GET, true)));
		} catch(Exception $e) {
			$this -> _smarty -> caching = false;
			$this -> _smarty->display(BASEPATH . '/bright/site/templates/' . $this -> viewtemplate,'tid_' . $this -> treeId . '_' . md5(print_r($_GET, true)));
		}
		return ob_get_clean();
	}
	
	public final function debug($variable) {
		echo '<pre>';
		print_r($variable);
		echo '</pre>';
	}
	
	public final function registerPlugin($type, $name, $method) {
		$this -> _smarty -> registerPlugin($type, $name, $method);
	}

	/**
	 * formats a unix timestamp to a date
	 * Usage:
	 * {$param|dateformat:'FORMAT'}
	 * @param $date
	 * @param $format
	 * @return string
	 */
	public final function dateformat($date, $format) {
		if(!is_numeric($date))
			$date = strtotime($date);
		return strftime($format, $date);
	}
	
	public final function debug_sm($params) {
		$var = empty($params['v']) ? '' : $params['v'];
		
		ob_start();
		$this -> debug($var);
		$o = ob_get_flush();
		return $o;
	}

	/**
	 * Gets an url for the given id
	 * Usage:
	 * {getUrl id=ID_OF_PAGE_TO_FIND [full=boolean] [pathOnly=boolean]}
	 * @param array $params [id=> ## lang=> ##]
	 * @return string
	 */
	public final function getUrl($params) {
		$id = empty($params['id']) ? 1 : $params['id'];
		$full = empty($params['full']) || $params['full'] == false ? false : true;
		$pathOnly = empty($params['pathOnly']) || $params['pathOnly'] == false ? false : true;
		$t = new Tree();
		$path = $full ? BASEURL : '';
		if(USEPREFIX) {
			$lang = empty($params['lang']) || !in_array($params['lang'], $this->_languages) ? $this -> language : $params['lang'];
			$path = $lang . '/';
		}

		if($pathOnly)
			$path = '';
			
		return $path . $t -> getPath($id);
	}
	
	public final function script($params) {
		$src = empty($params['src']) ? false : $params['src'];
		if($src) {
			$min = empty($params['min']) ? true : $params['min'];

            if(LIVESERVER && MINIFYAVAILABLE && $min)
                return str_replace('//', '/', "<script src='/min/{$src}?v={$this->version}'></script>");

            if(LIVESERVER && (!MINIFYAVAILABLE || !$min))
                return str_replace('//', '/', "<script src='{$src}?v={$this->version}'></script>");
            $ts = time();
            return "<script src='{$src}?v=$ts'></script>";

		}
	}
	public final function css($params) {
		$src = empty($params['src']) ? false : $params['src'];
		if($src) {
			$min = empty($params['min']) ? true : $params['min'];
			$media = empty($params['media']) ? 'all' : $params['media'];
			if(LIVESERVER && MINIFYAVAILABLE && $min)
				return str_replace('//', '/', "<link rel='stylesheet' type='text/css' media='$media' href='/min/{$src}?v={$this->version}' />");

            if(LIVESERVER && (!MINIFYAVAILABLE || !$min))
                return str_replace('//', '/', "<link rel='stylesheet' type='text/css' media='$media' href='{$src}?v={$this->version}' />");

            $ts = time();
			return "<link rel='stylesheet' type='text/css' media='$media' href='{$src}?v=$ts' />";

		}
	}
	
	public final function l10n($params) {
		$var = empty($params['name']) ? false : $params['name'];
		if($var) {
			$var = strtoupper($var);
			return Resources::getResource($var, $this -> language);
		}
		return '';
	}
	
	public function _fatal_handler() {
		$ex = error_get_last();
		if($ex['type'] === E_ERROR) {
			header('HTTP/1.1 500 Internal Server Error'	);
			echo '<pre>FATAL ERROR:' . "\r\n";
			echo  trim($ex['message']) . '</pre>';
			exit;
		}
	}

	/**
	 * @private
	 * @param unknown_type $tpl_source
	 * @param Smarty_Internal_Template $template
	 * @return mixed|\unknown_type
	 */
	public final function _postFilter($tpl_source, Smarty_Internal_Template $template) {
		$tpl_source = preg_replace_callback('/\/index\.php\?tid=([0-9]*)/ism', array($this, '_buildpaths'), $tpl_source);
		$tpl_source = preg_replace_callback('/\/index\.php\?rtid=([0-9]*)/ism', array($this, '_buildrelativepaths'), $tpl_source);
		return $tpl_source;
	}
	
	private function _buildpaths($matches) {
		return BASEURL . $this -> _buildpath($matches);
	}
	
	
	private function _buildrelativepaths($matches) {
		return '/' . $this -> _buildpath($matches);
	}
	
	function _buildpath($matches) {
		$tid = $matches[1];
		if(USEPREFIX && (!$this -> prefix || $this -> prefix == '')) {
			$langs = explode(',', AVAILABLELANG);
			$this -> prefix = $langs[0] . '/';
		}
		$t = new Tree();
		return $this -> prefix . $t -> getPath((int)$tid);
	}
}