<?php
namespace fur\bright\api\maps;
use fur\bright\api\cache\Cache;
use fur\bright\api\config\Config;
use fur\bright\api\page\Page;
use fur\bright\core\Connection;
use fur\bright\entities\OMarker;
use fur\bright\entities\OPoly;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\ParameterException;
use fur\bright\Permissions;
use fur\bright\utils\BrightUtils;
use fur\bright\utils\ColorUtils;
use fur\bright\utils\ImageUtils;

/**
 * Handles the creating, updating and returning of pages.<br/>
 * This class handles the storing and retreiving of google maps
 * Version history:
 * 20130311 1.1
 * - Added getLayerColor;
 * @author Fur
 * @version 1.1
 * @package Bright
 * @subpackage maps
 */
class Maps extends Permissions  {
	
	const GEO_LOOKUPADDRESS = 1;
	const GEO_LOOKUPLATLNG = 2;

	private $_conn;
	
	private $_hook;


	function __construct() {
		parent::__construct();

		$this -> _conn = Connection::getInstance();
		
		if(class_exists('MarkerHook', true)) {
			$this -> _hook = new \MarkerHook();
		}
	}
	
	public function createMarker($color) {
		$folder = BASEPATH . UPLOADFOLDER . 'markers/';
		if(!file_exists($folder) || !is_dir($folder))
			mkdir($folder);
		if(file_exists($folder . $color .'.png'))
			return;
			
		$image = imagecreatefrompng(dirname(__FILE__) . '/marker_gray.png');
	
		imagealphablending($image, true);
		imagesavealpha($image, true);
	
		# Convert hex colour into RGB values
		$hsl = ColorUtils::hex2hsl(hexdec('0x' . $color));
		ImageUtils::ImageHue($image, $hsl['h'], $hsl['s']);
	
		imagepng($image, $folder . $color .'.png');
		imagedestroy($image);
	}

	public function getMarkerByPageId($pageId, $full = false, $enabledonly = true) {
		return $this -> _getMarker($pageId, $full, $enabledonly, true);
		
	}
	/**
	 * Gets a marker by it's id
	 * @param int $markerId The id of the polyline / shape
	 * @param boolean $full When true, also the contents of the info window are returned (if present)
	 * @param boolean $enabledOnly When true, the marker is only return when enabled is set to true
	 * @return OMarker A marker
	 */
	public function getMarker($markerId, $full = false, $enabledOnly = true) {
		return $this -> _getMarker($markerId, $full, $enabledOnly, false);
	}

	public function getMarkerByLabel($label) {
		$label = Connection::getInstance() -> escape_string(filter_var($label, FILTER_SANITIZE_STRING));
		$mid = $this -> _conn -> getField("SELECT `markerId` FROM `gm_markers` WHERE `deleted`=0 AND `label`='$label'");
		if($mid)
			return $this -> getMarker($mid, true);
		return null;
	}

    /**
     * Gets the label of a marker
     * @param int $markerId
     * @return string
     */
	public function getMarkerLabel($markerId) {
		$sql = 'SELECT `label` FROM `gm_markers` WHERE `deleted`=0 AND markerId=' . (int) $markerId;
		return $this -> _conn -> getField($sql);
	}

