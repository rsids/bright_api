<?php
namespace fur\bright\api\files;
use fur\bright\api\config\Config;
use fur\bright\api\page\Page;
use fur\bright\core\Connection;
use fur\bright\entities\OFile;
use fur\bright\entities\OFolder;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\FilesException;
use fur\bright\exceptions\ParameterException;
use fur\bright\Permissions;
use fur\bright\utils\BrightUtils;

/**
 * The Files class manages the files uploaded by the user.
 * Version history:
 * 2.4 20120411
 * - Added FTP functionality for deleting folders
 * 2.3 20120207
 * - Added deleteFiles
 * - Added exifdata & iptc data
 * 2.2 20120125
 * - Uploadfolder is created if not existent
 * @author Ids Klijnsma - Fur
 * @copyright Copyright &copy; 2010 - 2012, Fur
 * @version 2.4
 * @package Bright
 * @subpackage files
 */
class Files extends Permissions  {

	/**
	 * @var \stdClass Object holding the paths and folders of the userfiles
	 */
	private $filesettings;

	/**
	 * @var Connection Holds the Connection singleton
	 */
	private $_conn;


	function __construct() {
		parent::__construct();
		$this -> IS_AUTH = true;
		$this -> _conn = Connection::getInstance();
		$cfg = new Config();
		$this -> filesettings = $cfg -> getFileSettings();
	}


	/**
	 * Gets the filesettings
	 * @return \stdClass Object holding the paths and folders of the userfiles
	 */
	public function getConfig() {
		return $this -> filesettings;
	}

	/**
	 * Gets additional information about a file;
	 * @param string $file The path to the file
	 * @since 2.3
	 * @return object An object containing the file size, and, if it's an image, the dimensions of the image
	 */
	public function getProperties($file) {
		$file = filter_var($file, FILTER_SANITIZE_STRING);
		if(strpos($file, '..'))
			return null;
		$fname = BASEPATH . UPLOADFOLDER . $file;
		if(file_exists($fname) && !is_dir($fname)) {
			// it's a file;
			$stats = @stat($fname);
			$imsize = @getimagesize($fname);
			$obj = new \stdClass();
			if($stats) {
				$obj -> filesize = $stats[7];
			}
			if($imsize) {
				$obj -> width = $imsize[0];
				$obj -> height = $imsize[1];
			}
			if(!$stats && !$imsize) {
				return null;
			}
			return $obj;
		}
		return null;
	}

    /**
     * Gets the subfolders of a given directory<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param string $dir The parent directory, relative to the filepath specified in config.ini
     * @return array An array of directories (OFolders)
     * @throws \Exception
     */
	public function getSubFolders($dir = '') {
		$folders = array();
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }

		if($dir == '/')
			$dir = '';

		$folder = str_replace('//', '/', BASEPATH . UPLOADFOLDER . $dir . '/');
		if(!is_dir(BASEPATH . UPLOADFOLDER)) {
			try {
				@mkdir(BASEPATH . UPLOADFOLDER);
			} catch(\Exception $ex) {
				throw $this -> throwException(FilesException::FOLDER_NOT_FOUND);
			}
		}
		if(!is_dir($folder))
			throw $this -> throwException(FilesException::FOLDER_NOT_FOUND);

		$files = scandir($folder);
		foreach($files as $file) {
			if ($file != '.' && $file != '..' && is_dir ($folder . $file)) {

				$of = new OFolder();
				$of -> label = $file;
				$of -> path = str_replace(BASEPATH . UPLOADFOLDER, '', $folder . $file . '/');
				try {
					$of -> numChildren = 0;
					$subFolder = scandir($folder . $file);
					foreach($subFolder as $sf) {
						if($sf != '.' && $sf != '..' && is_dir ($folder . $file . '/' . $sf)) {
							$of -> numChildren++;
						}
					}
				} catch(\Exception $ex) {
					$of -> numChildren = 0;
				}

				if($of -> numChildren > 0)
					$of -> children = array();

				$folders[] = $of;
				unset($of);
			}

		}

