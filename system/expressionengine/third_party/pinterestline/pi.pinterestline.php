<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * PinterestLine Plugin
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Plugin
 * @author		David Dexter
 * @link		http://codesly.com
 */

$plugin_info = array(
	'pi_name'		=> 'PinterestLine',
	'pi_version'	=> '1.0',
	'pi_author'		=> 'David Dexter',
	'pi_author_url'	=> 'http://codesly.com',
	'pi_description'=> 'Retrieve and parse Pinterest rss feed',
	'pi_usage'		=> Pinterestline::usage()
);


class Pinterestline {

	public $return_data;
    
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->EE =& get_instance();
	}
	
	// ----------------------------------------------------------------
	
	public function feed()
	{
		$username = $this->EE->TMPL->fetch_param('username');
		$limit = $this->EE->TMPL->fetch_param('limit',5);
		
		if($username == ''){
			return '';
		}
		
		// Cache path
		$this->cache = rtrim(APPPATH,"/").'/cache/pinterestline/';
		if(!file_exists($this->cache)){
			mkdir($this->cache);
		}
		
		// What is the cache time in minutes
			$cache_time = ( ! $this->EE->TMPL->fetch_param('cache')) ? 10 :  $this->EE->TMPL->fetch_param('cache');
			if($cache_time < 3){
				$cache_time = 3;
			}
		
		// Lets check the cache 
        	
        	$content = $this->_check_cache($this->cache.'pinterestline.'.$username.'.cache');
			$content = @unserialize($content);
			if($content !== false){
				$tm = (time() - $content["cachestamp"]) / 60;
				if($tm <= $cache_time){
					unset($content["cachestamp"]);
					$xml = $content;
				}
			}
			
			if(!isset($xml)){
				$url = 'http://www.pinterest.com/'.$username.'/feed.rss';
				$curl = curl_init();
			    curl_setopt ($curl, CURLOPT_URL, $url);
			    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			    $result = curl_exec($curl);
			    curl_close($curl);
			    
			    $xml = $this->simpleXMLToArray(simplexml_load_string($result));
			}
			
			$i = 0;
			foreach($xml["channel"]["item"] as $items){
				if($i < $limit){
					$variables[$i] = $items;
					// Break the image out of the description
						preg_match( '/src="([^"]*)"/i', $items["description"], $array ) ;
						$variables[$i]["image"] = isset($array[1]) ? $array[1] : '';	
						$variables[$i]["rel_date"] = $this->_build_relative_time(date("U",strtotime($items["pubDate"])));
					$i++;
				}else{
					continue;
				}
			}	

		// Parse the goodness 
			return $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables); 
	}
	    function _check_cache($fl){
		if(file_exists($fl)){
			$fh = fopen($fl,'r');
			$content = fread($fh, filesize($fl));
			return $content;
		}else{
			return false;
		}        
	}
	
	function _save_cache($fl,$vars){
		$vars['cachestamp'] = time();
		$fh = fopen($fl, 'w') or die("can't open pinterestline cache file");
		$str = serialize($vars);
		fwrite($fh, $str);
		fclose($fh);
		return true;
	}

    function _build_relative_time($time){
   		$diff = time() - $time;
		if ($diff < 60) {
			return 'less than a minute ago';
		}else if($diff < 120) {
			return 'about a minute ago';
		}else if($diff < (60*60)) {
			return round($diff / 60,0) . ' minutes ago';
		}else if($diff < (120*60)) {
			return 'about an hour ago';
		} else if($diff < (24*60*60)) {
			return 'about ' + round($diff / 3600,0) . ' hours ago';
		} else if($diff < (48*60*60)) {
			return '1 day ago';
		} else {
			return round($diff / 86400,0) . ' days ago';
		}
    }
    
     function simpleXMLToArray($xml,
                    $flattenValues=true,
                    $flattenAttributes = true,
                    $flattenChildren=true,
                    $valueKey='@value',
                    $attributesKey='@attributes',
                    $childrenKey='@children'){

        $return = array();
        if(!($xml instanceof SimpleXMLElement)){return $return;}
        $name = $xml->getName();
        $_value = trim((string)$xml);
        if(strlen($_value)==0){$_value = null;};

        if($_value!==null){
            if(!$flattenValues){$return[$valueKey] = $_value;}
            else{$return = $_value;}
        }

        $children = array();
        $first = true;
        foreach($xml->children() as $elementName => $child){
            $value = $this->simpleXMLToArray($child, $flattenValues, $flattenAttributes, $flattenChildren, $valueKey, $attributesKey, $childrenKey);
            if(isset($children[$elementName])){
                if($first){
                    $temp = $children[$elementName];
                    unset($children[$elementName]);
                    $children[$elementName][] = $temp;
                    $first=false;
                }
                $children[$elementName][] = $value;
            }
            else{
                $children[$elementName] = $value;
            }
        }
        if(count($children)>0){
            if(!$flattenChildren){$return[$childrenKey] = $children;}
            else{$return = array_merge($return,$children);}
        }

        $attributes = array();
        foreach($xml->attributes() as $name=>$value){
            $attributes[$name] = trim($value);
        }
        if(count($attributes)>0){
            if(!$flattenAttributes){$return[$attributesKey] = $attributes;}
            else{$return = array_merge($return, $attributes);}
        }
       
        return $return;
    }


	/**
	 * Plugin Usage
	 */
	public static function usage()
	{
		ob_start();
?>

* title 
* link
* image
* description 
* pubDate
* rel_date 

<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}
}


/* End of file pi.pinterestline.php */
/* Location: /system/expressionengine/third_party/pinterestline/pi.pinterestline.php */