    /**
     * Gets all the markers in a layer
     * @param int $layerId The id of the layer
     * @param boolean $enabledOnly When true, only markers are returned when enabled is set to true
     * @param array $additionalFields Additional template fields to include in the array
     * @return array An array of OMarkers
     */
	public function getMarkers($layerId = 0, $enabledOnly = true, $additionalFields = null) {
		$esql = ($enabledOnly) ? ' AND enabled=1' : '';
		
		$layerId = (int) $layerId;
		if($additionalFields) {
			$sql = "SELECT m.*, p.pageId,
					p.itemType,
					p.label,
					UNIX_TIMESTAMP(p.publicationdate),
					UNIX_TIMESTAMP(p.expirationdate),
					UNIX_TIMESTAMP(p.modificationdate),
					UNIX_TIMESTAMP(p.creationdate) ";
			
			$joins = array("LEFT JOIN page p ON m.pageId = p.pageId ");
			
			if(count($additionalFields) != 0) {
				$fields = array();
				foreach($additionalFields as $field) {
					$fields[] = ' COALESCE(co' . Connection::getInstance() -> escape_string($field) . '.value, \'\') as `' . Connection::getInstance() -> escape_string($field) .'` ';
					$joins[] = 'LEFT JOIN content co' . Connection::getInstance() -> escape_string($field) . ' ON p.pageId = co' . Connection::getInstance() -> escape_string($field) . '.pageId AND co' . Connection::getInstance() -> escape_string($field) . '.`lang`=\'nl\' AND co' . Connection::getInstance() -> escape_string($field) . '.`field`=\'' . Connection::getInstance() -> escape_string($field) . '\' ';
				}
				$sql .= ', ' . join(', ', $fields);
			}
			$sql .= "FROM gm_markers m\r\n";
			$sql .= join("\r\n", $joins) . "\r\n";
			$sql .= 'INNER JOIN itemtypes it ON p.itemType = it.itemId AND it.templatetype = 5';
			$sql .= " WHERE m.deleted=0 $esql";
		} else {
			$sql = "SELECT * FROM gm_markers WHERE `deleted`=0 $esql" ;
			
		}
		if($layerId > 0) 
			$sql .= " AND layer=$layerId";
// 		echo $sql;
		return $this -> _conn -> getRows($sql, 'OMarker');
	}

    /**
     * Gets all the markers and polys
     * @param boolean $updatesOnly When true, only updates values are fetched
     * @param null $search
     * @param bool $enabledOnly
     * @return \stdClass
     */
	public function getMarkersAndPolys($updatesOnly = false, $search = null, $enabledOnly = true) {
		$settings = $this -> getSettings();
		$additionalFields = array();
		if($settings) {
			// Check which additional columns to fetch
			if($settings !== null && isset($settings -> maps) && isset($settings -> maps -> visibleColumns)) {
				foreach($settings -> maps -> visibleColumns as $col) {
					// Check if it's a valid column (if it's in mapsColumns, it's returned anyway)
					if(!in_array($col, Config::$mapsColumns) || $col == 'title') {
						$additionalFields[] = $col;
					}
				}

			}
		}

		$tables = array(array('gm_markers', 'gmm', 'OMarker'), array('gm_polys', 'gmp', 'OPoly'));
		$result = new \stdClass();
		foreach($tables as $table) {
			$esql = ($enabledOnly) ? " AND {$table[1]}.`enabled`=1" : '';
			$sql = "SELECT {$table[1]}.*, p.pageId,
					p.itemType,
					p.label,
					it.icon as `icon`,
					it.lifetime as `lifetime`,
					it.label as `itemLabel`,
					UNIX_TIMESTAMP(p.modificationdate) as `modificationdate`,
					UNIX_TIMESTAMP(p.publicationdate) as `publicationdate`,
					UNIX_TIMESTAMP(p.expirationdate) as `expirationdate`,
					p.alwayspublished,
					p.showinnavigation ";
			$joins = array("LEFT JOIN page p ON {$table[1]}.pageId = p.pageId ");
			if(count($additionalFields) != 0) {
				$fields = array();
				foreach($additionalFields as $field) {
					$fields[] = ' COALESCE(co' . Connection::getInstance() -> escape_string($field) . '.value, \'\') as `' . Connection::getInstance() -> escape_string($field) .'` ';
					$joins[] = 'LEFT JOIN content co' . Connection::getInstance() -> escape_string($field) . ' ON p.pageId = co' . Connection::getInstance() -> escape_string($field) . '.pageId AND co' . Connection::getInstance() -> escape_string($field) . '.`lang`=\'nl\' AND co' . Connection::getInstance() -> escape_string($field) . '.`field`=\'' . Connection::getInstance() -> escape_string($field) . '\' ';
				}
				$sql .= ', ' . join(', ', $fields);
			}
			$sql .= "FROM {$table[0]} {$table[1]}\r\n";
			$sql .= join("\r\n", $joins) . "\r\n";
			$sql .= 'INNER JOIN itemtypes it ON p.itemType = it.itemId AND it.templatetype = 5';
			$sql .= " WHERE {$table[1]}.deleted=0 $esql";
			
			if($updatesOnly &&  isset($_SESSION['getMarkersAndPolys_lastUpdate']) &&  $_SESSION['getMarkersAndPolys_lastUpdate'] != '') {
				$sql .= ' AND UNIX_TIMESTAMP(p.modificationdate) > ' . $_SESSION['getMarkersAndPolys_lastUpdate'] . ' ';
			}
			
			if($search !== null) {
				$search = Connection::getInstance() -> escape_string($search);
				$sql .= "AND (MATCH(`search`) AGAINST('$search')) ";
			}

			$sql .=	' ORDER BY p.modificationdate DESC';
			$result -> {$table[0]} = $this -> _conn -> getRows($sql, $table[2]);
		}
		$_SESSION['getMarkersAndPolys_lastUpdate'] = time();

		return $result;


	}
	
