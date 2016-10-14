<?php

namespace fur\bright\utils;

use fur\bright\api\tree\Tree;

class Form {
	
	const TYPE_STRING = 1;
	const TYPE_EMAIL = 2;
	const TYPE_PHONE = 3;
	const TYPE_HTML = 4;
	const TYPE_SELECT = 5;
	const TYPE_RADIO = 6;
	const TYPE_CHECKBOX = 7;
	const TYPE_FILE = 8;
	const TYPE_INT = 9;
	const TYPE_NUMBER = 10;
	const TYPE_DATE = 11;
	const TYPE_TIMESTAMP = 12;
	const TYPE_TEXT = 13;
	
	const EXCEPTION_NOT_IMPLEMENTED = 1;
	const EXCEPTION_INVALID_ACTION = 2;
	const EXCEPTION_INVALID_METHOD = 3;
	const EXCEPTION_INVALID_EMAIL = 4;
	
	const ACTION_EMAIL = 1;
	const ACTION_STORE = 2;
	
	private $_action = Form::ACTION_EMAIL;
	
	private $_errors = array();
	private $_exceptions = array();
	
	private $_fields;
	private $_fieldtypes;
	private $_filters;
	private $_requiredFields = array();
	
	private $_pageAfterSuccess;
	
	private $_data;
	private $_subject;
	private $_title;
	private $_bodytext;
	private $_labelFunction;
	private $_recipient;
	
	/**
	 * Default input method
	 * @var int
	 */
	private $_method = INPUT_POST;
	
	/**
	 * The phone format to check
	 * @default Validates dutch phone number with optional country code, whitespaces and parentheses are allowed
	 * @var string
	 */
	private $_phone_format = '/^(\+[1-9][0-9]{1,2}|0)([0-9\s\(\)]{9,20})$/';
	
	private $_date_format = 'YYYY-MM-DD';
	
	function __construct() {
		$this -> _exceptions = array(	Form::EXCEPTION_NOT_IMPLEMENTED => 'Method not implemented yet',
										Form::EXCEPTION_INVALID_ACTION => "Invalid Action, must be either Form::ACTION_EMAIL or Form::ACTION_STORE",
										Form::EXCEPTION_INVALID_METHOD => "Invalid Method, must be either INPUT_GET, INPUT_POST or INPUT_SESSION",
										Form::EXCEPTION_INVALID_EMAIL => "Invalid e-mail address");
		
		$this -> setBodytext('Er is een mail verstuurd vanaf ' . SITENAME)
			  -> setTitle('Fur mailing tool');
		
	}
	
	public function addField($name, $type = Form::TYPE_STRING, $required = false) {
		$this -> _filters[$name] = self::_getFilter($type); 
		$this -> _fieldtypes[$name] = $type;
		$this -> _requiredFields[$name] = $required;
		// Allow daisy chaining
		return $this;
	}
	
	/**
	 * Checks the form, but does nothing with the data
	 * @return Form $this
	 */
	public function check() {
		$this -> _data = filter_input_array($this -> _method, $this -> _filters);
		foreach($this -> _data as $key => $value) {
		
			if($value === false || $value === null || $value == '') {
				// Invalid or missing input
				if($this -> _requiredFields[$key] == true) {
					$this -> _errors[$key] = $key;
				} else {
					unset($this -> _data[$key]);
				}
			}
		}
		return $this;
	}
	
	/**
	 * Returns the (filtered) data
	 */
	public function getData() {
		return (object)$this -> _data;
	}
	
	/**
	 * Gets all the errors
	 * @return array
	 */
	public function getErrors() {
		return $this -> _errors;
	}
	
	/**
	 * Gets the current date format
	 * @return string
	 */
	public function getDateFormat() {
		return $this -> _date_format;
	}
	
	/**
	 * Gets the current phone format
	 * @return string
	 */
	public function getPhoneFormat() {
		return $this -> _phone_format;
	}
	
	/**
	 * Sets the action, what to do with the data
	 * @param int $action
	 * @throws \Exception
	 * @return Form $this
	 */
	public function setAction($action) {
		if($action == self::ACTION_EMAIL || $action == self::ACTION_STORE) {
			$this -> _action = $action;
		} else {
			throw new \Exception($this -> _exceptions[self::EXCEPTION_INVALID_ACTION], self::EXCEPTION_INVALID_ACTION);
		}
		return $this;
	}
	
	/**
	 * Sets the text body of the mail
	 * @param string $body
	 * @return Form $this
	 */
	public function setBodytext($body) {
		$this -> _bodytext = $body;
		return $this;
	}
	
	/**
	 * Sets the date format
	 * @param string $format
	 * @return Form $this
	 */
	public function setDateFormat($format) {
		$this -> _date_format = $format;
		return $this;
	}
	
	public function setLabelFunction($function) {
		$this -> _labelFunction = $function;
		return $this;
	}
	
	/**
	 * Sets the method used to get the data (INPUT_POST / INPUT_GET / INPUT_SESSION)
	 * @param int $method
	 * @return Form $this
	 */
	public function setMethod($method) {
		$this -> _method = ($method == INPUT_POST || $method == INPUT_GET || $method == INPUT_SESSION) ? $method : INPUT_POST;
		return $this;
	}
	
	/**
	 * Sets the phone format
	 * @param string $format
	 * @return Form $this
	 */
	public function setPhoneFormat($format) {
		$this -> _phone_format = $format;
		return $this;
	}
	
	/**
	 * Sets the page to navigate to after succes
	 * @param int $tid
	 * @return Form $this
	 */
	public function setPageAfterSuccess($tid) {
		$this -> _pageAfterSuccess = $tid;
		return $this;
	}
	