		usort($folders, array($this, '_sortFolders'));
		return $folders;
	}

    /**
     * Gets the entire folder structure of the user upload directory<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @return array A multi-dimensional array of OFolders
     * @throws \Exception
     */
	public function getStructure() {
		if(!$this -> IS_AUTH) {
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);
        }

		$folder = new OFolder();
		$folder -> label = str_replace('/', '', UPLOADFOLDER);
		$folder -> path = '/';
		$folder -> children = $this -> _readFolder('');
		$folder -> numChildren = count($folder -> children);
		return array($folder);
	}

	/**
	 * Recursive function to walk the folder tree of the user upload directory
	 * @param string $path The path of the folder
	 * @return array An array of folders
	 */
	private function _readFolder($path) {
		$folder = str_replace('//', '/', BASEPATH . UPLOADFOLDER . $path . '/');
		$files = scandir($folder);
		$folders = array();
		foreach($files as $file) {
			if ($file != '.' && $file != '..' && is_dir ($folder . $file)) {

				$of = new OFolder();
				$of -> label = $file;
				$of -> path = str_replace(BASEPATH . UPLOADFOLDER,  '',  $folder . $file . '/');
				try {
					$of -> numChildren = count(scandir($folder . $file)) - 2;
				} catch(\Exception $ex) {
					$of -> numChildren = 0;
				}

				if($of -> numChildren > 0)
					$of -> children = $this -> _readFolder($path . '/' . $file);

				$folders[] = $of;
				unset($of);
			}

		}

		usort($folders, array($this, '_sortFolders'));
		return $folders;
	}

    /**
     * Gets the files in the given folder
     * @param string $dir The foldername
     * @param bool $returnThumbs
     * @param null $exclude_ext
     * @param bool $extended
     * @param null $include_ext
     * @return array An array of files
     * @throws \Exception
     */
	public function getFiles($dir = '', $returnThumbs = true, $exclude_ext = null, $extended = false, $include_ext = null) {
		if($dir == '/')
			$dir = '';

		$folder = str_replace('//', '/', BASEPATH . UPLOADFOLDER . $dir . '/');
		if(!is_dir($folder))
			throw $this -> throwException(FilesException::FOLDER_NOT_FOUND);

		if(!$exclude_ext)
			$exclude_ext = array();

		$result = array();
		$files = scandir($folder);
		foreach($files as $file) {
			if ($file != '.' && $file != '..' && !is_dir ($folder . $file) && $file != '.htaccess') {
				$path_parts 	= pathinfo($folder . $file);
				$path_ext		= array_key_exists('extension', $path_parts) ? strtolower($path_parts['extension']) : '';
				if($path_ext == '') {
					$af = explode('.', $file);
					$path_ext = array_pop($af);
				}
				$path_file		= strtolower($path_parts['filename']);
				if(strpos($path_file, '__thumb__') === false || $returnThumbs) {

					if(count($exclude_ext) == 0 || !in_array($path_ext, $exclude_ext) && ($include_ext == null || in_array($path_ext, $include_ext))) {

						$of = new OFile();
						$of -> filename = $file;
						$of -> path = str_replace(BASEPATH . UPLOADFOLDER, '', $folder);
						$of -> extension = $path_ext;
                        $of -> modificationdate = filemtime($folder . $file);
						$of -> filesize = filesize($folder . $file);

						$result[] = $of;
						unset($of);
					}

				}
			}

		}
		usort($result, array($this, '_sortFiles'));
		return $result;
	}

    /**
     * Creates a folder<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param string $folderName The name of the folder to create
     * @param string $dir The path of the parentfolder, relative to the base folder specified in the config.ini
     * @return array An array of folders, which are the subfolders of $dir
     * @throws \Exception
     */
	public function createFolder($folderName, $dir) {

		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }

		$folder = str_replace('//', '/', BASEPATH . UPLOADFOLDER . $dir . '/');
		if(!is_dir($folder))
			throw $this -> throwException(4002);

		if(is_dir($folder . $folderName))
			return $this -> getSubFolders($dir);

		$success = false;

		if ($folderName != '') {
			if(USEFTPFORFOLDERS === true) {

				$folder = str_replace('//', '/', FTPBASEPATH . UPLOADFOLDER . $dir . '/');

                $connectionId = ftp_connect(FTPSERVER);
				ftp_login($connectionId, FTPUSER, FTPPASS);
				$success = ftp_mkdir($connectionId, $folder . $folderName) !== false;
				ftp_site($connectionId, 'CHMOD 777 ' . $folder . $folderName);
				ftp_close($connectionId);

			} else {
				$success = mkdir($folder .$folderName);
			}
		}
		if(!$success)
			throw $this -> throwException(4008);

		return $this -> getSubFolders($dir);
	}

    /**
     * Deletes a directory<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param string $folderName The name of directory to delete
     * @param string $parent The directory in which the dir to delete is located, relative to the base folder specified in the config.ini
     * @return array The sub-dirs of $parent
     * @throws \Exception
     */
	public function deleteFolder($folderName, $parent) {
		if(!$this -> IS_AUTH)
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);

		$folder = str_replace('//', '/', BASEPATH . UPLOADFOLDER . $parent . '/');

		if(!is_dir($folder . $folderName))
			throw $this -> throwException(FilesException::FOLDER_NOT_FOUND);


		$success = false;
		$arr = scandir($folder . $folderName);

		if ($folderName != '' && count($arr) == 2) {
			if(USEFTPFORFOLDERS === true) {

				$folder = str_replace('//', '/', FTPBASEPATH . UPLOADFOLDER . $parent . '/');

                $connectionId = ftp_connect(FTPSERVER);
				ftp_login($connectionId, FTPUSER, FTPPASS);
				$success = ftp_rmdir($connectionId, $folder . $folderName);
				ftp_close($connectionId);

			} else {
				$success = rmdir($folder.$folderName);
			}
		}
		if(!$success)
			throw $this -> throwException(4004);

		return $this -> getSubFolders($parent);
	}


    /**
     * Moves a file from oldpath to newpath<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param string $oldPath The directory where the file currently resides, relative to the base folder specified in the config.ini
     * @param string $newPath The target directory, relative to the base folder specified in the config.ini
     * @param string $filename The file to move
     * @return array The contents of oldpath
     * @throws \Exception
     */
	public function moveFile ($oldPath, $newPath, $filename) {
		if(!$this -> IS_AUTH)
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);

		if(!is_dir(BASEPATH . UPLOADFOLDER . $oldPath)) {
            throw $this->throwException(FilesException::PARENT_NOT_FOUND);
        }

		if(!file_exists(BASEPATH . UPLOADFOLDER . $oldPath . $filename)) {
            throw $this->throwException(FilesException::FILE_NOT_FOUND);
        }

		if(file_exists(BASEPATH . UPLOADFOLDER . $newPath . $filename)) {
            throw $this->throwException(FilesException::DUPLICATE_FILENAME);
        }

		$thumbParts = explode('.', $filename);
		$thumbParts[count($thumbParts) - 2] .= '__thumb__';
		$thumb = join('.', $thumbParts);
		$ext = $thumbParts[count($thumbParts) - 1];
		if(file_exists(BASEPATH . UPLOADFOLDER . $oldPath . $thumb))
			rename(BASEPATH . UPLOADFOLDER . $oldPath . $thumb, BASEPATH . UPLOADFOLDER . $newPath . $thumb);

		if(strpos($filename, '__thumb__') !== false) {
			// Dragged file IS a thumb, move original too
			$thumbParts = explode('__thumb__', $filename);
			$thumb = join('', $thumbParts);
			if(file_exists(BASEPATH . UPLOADFOLDER . $oldPath . $thumb))
				rename(BASEPATH . UPLOADFOLDER . $oldPath . $thumb, BASEPATH . UPLOADFOLDER . $newPath . $thumb);
		}
		if(strtolower($ext) === 'pdf') {
			// Update page
			$page = new Page();
			$p = $page -> getPageByLabel(md5($oldPath . $filename));
			if($p) {
				$p -> label = md5($newPath . $filename);
				$p -> content -> path -> all = $oldPath . $filename;
				$page -> setPage($p);
			} else {
				// Re-index
			}
		}

		rename(BASEPATH . UPLOADFOLDER . $oldPath . $filename, BASEPATH . UPLOADFOLDER . $newPath . $filename);
		return $this -> getFiles($oldPath);

	}

    /**
     * Deletes a file<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>DELETE_FILE</li>
     * </ul>
     * @param string $filename The file to delete
     * @param string $dir The path of the file, relative to the base folder specified in the config.ini
     * @param boolean $throwNotExistsException When true, an exception is thrown when the specified file does not exist
     * @return array An array of files, which are in $path
     * @throws \Exception
     */
	public function deleteFile($filename, $dir, $throwNotExistsException = false) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }

		if(!$this -> DELETE_FILE) {
            throw $this->throwException(FilesException::FILE_DELETE_NOT_ALLOWED);
        }

		$folder = str_replace('//', '/', BASEPATH . UPLOADFOLDER . $dir . '/');
		if(!is_dir($folder)) {
            throw $this->throwException(FilesException::PARENT_NOT_FOUND);
        }


		$thumbParts = explode('.', $filename);
		$thumbParts[count($thumbParts) - 2] .= '__thumb__';
		$thumb = join('.', $thumbParts);

		if(file_exists($folder . $thumb))
			unlink($folder . $thumb);

		if(file_exists($folder . $filename)) {
			// Prevent unlink error
			try {
				chmod($folder . $filename, 0666);
			} catch(\Exception $ex) {
				/*Swallow it*/
			}
            unlink($folder . $filename);

		} else if($throwNotExistsException) {
			throw $this -> throwException(4005);
		}

		return $this -> getFiles($dir);
	}

	public function deleteFiles($files, $path) {
		foreach($files as $file) {
			$this -> deleteFile($file[0], $file[1]);
		}
		return $this -> getFiles($path);
	}

    /**
     * Downloads a file from the given url and stores it on the server
     * @param string $url The url of the file
     * @param string $filename The filename on the local server
     * @param string $parent The parent folder
     * @return object
     * @throws \Exception
     */
	public function uploadFromUrl($url, $filename, $parent) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }
		
		if($filename === '') {
			$ua = explode('/', $url);
			$filename = array_pop($ua);
		}
		$url = str_replace(' ', '%20', $url);
		$url = filter_var($url, FILTER_VALIDATE_URL);
		$filename = filter_var($filename, FILTER_SANITIZE_STRING);
		$filename = preg_replace('/[\s&\|\+\!\(\)]/', '-', $filename);
		$filename = str_replace('%20', '-', $filename);
		while(strpos($filename, '--')) {
			$filename = str_replace('--', '-', $filename);
		}
		$parent = filter_var($parent, FILTER_SANITIZE_STRING);
		
		if($url === false || $filename === false || $parent === false) {
			throw $this -> throwException(ParameterException::STRING_EXCEPTION, __LINE__);
		}
		
		if(strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
			throw $this -> throwException(ParameterException::STRING_EXCEPTION, __LINE__);
		}
		
		if(strpos($parent, '.') !== false) {
			throw $this -> throwException(ParameterException::STRING_EXCEPTION, __LINE__);
		}
		
		
		if(!is_dir(BASEPATH . UPLOADFOLDER . $parent))
			throw $this -> throwException(FilesException::FOLDER_NOT_FOUND);
		
		$local = BASEPATH . UPLOADFOLDER . $parent . '/' . $filename;
		$i = 1;
		
		$fileParts = explode('.', $filename);
		$ext = array_pop($fileParts);
		if(count($fileParts) > 0) {
			$file = implode('.', $fileParts);
		} else {
			$file = $ext;
		}
		
		if(BrightUtils::endsWith($file, '-'))
			$file = substr($file, 0, -1);
		
		while(file_exists($local)) {
			$local = BASEPATH . UPLOADFOLDER . "$parent/$file-$i.$ext"; 
			$i++;
		}
		
		try {
			$ext = @file_get_contents($url);
		} catch(\Exception $e) {
			throw $this -> throwException(FilesException::UPLOAD_FAILED);
		}
		
		if($ext === false) {
            throw $this->throwException(FilesException::UPLOAD_FAILED);
        }
			
		$res = file_put_contents($local, $ext);
		if($res === false) {
            throw $this->throwException(FilesException::UPLOAD_FAILED);
        }
		
		
		if(strpos($filename, '.') === false) {
			// No extension supplied, add it.
			$imgType = exif_imagetype($local);
			if($imgType !== false) {
				$add = '';
				
				switch($imgType) {
					case IMAGETYPE_GIF:		$add = '.gif';break;
					case IMAGETYPE_JPEG:	$add = '.jpg';break;
					case IMAGETYPE_PNG:		$add = '.png';break;
					case IMAGETYPE_SWF:		$add = '.swf';break;
					case IMAGETYPE_PSD:		$add = '.psd';break;
					case IMAGETYPE_BMP:		$add = '.bmp';break;
					case IMAGETYPE_TIFF_II:	$add = '.tiff';break;
					case IMAGETYPE_TIFF_MM:	$add = '.tiff';break;
				}
				if($add != '') {
					// Found correct extension
					rename($local, $local . $add);
					$filename .= $add;
				}
			}
		}
		return (object) array('files' => $this -> getFiles($parent), 'file' => $filename);
	}
	

	private function _sortFiles($a, $b) {
		return strcmp(strtoupper($a -> filename), strtoupper($b -> filename));
	}

	private function _sortFolders($a, $b) {
		return strcmp(strtoupper($a -> label), strtoupper($b -> label));
	}
	
	
}