	/**
	 * Gets all the markers of the given template type
	 * @param int $type The type of the template
	 * @param boolean $enabledOnly When true, only enabled markers are returned
	 * @param bool $full When true, the full content of the marker is returned
	 * @return OMarker
	 */
	public function getMarkersByType($type, $enabledOnly = true, $full = false) {
		$esql = ($enabledOnly) ? ' AND `enabled`=1' : '';
		$type = (int)$type;
		$sql = "SELECT gm.* FROM gm_markers gm
				INNER JOIN page p ON p.pageId = gm.pageId AND p.itemType=$type
				WHERE `deleted`=0 $esql";
		
		$markers = $this -> _conn -> getRows($sql, 'OMarker');
		if($markers && $full === true) {
			foreach($markers as &$marker) {
				$marker = $this -> getMarker($marker -> markerId, true, true);
			}
		}
		return $markers;
	}
	
	public function geocode($mode, $marker) {
		$marker = (object) $marker;
		$baseurl = 'http://maps.googleapis.com/maps/api/geocode/json?sensor=false';
		switch($mode) {
			case Maps::GEO_LOOKUPADDRESS:
				$baseurl .= '&latlng=' . $marker  -> lat . ',' . $marker -> lng;
				break;
			case Maps::GEO_LOOKUPLATLNG:
				$parts = array();
				if($marker -> street != null && $marker -> street != '') {
					$s = $marker -> street;
					if($marker -> number != null && $marker -> number != '') {
						$s .= ' ' . $marker -> number;
					}
					$parts[] = urlencode(trim($s));
				}
				
				if($marker -> zip != null && $marker -> zip != '') {
					$parts[] = urlencode(trim($marker -> zip));
				}
				
				if($marker -> city != null && $marker -> city != '') {
					$parts[] = urlencode(trim($marker -> city));
				}
				$baseurl .= '&address=' . implode(',', $parts);

				break;
					
		}
		$data = @file_get_contents($baseurl);
		if(!$data)
			return false;
		
		return @json_decode($data);
	}

