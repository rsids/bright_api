<?php
namespace fur\bright\api\mailing;
use fur\bright\api\page\Page;
use fur\bright\api\template\Template;
use fur\bright\api\User;
use fur\bright\core\Connection;
use fur\bright\entities\OPage;
use fur\bright\entities\OTreeNode;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\ParameterException;
use fur\bright\Permissions;
use fur\bright\utils\BrightUtils;
use fur\bright\utils\Mailer;

/**
 * Handles the creating, updating and returning of mailings.<br/>
 * A Mailing is a special type of page
 * Version history
 * 2.3 20130709
 * - Added check for content in _send, should fix php notice (Trying to get property of non-object) 
 * @author Fur - Ids Klijnsma
 * @version 2.2
 * @package Bright
 * @subpackage mailing
 */
class Mailing extends Permissions  {
	
	function __construct() {
		parent::__construct();
		
		$this -> _conn = Connection::getInstance();
	}
	
	private $_conn;

    /**
     * Saves a mailing
     * @param OPage $mailing The mailing to save
     * @return stdClass An object containing mailing, the just saved mailing and mailings, an array of all mailings
     * @throws Exception
     */
	public function setMailing(OPage $mailing) {
		if(!$this -> IS_AUTH) 
			throw $this -> throwException(1001);	
		$page = new Page();
		$mailing = $page -> setPage($mailing, false);
		$result = new stdClass();
		// Fetch mailing again, fixes #43
		$result -> mailing = $page -> getPageById($mailing -> pageId, false, true);
		$result -> mailings = $page -> getPages(1, null, true);
		return $result;
	}

    /**
     * deletes a mailing by it's ID
     * @since 2.2 - 08 mar 2011
     * @param int $pageId
     * @return array
     */
	public function deleteMailing($pageId) {
		$page = new Page();
		$page -> deletePage($pageId);
		return $page -> getPages(1, null, true);
	}
	
	/**
	 * Get all the mailings which are send
	 * @since 2.1 12-11-2010
	 * @return array An array of pages
	 */
	public function getSendMailings() {
		$sql = 'SELECT pageId, UNIX_TIMESTAMP(dateadded) as `dateadded` FROM mailqueue WHERE issend=2';
		$ids = $this -> _conn -> getFields($sql);
		$rows = $this -> _conn -> getRows($sql);
		$page = new Page();
		$result = $page -> getPagesByIds($ids, true);
		if($result) {
			foreach($result as $page) {
				foreach($rows as $row) {
					if($row -> pageId == $page -> pageId) {
						$page -> publicationdate = $row -> dateadded;
						break;
					}
				}
			}
		}
		return $result;
	}
	
	/**
	 * Get the last send mailing
	 * @since 2.1 12-11-2010
	 * @return OPage The last send mailing
	 */
	public function getLatestMailing() {
		$sql = 'SELECT pageId, UNIX_TIMESTAMP(dateadded) as `dateadded` FROM mailqueue WHERE issend=2 ORDER BY dateadded DESC LIMIT 0,1';
		$row = $this -> _conn -> getRow($sql);
		if($row) {
			$page = new Page();
			$result = $page -> getPageById($row -> pageId);
			$result -> publicationdate = $row -> dateadded;
			return $result;
		}
		return null;
		
	}

    /**
     * Gets a mailing by it's label
     * @param string $label
     * @param boolean $testmode when true, the check if the mailing has been send is removed
     * @return null|OPage
     */
	public function getMailingByLabel($label, $testmode = false) {
		if(!$testmode) {
			$sql = 'SELECT m.pageId, p.label, UNIX_TIMESTAMP(dateadded) as `dateadded` 
					FROM mailqueue m
					INNER JOIN `page` p ON p.pageId = m.pageId AND label=\'' . Connection::getInstance() -> escape_string($label) . '\'
					AND m.issend=2';
		
		} else {
			$sql = 'SELECT pageId, p.label FROM page p INNER JOIN itemtypes it ON p.itemType = it.itemId AND it.templatetype = 1 AND p.label=\'' . Connection::getInstance() -> escape_string($label) . '\'';
		}				
				
		$row = $this -> _conn -> getRow($sql);
		if($row) {
			$page = new Page();
			$result = $page -> getPageById($row -> pageId);
			$result -> publicationdate = $row -> dateadded;
			return $result;
		}
		return null;
	}

    /**
     * Sends a test mail of the mailing to the entered e-mail address
     * @param int $pid The PageId of the mailing to send
     * @param string $email The e-mail address to send the mailing to
     * @return bool True when successful
     * @throws \Exception
     */
	public function sendTest($pid, $email) {
		if(!$this -> IS_AUTH) {
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);
        }

