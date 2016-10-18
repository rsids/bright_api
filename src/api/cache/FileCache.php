<?php
namespace fur\bright\api\cache;

use fur\bright\exceptions\FilesException;
use fur\bright\interfaces\ICache;
use fur\bright\Permissions;

class FileCache extends Permissions  implements ICache  {

	private $_forceCache = false;

	function __construct($forceCache = false) {
		parent::__construct();
		$this -> _forceCache = $forceCache;
		if(!is_dir(BASEPATH . 'bright/cache')) {
			if(!@mkdir(BASEPATH . 'bright/cache', 0777, true)) {
				throw new FilesException("Cannot create cache directory (bright/cache/)", FilesException::FOLDER_CREATE_FAILED);
			}
		}
		if(!file_exists(BASEPATH . 'bright/cache/.htaccess')) {
			if(!@file_put_contents(BASEPATH . 'bright/cache/.htaccess', 'deny from all')) {
				throw new FilesException("Cache directory is not writable", FilesException::FILE_NOT_WRITABLE);
			}
		}
	}

	/**
	 * @param $value
	 * @param $name
	 * @param $expirationDate
	 * @param null $headers
	 * @return bool
	 */
	public function setCache($value, $name, $expirationDate, $headers = null) {
		if($expirationDate < 0 || (!LIVESERVER && !$this -> _forceCache))
			return true;
		if($headers){
			$nc = count($headers);
			while(--$nc > -1) {
				// Only keep content-type
				if(stripos($headers[$nc], "Content-Type") === false) {
					array_splice($headers, $nc, -1);
				}
			}
		}
		$obj = new \stdClass();
		$obj -> life = $expirationDate;
		$obj -> contents = $value;
		$obj -> headers = $headers;

		$cached = serialize($obj);
		$handle = @fopen(BASEPATH . 'bright/cache/' . $name, 'w');
		if(!$handle)
			return false;

		fwrite($handle, $cached);
		fclose($handle);
		return true;
	}

	/**
	 * Deletes a cached file on the server<br/>
	 * Required permissions:<br/>
	 * <ul>
	 * <li>IS_AUTH</li>
	 * </ul>
	 * @param string $name The name of the cached files
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteCache($name) {
		if(!$this -> IS_AUTH)
			throw $this -> throwException(1001);

		if(count(explode('/', $name)) > 1 || count(explode('\\', $name)) > 1)
			throw $this -> throwException(2001);

		if(file_exists(BASEPATH . 'bright/cache/' . $name) === false)
			return false;

		return unlink(BASEPATH . 'bright/cache/' . $name);
	}

    /**
     * Deletes all the cached files where Page '$label' is in the path<br/>
     * @param string $label
     * @throws \Exception
     */
	public function deleteCacheByLabel($label) {
		if(count(explode('/', $label)) > 1 || count(explode('\\', $label)) > 1)
			throw $this -> throwException(2001);


		$files = scandir(BASEPATH . 'bright/cache/');
		foreach($files as $file) {

			$arr = explode('%', $file);
			$cachelabel = array_pop($arr);

			// Remove Querystring
			$arrq = explode('&', $cachelabel);
			$cachelabel = $arrq[0];

			// Also delete empty labels, so the homepage will always be deleted,
			// Unfortunately, this is the only way to update the homepage
			if($cachelabel == $label || $cachelabel == '')
				unlink(BASEPATH . 'bright/cache/' . $file);

		}

		if(!defined('SMARTYAVAILABLE') || !SMARTYAVAILABLE)
			return;

		$label = str_replace('-', '_', $label);
		$files = glob(BASEPATH . "bright/cache/smarty/page_*{$label}*");


		if(!$files)
			return;

		foreach($files as $file) {
			unlink($file);
		}


	}

	/**
	 * Removes all the cached files starting with $prefix
	 * @since 2.1
	 * @param string $prefix The prefix
	 * @throws \Exception
	 * @return void
	 */
	public function deleteCacheByPrefix($prefix) {
		$prefix = filter_var($prefix, FILTER_SANITIZE_STRING);

		if(count(explode('/', $prefix)) > 1 || count(explode('\\', $prefix)) > 1)
			throw $this -> throwException(2001);


		$files = glob(BASEPATH . "bright/cache/{$prefix}*");
		if($files) {
			foreach($files as $file) {
				unlink($file);
			}
		}

		if(!defined('SMARTYAVAILABLE') || !SMARTYAVAILABLE)
			return;

		$files = glob(BASEPATH . "bright/cache/smarty/{$prefix}_*");

		if(!$files)
			return;

		foreach($files as $file) {
			unlink($file);
		}
	}
	/**
	 * Deletes all the cached files<br/>
	 * Required permissions:<br/>
	 * <ul>
	 * <li>IS_AUTH</li>
	 * </ul>
	 */
	public function flushCache() {
		if(!$this -> IS_AUTH)
			throw $this -> throwException(1001);

		if ($handle = opendir(BASEPATH . 'bright/cache/')) {
			while (false !== ($file = readdir($handle))) {
				if (!is_dir(BASEPATH . 'bright/cache/' . $file) && $file != '.htaccess') {
					unlink(BASEPATH . 'bright/cache/' . $file);
				}
			}
			closedir($handle);
		}

		if(defined('SMARTYAVAILABLE') && SMARTYAVAILABLE) {
			require_once(BASEPATH . 'bright/externallibs/smarty/libs/Smarty.class.php');
			try {
				$s = new \Smarty();
				$ds = DIRECTORY_SEPARATOR;
				$s
					-> setCompileDir(BASEPATH . "bright{$ds}cache{$ds}smarty_c")
					-> setCacheDir(BASEPATH . "bright{$ds}cache{$ds}smarty")
					-> clearAllCache();
			}catch(\Exception $e) {
				error_log($e -> getTraceAsString());
				// Fail silently
			}
		}

	}

	/**
	 * Gets a cached file by it's name<br/>
	 * @param string $name The name of the cached file
	 * @return mixed The cached string, or false when not found
	 */
	public function getCache($name) {

		if(!LIVESERVER && !$this -> _forceCache) // We don't cache while developing!
			return false;

		if(file_exists(BASEPATH . 'bright/cache/' . $name) === false)
			return false;

		$handle = fopen(BASEPATH . 'bright/cache/' . $name, 'r');
		$contents = fread($handle, filesize(BASEPATH . 'bright/cache/' . $name));
		fclose($handle);
		$result = unserialize($contents);
		if($result -> life > time()) {
			if(isset($result -> headers)) {
				return $result;
			}
			return $result -> contents;
		}

		return false;
	}
}