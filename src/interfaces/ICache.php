<?php
namespace fur\bright\interfaces;

/**
 * User: ids
 * Date: 6/11/14
 * Time: 9:35 AM
 */

interface ICache {
	public function setCache($value, $name, $expirationDate, $headers = null);

	/**
	 * Deletes a cached file on the server<br/>
	 * @param string name The name of the cached files
	 */
	public function deleteCache($name);

	/**
	 * Deletes all the cached files where Page '$label' is in the path<br/>
	 */
	public function deleteCacheByLabel($label);

	/**
	 * Removes all the cached files starting with $prefix
	 * @since 2.1
	 * @param string $prefix The prefix
	 * @throws Exception
	 * @return void
	 */
	public function deleteCacheByPrefix($prefix);

	/**
	 * Deletes all the cached files
	 */
	public function flushCache();

	/**
	 * Gets a cached file by it's name<br/>
	 * @param string name The name of the cached file
	 * @return mixed The cached string, or false when not found
	 */
	function getCache($name);
} 