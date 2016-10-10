<?php
namespace fur\bright\entities;
/**
 * This class defines the File object, used by the files classes
 * Version history
 * 2.3 20121217
 * - Added exif
 * 2.2 20120207
 * - Added width
 * - Added height
 * - iptc became deprecated
 * @author Fur
 * @version 2.3
 * @package Bright
 * @subpackage objects
 */
class OFile {
	
	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OFile';
	
	/**
	 * @var string The filename of the file
	 */
	public $filename = '';
	/**
	 * @var string The (relative) path of the file
	 */
	public $path = '';
	/**
	 * @var string The extension of the file
	 */
	public $extension = '';
	/**
	 * @var string The size of the file, in bytes
	 */
	public $filesize = 0;
	/**
	 * @var int Unix Timestamp of last modificationdate
	 * @since 2.1 - 1 jun 2010
	 */
	public $modificationdate = 0;
	/**
	 * @var array An array containing IPTC data
	 * @since 2.1 - 1 jun 2010
	 * @deprecated 2.2 - Feb 7, 2012
	 */
	public $iptc;
	
	/**
	 * @var int The width, if it's an image
	 * @since 2.2 - Feb 7, 2012
	 */
	public $width;
	
	/**
	 * @var int The height, if it's an image
	 * @since 2.2 - Feb 7, 2012
	 */
	public $height;
	
	public $exif;
	
	
}