    /**
     * Deletes a marker
     * @param int $markerId The marker to delete
     * @return bool
     * @throws \Exception
     */
	public function deleteMarker($markerId) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }

		if(!is_numeric($markerId)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }
		
		$cache = new Cache();
		$cache -> deleteCacheByPrefix('marker');
		$sql = 'UPDATE `gm_markers` SET `deleted` = 1 WHERE markerId=' . $markerId;
		return $this -> _conn -> updateRow($sql);
	}

	/**
	 * Gets the initial settings for the map editor
	 */
	public function getDefaultSettings() {
		return (object) array('zoom' => 8, 'lat' => 53, 'lng' => 6.5);
	}
	
	/**
	 * Returns the default color of the layer
	 * @param int $layerId The id of the layer
	 * @return double the color of the Layer
	 * @since
	 */
	public function getLayerColor($layerId) {
		$layerId = (int) $layerId;
		return (double) $this -> _conn -> getField("SELECT color FROM gm_layers WHERE layerId=$layerId");
	}

	/**
	 * Gets a poly by it's id
	 * @param int $polyId The id of the polyline / shape
	 * @param boolean $full When true, also the contents of the infowindow are returned (if present)
	 * @return OPoly A poly
	 */
	public function getPoly($polyId, $full = false) {
		$sql = 'SELECT * FROM `gm_polys` WHERE `deleted`=0 AND polyId=' . (int) $polyId;
		$poly = $this -> _conn -> getRow($sql, 'OPoly');
		$poly -> points = $this -> _getPolyPoints($poly -> polyId);

		if(!$full)
			return $poly;

		$page = new Page();
		$contents = $page -> getPageById($poly -> pageId);
		if($contents) {
			foreach($contents as $key => $value) {
				$poly -> {$key} = $value;
			}
			$poly -> _explicitType = 'OPoly';
		}

		return $poly;
	}

	/**
	 * Gets all the polys of a layer
	 * @param int $layerId The id of the layer
	 * @return array An array of OPolys
	 */
	public function getPolys($layerId, $enabledonly = true) {
		$esql = ($enabledonly) ? ' AND `enabled`=1' : '';
		$layerId = (int) $layerId;
		$sql = "SELECT * FROM gm_polys WHERE `deleted`= 0 $esql AND layer=$layerId";
		$polys = $this -> _conn -> getRows($sql, 'OPoly');
		foreach($polys as $i => $poly) {
			$poly -> points = $this -> _getPolyPoints($poly -> polyId);
			$polys[$i] = $poly;
		}
		return $polys;
	}

    /**
     * Saves a full marker
     * @param OMarker $marker
     * @return OMarker The marker
     * @throws \Exception
     */
	public function setFullMarker(OMarker $marker) {
		$page = new Page();

		
		// Store marker specific fields
		$msettings = new OMarker();
		
		$dbls = array('lat','lng');
		$ints = array('layer', 'uselayercolor','enabled','pageId','markerId','color','iconsize');
		$strings = array('icon', 'label', 'street', 'number', 'zip', 'city');
		
		if(!$marker -> markerId) {
			$nm = $this -> setMarker($marker -> lat, $marker -> lng, $marker -> layer);
			$marker -> markerId = $nm -> markerId;
		}
		
		if(method_exists($this -> _hook, 'preSetMarker'))
			$marker = $this -> _hook -> preSetMarker($marker);
		
		$p = $page -> setPage($marker, false, false);
		if($marker -> pageId == 0) {
			$marker -> pageId = $p -> pageId;
		}
		
		
		BrightUtils::forceDouble($marker, $dbls);
		BrightUtils::forceInt($marker, $ints);
		BrightUtils::escape($marker, $strings);

		if($marker -> uselayercolor === null || $marker -> uselayercolor === false || $marker -> uselayercolor === '') {
			$marker -> uselayercolor = 0;
		}
		
		if($marker -> enabled === null || $marker -> enabled === false || $marker -> enabled === '') {
			$marker -> enabled = 0;
		}
		
		$ss = Connection::getInstance() -> escape_string(BrightUtils::createSearchString($marker));
		$sql = "UPDATE `gm_markers` 
				SET `lat`= {$marker -> lat}, 
				`lng`= {$marker -> lng}, 
				`layer`= {$marker -> layer}, 
				`uselayercolor`= {$marker -> uselayercolor}, 
				`enabled`= {$marker -> enabled}, 
				`pageId`= {$marker -> pageId}, 
				`color`= {$marker -> color}, 
				`iconsize`= {$marker -> iconsize}, 
				`icon`='{$marker -> icon}', 
				`label`='{$marker -> label}', 
				`search`='{$ss}',
				`street`='{$marker -> street}', 
				`number`='{$marker -> number}', 
				`zip`='{$marker -> zip}', 
				`city`='{$marker -> city}' 
				WHERE `markerId`={$marker -> markerId}";
		
		$this -> _conn -> updateRow($sql);

		if(method_exists($this -> _hook, 'postSetMarker')) {
			$this -> _hook -> postSetMarker($marker);
		}
		
		$cache = new Cache();
		$cache -> deleteCacheByPrefix('marker');
		return $this -> getMarker($marker -> markerId, true, false);
	}

    /**
     * Saves a full poly
     * @param OPoly $poly
     * @return OPoly
     */
	public function setFullPoly(OPoly $poly) {
		$page = new Page();
		$p = $page -> setPage($poly, false, false);
		if($poly -> pageId == 0) {
			$poly -> pageId = $p -> pageId;
		}
		$sql = 'UPDATE `gm_polys` ' .
				'SET `layer`=' . (int)$poly -> layer . ', ' .
				'`uselayercolor`=' . (int)$poly -> uselayercolor . ', ' .
				'`enabled`=' . (int)$poly -> enabled . ', ' .
				'`pageId`=' . (int)$poly -> pageId . ', ' .
				'`color`=' . (int)$poly -> color . ', ' .
				'`label`=\'' . Connection::getInstance() -> escape_string($poly -> label) . '\', ' .
				'`search`=\'' . Connection::getInstance() -> escape_string(BrightUtils::createSearchString($poly)) . '\' ' .
				'WHERE `polyId`=' . (int)$poly -> polyId;
		$this -> _conn -> updateRow($sql);
		
		$cache = new Cache();
		$cache -> deleteCacheByPrefix('marker');
		return $this -> getPoly($poly -> polyId, true);
	}

    /**
     * Creates a new marker at $lat, $lng in $layer
     * @param double $lat The latitude of the marker
     * @param double $lng The longitude of the marker
     * @param int $layer The id of the layer
     * @return OMarker
     * @throws \Exception
     */
	public function setMarker($lat, $lng, $layer) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }

		if(!is_numeric($lat) || !is_numeric($lng) || !is_numeric($layer)) {
			throw $this -> throwException(ParameterException::INTEGER_EXCEPTION);
        }

		$lat = (double)$lat;
		$lng = (double)$lng;
		$layer = (int)$layer;

		if($layer == -1) {
			$layer = '(SELECT MAX(`layerId`) FROM `gm_layers`)';
		}

		$sql = "INSERT INTO `gm_markers` (`lat`, `lng`, `layer`, `color`, `uselayercolor`, `enabled`)
				VALUES ({$lat}, {$lng},{$layer}, (SELECT `color` FROM `gm_layers` WHERE `layerId`={$layer}), 1,1)";
		$id = $this -> _conn -> insertRow($sql);
		$cache = new Cache();
		$cache -> deleteCacheByPrefix('marker');
		return $this -> getMarker($id);
	}

	/**
	 * Updates the position of a marker
	 */
	public function setMarkerPosition($markerId, $lat, $lng) {
		if(!$this -> IS_AUTH)
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);

		if(!is_numeric($markerId) || !is_numeric($lat) || !is_numeric($lng))
			throw $this -> throwException(2002);
		
		$cache = new Cache();
		$cache -> deleteCacheByPrefix('marker');
		$sql = 'UPDATE `gm_markers` SET lat=' . $lat . ', lng=' . $lng . ' WHERE markerId=' . $markerId;
		return $this -> _conn -> updateRow($sql);

	}

    /**
     * Creates or updates a poly
     * @param OPoly $poly The poly to update or insert
     * @return OPoly The inserted / updated poly
     * @throws \Exception
     * @see objects.OPoly
     * @since 0.3 - 17 dec 2010
     */
	public function setPoly(OPoly $poly) {
		if(!$this -> IS_AUTH)
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);

		if($poly -> polyId == 0) {
			$poly -> polyId = $this -> _createPoly($poly);
		} else {
			$this -> _updatePoly($poly);
		}

		$this -> _setPolyPoints($poly);

		return $this -> getPoly($poly -> polyId);
	}

	private function _createPoly(OPoly $poly) {
		$layer = (int) $poly -> layer;
		if($poly -> layer == -1) {
			$layer = '(SELECT MAX(`layerId`) FROM `gm_layers`)';
		}

		$sql = 'INSERT INTO `gm_polys` (`layer`, `color`, `uselayercolor`, `isShape`, `enabled`) ' .
				'VALUES (' .
				$layer .', ' .
				'(SELECT `color` FROM `gm_layers` WHERE `layerId`=' . $layer . '), 1, ' . (int) $poly -> isShape . ', 1)';
		$id = $this -> _conn -> insertRow($sql);
		return $id;
	}
	
	private function _getMarker($id, $full, $enabledOnly, $byPage = false) {
		$id = (int) $id;
		
		$cname = 'marker_' . $id;
		$cname .= ($full) ? 1:0; 
		$cname .= ($enabledOnly) ? 1:0; 
		$cname .= ($byPage) ? 1:0; 
		$cache = new Cache();
		$result = $cache -> getCache($cname);
		if($result)
			return $result;
		
		$esql = ($enabledOnly) ? ' AND `enabled`=1' : '';
		if($byPage) {
			$sql = "SELECT * FROM `gm_markers` WHERE `deleted`=0 $esql AND pageId=$id";
		} else {
			$sql = "SELECT * FROM `gm_markers` WHERE `deleted`=0 $esql AND markerId=$id";
		}
		$marker = $this -> _conn -> getRow($sql, 'OMarker');
		
		if(!$marker)
			return null;
			
		if(!$full) {
			$cache -> setCache($marker, $cname, strtotime('+1 year'));
			return $marker;
		}
		
		$page = new Page();
		$contents = $page -> getPageById($marker -> pageId, false, false);
		if($contents) {
			foreach($contents as $key => $value) {
				$marker -> {$key} = $value;
			}
			$marker -> _explicitType = 'OMarker';
		}
		$cache -> setCache($marker, $cname, strtotime('+1 year'));
		return $marker;
	}

	private function _getPolyPoints($polyId) {
		$sql = 'SELECT lat, lng FROM gm_polypoints WHERE `polyId`=' . (int) $polyId . ' ORDER BY `index`';
		return $this -> _conn -> getRows($sql, 'LatLng');
	}

	private function _setPolyPoints($poly) {
		// Select max
		$sql = 'SELECT MAX(`pointId`) FROM `gm_polypoints` WHERE `polyId`=' . (int) $poly -> polyId;
		$pointId = (int) $this -> _conn -> getField($sql);
		if(is_array($poly -> points) && count($poly -> points) > 0) {
			// Insert new points
			$sql = 'INSERT INTO `gm_polypoints` (`polyId`, `lat`, `lng`, `index`) VALUES ';
			$points = array();
			foreach($poly -> points as $point) {
				$point = (object) $point;
				$points[] = '(' . (int)$poly -> polyId . ', ' . (double) $point -> lat . ', ' . (double) $point -> lng . ', ' . count($points) . ')';
			}
			$sql .= implode(', ', $points);
			$this -> _conn -> insertRow($sql);
		}

		// Clean up again
		$this -> _conn -> deleteRow('DELETE FROM `gm_polypoints` WHERE `polyId`=' . (int) $poly -> polyId . ' AND `pointId` <=' . $pointId);

	}

	private function _updatePoly(OPoly $poly) {
		$layer = (int) $poly -> layer;
		if($poly -> layer == -1) {
			$layer = '(SELECT MAX(`layerId`) FROM `gm_layers`)';
		}
		$color = (double) $poly -> color;
		if($poly -> uselayercolor) {
			$color = '(SELECT `color` FROM `gm_layers` WHERE `layerId`=' . $layer . ')';
		}

		$sql = 'UPDATE `gm_polys` ' .
				'SET `layer`=' . $layer . ', ' .
				'`color`=' . $color . ', ' .
				'`uselayercolor`=' . (int) $poly -> uselayercolor . ', ' .
				'`enabled`=' . (int) $poly -> enabled . ', ' .
				'`deleted`=' . (int) $poly -> deleted . ', ' .
				'`isShape`=' . (int) $poly -> isShape . ' '.
				'WHERE polyId=' . (int) $poly -> polyId;
		$this -> _conn -> updateRow($sql);
		return  (int)$poly -> polyId;
	}

	/**
	 * Colorizes an image
	 * @param resource $im
	 * @param int $r
	 * @param int $g
	 * @param int $b
	 */
	private function _colorize($im,$r,$g,$b) {
		$height = imagesy($im);
	    $width = imagesx($im);
	    for($x=0; $x<$width; $x++){
	        for($y=0; $y<$height; $y++){
	            $old = imageColorAt($im, $x, $y);
   				$a = ($old >> 24) & 0xFF;
	            imagesetpixel($im, $x, $y,imagecolorallocatealpha($im, $r, $g, $b, $a));
	        }
	    }
	}


}