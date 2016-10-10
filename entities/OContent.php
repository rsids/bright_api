<?php
namespace fur\bright\entities;
/**
 * This class makes sure no notices are displayed when asking for a non-existing property<br/>
 * It also localizes the object
 * @author fur
 * @version 2.2
 * @package Bright
 * @subpackage objects
 */
class OContent extends OBaseObject  {

	public function __get($key) {


		if(isset($_SESSION['language']) && strpos($key, 'loc_') === 0) {
			$realkey = substr($key, 4);
			if(isset($this -> $realkey)) {
				if(isset($this -> $realkey -> {$_SESSION['language']})) {
					return $this -> $realkey -> {$_SESSION['language']};
				} else {
					/**
					 * @since 2.2 TPL is added to the languages
					 * @var unknown_type
					 */
					$langs = explode(',', AVAILABLELANG . ',tpl');
					foreach($langs as $lang) {
						if(isset($this -> $realkey -> $lang))
							return $this -> $realkey -> $lang;
					}

					if(isset($this -> $realkey -> all))
						return $this -> $realkey -> all;
				}

			}
		}

		if(isset($this -> $key)) {

			return $this -> $key;
		}
		return '';
	}

}