		if(!is_numeric($pid)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }

		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw $this->throwException(ParameterException::EMAIL_EXCEPTION);
        }
		
		$this -> _send($pid, $email);
		return true;
	}

    /**
     * Adds a mailing to the mailqueue
     * @param int $pid The pageId holding the mail template
     * @param array $userGroups The usergroups to send the mailing to
     * @return bool
     * @throws \Exception
     */
	public function sendMass($pid, $userGroups) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }
		
		sort($userGroups);
			
		$sql = 'SELECT count(id) FROM mailqueue WHERE pageId=' . (int) $pid . ' AND `groups`=\'' . Connection::getInstance() -> escape_string(join(',', $userGroups)) . '\' AND issend=0';
		if((int)$this -> _conn -> getField($sql) == 0) {
			$sql = 'INSERT INTO mailqueue (`pageId`, `groups`, `dateadded`, `issend`) VALUES ' .
					'(' . (int) $pid . ', ' .
					'\'' . Connection::getInstance() -> escape_string(join(',', $userGroups)) . '\', ' .
					'NOW(), ' .
					'0)';
			$res = $this -> _conn -> insertRow($sql);
		} 
		return true;
	}
	
	/**
	 * Does the actual sending. DO NOT CALL THIS METHOD FROM YOUR SCRIPTS
	 * @private
	 */
	public function realSend() {
		BrightUtils::inBrowser();
		$sql = 'SELECT id, pageId, `groups` FROM `mailqueue` WHERE issend=0';
		$mailings = $this -> _conn -> getRows($sql);
		
		$user = new User();
		foreach($mailings as $mailing) {
			$sql = 'UPDATE `mailqueue` SET issend=1 WHERE id=' . (int)$mailing -> id;
			$this -> _conn -> updateRow($sql);
			$groups = explode(',', $mailing -> groups);			
			$emails = $user -> getUsersInGroups($groups, false, true);
			$this -> _send($mailing -> pageId, $emails);
			$sql = 'UPDATE `mailqueue` SET issend=2 WHERE id=' . (int)$mailing -> id;
			$this -> _conn -> updateRow($sql);
		}
	}
	
	public function preview($pid) {
		$parsed = $this -> _parse($pid);
		if(!$parsed || !isset($parsed -> parsed))
			return 'error';
		return $parsed -> parsed;
	}

	
	private function _send($pid, $email) {
		if(is_string($email))
			$email = array($email);
			
		$parsed = $this -> _parse($pid);
		
		$mailer = new Mailer();
		$emails = array();
		$decorated = array();
		$template = new Template();
		$tpl = $template -> getUserTemplate();
		foreach($email as $em) {
			if(is_string($em)) {
				$emails[] = $em;
			} else {
				$emails[] = $em -> email;
				$decoration = array('{email}'=>$em -> email,'[email]'=>$em -> email,'[code]'=>$em -> activationcode,'{code}'=>$em -> activationcode);
				if($tpl) {
					foreach($tpl -> fields as $field) {
						if(isset($em -> content -> { $field -> label }))
							$decoration['[' . $field -> label .  ']'] = $em -> content -> { $field -> label } -> tpl;
					}
				}
				$decorated[$em -> email] = $decoration;
			}
		}
		if(count($decorated) == 0)
			$decorated = null;
		$mailer -> sendMassMail(array(MAILINGFROM => SITENAME), $emails, $parsed -> page -> content -> loc_title, $parsed -> parsed, $decorated);		
	}
	
	private function _parse($pid) {
		$tpl = new Template();
		$template = $tpl -> getTemplateDefinitionByPageId($pid);

		if((int)$template -> templatetype != 1)
			throw $this -> throwException(7008);
		$_SESSION['language'] = 'tpl';
		$page = new Page();
		$opage = $page -> getPageById($pid, false, true);
		$tn = new OTreeNode();
		$tn -> page = $opage;
		$tname = $template -> itemtype;
		
		if(!file_exists(BASEPATH . '/bright/site/views/' . $tname . 'View.php'))
			throw $this -> throwException(7010, array($tname . 'View'));


//		include_once('views/' . $tname . 'View.php');
		$viewclass = $tname . 'View';
		$view = new $viewclass($tn);
		
		
		// Simplify page for parsing
		// @deprecated Should not be used anymore
		foreach($opage -> content as $cfield => $value) {
			$opage -> {$cfield} = $value;
		}

	
		$deactivationlink = BASEURL . 'bright/';
		if(defined('USEACTIONDIR') && USEACTIONDIR) {
			$deactivationlink .= 'actions/';
		}
		$deactivationlink .= 'registration.php?action=deactivate&email={email}&code={code}';
		$view -> deactivationlink = $deactivationlink;
		define('DEACTIVATIONLINK', $deactivationlink);
		
		if(method_exists($view, 'parseTemplate')) {
			// Bright parser
			$parsed = $view -> parseTemplate($view, $template -> itemtype);
		} else {
			// Smarty parser
			\Smarty::muteExpectedErrors();
			$parsed = $view -> output();
			\Smarty::unmuteExpectedErrors();
		}
		
		$parsed = preg_replace_callback('/<img([^>]*)src="\/([^"]*)"([^>]*)\/>/ism', array($this, '_replaceImage'), $parsed);
		
		
		$result = new \stdClass();
		$result -> page = $opage;
		$result -> parsed = $parsed;
		
		return $result;
	}
	
	private function _replaceImage($matches) {
		return '<img' . $matches[1] . 'src="' . BASEURL . $matches[2] . '"' . $matches[3] .'/>';
	}
}