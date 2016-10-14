<?php
namespace fur\bright\api\cache;
use fur\bright\interfaces\ICache;

/**
 * User: ids
 * Date: 6/11/14
 * Time: 9:32 AM
 */

class APCCache implements ICache {

	public function setCache($value, $name, $expirationDate, $headers = null) {
		if($expirationDate - time() <= 0)
			return;

		$cached = (object) array('contents' => $value, 'headers' => $headers);
		apc_store(CACHEPREFIX.$name, $cached, $expirationDate - time());
	}

	/**
	 * Deletes a cached file on the server<br/>
	 * @param string $name The name of the cached files
	 * @return bool|\string[]
	 */
	public function deleteCache($name) {
		return apc_delete(CACHEPREFIX.$name);
	}

	/**
	 * Deletes all the cached files where Page '$label' is in the path<br/>
	 */
	public function deleteCacheByLabel($label) {
		apc_delete(CACHEPREFIX.$label);
		foreach(new \APCIterator('user', "/%$label%/") as $iterator) {
			apc_delete($iterator['key']);
		}
	}

	/**
	 * Removes all the cached files starting with $prefix
	 * @param string $prefix The prefix
	 * @return void
	 */
	public function deleteCacheByPrefix($prefix) {
		$prefix = CACHEPREFIX.$prefix;
		foreach(new \APCIterator('user', "/^$prefix/") as $iterator) {
			apc_delete($iterator['key']);
		}
	}

	/**
	 * Deletes all the cached files
	 */
	public function flushCache() {
		apc_clear_cache('user');
	}

	/**
	 * Gets a cached file by it's name<br/>
	 * @param string $name The name of the cached file
	 * @return mixed The cached string, or false when not found
	 */
	function getCache($name) {
		$result = apc_fetch(CACHEPREFIX.$name);
		if(!$result)
			return false;

		if(isset($result -> headers)) {
			return $result;
		}
		return $result -> contents;
	}
}