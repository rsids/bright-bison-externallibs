<?php
use BrightSearch\Utils;

include_once dirname(__FILE__) . '/../../library/Bright/Bright.php';


class Migrate {
	
	private $arrays = array();
	private $curarray = array();
	private $headerset = false;
	private $footerset = false;
	private $fname = '';
	private $tpl = '';
	
	function __construct() {
		$templates = glob(BASEPATH . 'bright/site/templates/*.html');
		
		foreach($templates as $template) {
			// Get contents of file
			$this -> tpl = file_get_contents($template);
			///<!--[ ]*###([A-z0-9_\.\,\-\:\/ ]*)###[ ]*-->\r*\n*/
			
			$fname = explode('/', $template);
			$fname = array_pop($fname);
			$this -> fname = substr($fname, 0, -5);
			
			$this -> arrays = array();
			$this -> curarray = array();
			$this -> headerset = false;
			$this -> footerset = false;
			
			$this -> tpl = preg_replace_callback('/<!--[ ]*###([A-z0-9_\.\,\-\:\/ ]*)###[ ]*-->/', function ($ma) {

				$m = explode('_', $ma[1]);
				$isif = false;
				
				$type = array_shift($m);
				
				if(count($m) > 0) {
					switch($m[count($m)-1]) {
						case 'IF':
						case 'START':
							$isif = $type == 'V' || $type == 'A' || $type == 'F';
							break;
						case 'ELSE':
							return '{else}';
						case 'ENDIF':
							return '{/if}';
						case 'END':
							if($type == 'V' || $type == 'A' || $type == 'F')
								return '{/if}';
					}
				}
				switch($type) {
					case 'HEADER':
						if(!$this -> headerset) {
							$this -> headerset = true;
							return '{block "header"}';
						}
						return '{/block}';
						
					case 'FOOTER':
						if(!$this -> footerset) {
							$this -> footerset = true;
							return '{block "footer"}';
						}
						return '{/block}';
					case 'TEMPLATE':
						if($m[0] == 'START') {
							return '{block "template"}';
						} else if($m[0] == 'END' || count($m) > 1 && $m[1] == 'END') {
							$ret = '{/block}';
							if(count($m) > 1)
								$ret .= '{*End of block "' .$this -> fname . '.' . $m[0]. '" *}'; 
							return $ret;
						} else {
							
							return '{block "'.$this -> fname . '.' . $m[0] . '"}';
						}
						
						break;
					case 'D':
						if(count($this->curarray) == 0) {
							return $ma[0];
						}
						$aname = $this -> curarray[count($this->curarray)-1];
						$vara = explode('.', $m[0]);
						$var = join('->', $vara);
						
						return '{$' . $this -> arrays[$aname] . '->'.$var.'}';
						
					case 'VALUE':
						if(count($this->curarray) == 0) {
							return $ma[0];
						}
						$aname = $this -> curarray[count($this->curarray)-1];
						return '{$' . $this -> arrays[$aname] . '}';
					case 'V':
						
						
						// Normal variable;
						$vara = explode('.', $m[0]);
						$var = join('->', $vara);
						$var = '$this->' . $var;
						if(count($this->curarray) > 0) {
							$aname = $this -> curarray[count($this->curarray)-1];
							if(strpos($m[0], $aname) === 0) {
								$var = str_replace($aname, $this -> arrays[$aname], $m[0]);
								$vara = explode('.', $var);
								$var = join('->', $vara);
								$var = '$' . $var;
							}
						} 
						
						if($isif) {
							return '{if ' . $var . '}';
						}
						return '{' . $var . '}';
						
					case 'A':
						// Array, change to foreach
						$vara = explode('.', $m[0]);
						$last = $vara[count($vara)-1] . '_item';
						$var = join('->', $vara);
						if($isif) {
							return '{if $this->' . $var . '}';
						}
						if(!array_key_exists($m[0], $this-> arrays)) {
							$this -> arrays[$m[0]] = $last;
							$this -> curarray[] = $m[0];
							return '{foreach from=$this->' .$var .' item=' .$last .'}';
						} else {
							unset($this->arrays[$m[0]]);
							array_pop($this -> curarray);
							return '{/foreach}';
						}
					
					case 'T':
						// Template!
						return '{include \'' . $m[0] . '.tpl\'}';
					
					case 'F':
						// Function, manual labour :(
						return '<!-- FIXME implement custom function --> '. $ma[0]; 
						
					default:
						if(defined($type)) 
							return '{$smarty.const.' . $type .'}';
						
						throw new Exception("Unknown tag\r\n{$ma[0]} in {$this->fname}");
						return $ma[0]; 
				}
			}, $this -> tpl);
			
			
			// Fix links
			$this -> tpl = preg_replace_callback('#/index\.php\?tid=(.*?)(["\'\?& ]{1})#', function($m) {

				if(BrightUtils::startsWith($m[1], '{') && BrightUtils::endsWith($m[1], '}')) {
					$m[1] = str_replace(array('{','}'), array('',''), $m[1]);
				}
				return '/{getUrl id=' .$m[1] .'}' . $m[2];
			}, $this -> tpl);
			
			// Wrap script tags
			$this -> tpl = preg_replace_callback('#<script.*?</script>#ism', function($m) {
// 				print_r($m);exit;
				return '{literal}' . $m[0] . '{/literal}';
			}, $this -> tpl);
			
			// Create template includes / blocks
			$this -> tpl = preg_replace_callback('#\{block "([A-z0-9]*\.[A-z0-9]*)"\}(.*?)\{/block\}#ism', function($m) {
				$fname = BASEPATH . 'bright/site/templates/' . $m[1] . '.tpl';
				if(!file_exists($fname)) {
					file_put_contents($fname, $m[2]);
				} else {
					echo "<li>$fname exists";
				}
				return '';
			}, $this -> tpl);
			
			
			if(strpos($template, 'default.html') === false)
				$this -> tpl = '{extends "default.tpl"}' . "\r\n" . $this -> tpl;
			
			
			$filename = str_replace('.html', '.tpl', $template);
			if(!file_exists($filename)) {
				file_put_contents($filename, $this -> tpl);
			} else {
				echo "<li>$filename exists";
			}

		}
	}
}

$m = new Migrate();