	public function setRecipient($email) {
		$this -> _recipient = filter_var($email, FILTER_VALIDATE_EMAIL);
		if(!$this -> _recipient)
			throw new \Exception($this -> _exceptions[Form::EXCEPTION_INVALID_EMAIL], Form::EXCEPTION_INVALID_EMAIL);
		
		return $this;
	}
	
	/**
	 * Sets the subject
	 * @param string $subject
	 * @return Form $this
	 */
	public function setSubject($subject) {
		$this -> _subject = filter_var($subject, FILTER_SANITIZE_STRING);
		return $this;
	}
	
	/**
	 * Sets the title
	 * @param string $title
	 * @return Form $this
	 */
	public function setTitle($title) {
		$this -> _title = filter_var($title, FILTER_SANITIZE_STRING);
		return $this;
	}
	
	/**
	 * Submits the form
	 */
	public function submit() {
		
		$this -> check();
		if(count($this -> _errors) == 0) {
			// Valid form
			switch($this -> _action) {
				case self::ACTION_EMAIL:
					// Send the form
					if(!$this -> _recipient)
						throw new \Exception($this -> _exceptions[Form::EXCEPTION_INVALID_EMAIL], Form::EXCEPTION_INVALID_EMAIL);
					
					$emldata = array('bodytext' => $this -> _bodytext,
									'title' => $this -> _title,
									'fields' => $this -> _data);
					$smarty = new \Smarty();
					$ds = DIRECTORY_SEPARATOR;
					$smarty -> assign($emldata)
							-> setCacheDir(BASEPATH . "bright{$ds}cache{$ds}smarty")
							-> setCompileDir(BASEPATH . "bright{$ds}cache{$ds}smarty_c")
							-> enableSecurity()
							-> addTemplateDir(BASEPATH . "bright{$ds}library{$ds}Bright{$ds}templates{$ds}")
							-> registerPlugin(\Smarty::PLUGIN_FUNCTION, 'getLabel', array($this, '_getLabel'))
							-> registerPlugin(\Smarty::PLUGIN_FUNCTION, 'getValue', array($this, '_getValue'))
							-> php_handling = \Smarty::PHP_REMOVE;
					
					ob_start();
					$smarty -> display('FormMailTemplate.tpl');
					$html = ob_get_clean();


					ob_start();
					$smarty -> display('FormMailPlainTemplate.tpl');
					$plain = ob_get_clean();
					
					$mailer = new Mailer();
					$res = $mailer -> sendHtmlMail(MAILINGFROM, $this -> _recipient, $this -> _subject, $html,$plain);
					if($res && $this -> _pageAfterSuccess != null) {
						$t = new Tree();
						$path = $t -> getPath($this ->_pageAfterSuccess);
						if(USEPREFIX) {
							$path = $_SESSION['language'] . '/' . $path;
						}
						$path = BASEURL . $path;
						header("Location: $path");
						exit;
					}
					break;
				case self::ACTION_STORE:
					// Store the data
					throw new \Exception($this -> _exceptions[Form::EXCEPTION_NOT_IMPLEMENTED] . ' ACTION_STORE', Form::EXCEPTION_NOT_IMPLEMENTED);
					break;
			}
		}
		
		return $this;
	}
	
	private function _getFilter($type) {
		switch ($type) {
			case Form::TYPE_TEXT:
			case Form::TYPE_STRING:
				return FILTER_SANITIZE_STRING;
			
			case Form::TYPE_EMAIL:
				return FILTER_VALIDATE_EMAIL;
				
			case Form::TYPE_PHONE:
				return array('filter' => FILTER_VALIDATE_REGEXP, 'options' => array('regexp' => $this -> _phone_format));
			
			case Form::TYPE_CHECKBOX:
				return array('filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY);
			
			case Form::TYPE_INT:
				return array('filter' => FILTER_VALIDATE_INT);
			
			case Form::TYPE_NUMBER:
				return array('filter' => FILTER_VALIDATE_FLOAT);
			
			case Form::TYPE_DATE:
				throw new \Exception($this -> _exceptions[Form::EXCEPTION_NOT_IMPLEMENTED], Form::EXCEPTION_NOT_IMPLEMENTED);
				
				return array('filter' => FILTER_CALLBACK, 'options' => array('Form::_validateDate'));

			default:
				throw new \Exception($this -> _exceptions[Form::EXCEPTION_NOT_IMPLEMENTED], Form::EXCEPTION_NOT_IMPLEMENTED);
		}
	}

	/**
	 * @private
	 * @param $params
	 * @internal param array $key
	 * @return mixed
	 */
	public final function _getLabel($params) {
		if($this -> _labelFunction != null) {
			return call_user_func_array($this -> _labelFunction, array($params['key']));
		}
		
		return $params['key'];
	}


	/**
	 * @private
	 * @param $params
	 * @internal param \unknown_type $key
	 * @return string
	 */
	public final function _getValue($params) {
		switch($this -> _getType($params['key'])) {
			case Form::TYPE_TEXT:
				return '<p>' . nl2br($params['value']) . '</p>';
			case Form::TYPE_CHECKBOX:
				$str = '<ul>';
				foreach($params['value'] as $item) {
					$str .= "<li>$item</li>";
				}
				$str .= '</ul>';
				return $str;
		}
		return $params['value'];
	}
	
	private function _getType($key) {
		return $this -> _fieldtypes[$key];
	}
	
	private static function _validateDate($input) {
		
	}
}