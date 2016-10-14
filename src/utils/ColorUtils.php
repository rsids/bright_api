<?php

namespace fur\bright\utils;

/**
 * Class with color modifying functions
 * For hsv / hsl methods, see http://en.wikipedia.org/wiki/HSL_and_HSV
 * @author ids
 * @copyright 2013 Fur - www.wewantfur.com
 */
class ColorUtils {

	public static function compareColors ($col1, $col2, $tolerance=35, $x, $y) {
		$col1Rgb = array(
				"r" => hexdec(substr($col1, 1, 2)),
				"g" => hexdec(substr($col1, 3, 2)),
				"b" => hexdec(substr($col1, 5, 2))
		);
		$col2Rgb = array(
				"r" => hexdec(substr($col2, 1, 2)),
				"g" => hexdec(substr($col2, 3, 2)),
				"b" => hexdec(substr($col2, 5, 2))
		);
		return ($col1Rgb['r'] >= $col2Rgb['r'] - $tolerance && $col1Rgb['r'] <= $col2Rgb['r'] + $tolerance) && ($col1Rgb['g'] >= $col2Rgb['g'] - $tolerance && $col1Rgb['g'] <= $col2Rgb['g'] + $tolerance) && ($col1Rgb['b'] >= $col2Rgb['b'] - $tolerance && $col1Rgb['b'] <= $col2Rgb['b'] + $tolerance);
	}

	public static function getContrastColor($rgb) {

		// Counting the perceptive luminance - human eye favors green color...
		$a = 1 - ( 0.299 * $rgb['r'] + 0.587 * $rgb['g'] + 0.114 * $rgb['b'])/255;

		if ($a < 0.5)
			$d = 0; // bright colors - black font
		else
			$d = 255; // dark colors - white font

		return array('r' => $d,'g' => $d,'b' => $d);
	}

	/**
	 * Converts a int to a hex string (#nnnnnn)
	 * @param int $dec The color as int
	 * @return string A hex string
	 */
	public static function dec2hex($dec) {
		$rgb = array(
				'r' => $dec >> 16,
				'g' => $dec >> 8 & 0xff,
				'b' => $dec & 0xff
		);
		return self::rgb2hex($rgb);
	}

	/**
	 * Converts a hsv color to a rgb color
	 *
	 * @param array $hsv An array (h,s,v)
	 * @returns array (r,g,b)
	 */
	public static function hsv2rgb($hsv) {
		$h = $hsv['h'];
		$s = $hsv['s'];
		$v = $hsv['v'];
		$rgb = array();
		if ($s == 0) {
			$rgb['r'] = $v * 255;
			$rgb['g'] = $v * 255;
			$rgb['b'] = $v * 255;
		} else {
			$var_h = $h * 6;
			$var_i = floor($var_h);
			$var_1 = $v * (1 - $s);
			$var_2 = $v * (1 - $s * ($var_h - $var_i));
			$var_3 = $v * (1 - $s * (1 - ($var_h - $var_i)));

			if ($var_i == 0) {
				$var_r = $v;
				$var_g = $var_3;
				$var_b = $var_1;
			} else if ($var_i == 1) {
				$var_r = $var_2;
				$var_g = $v;
				$var_b = $var_1;
			} else if ($var_i == 2) {
				$var_r = $var_1;
				$var_g = $v;
				$var_b = $var_3;
			} else if ($var_i == 3) {
				$var_r = $var_1;
				$var_g = $var_2;
				$var_b = $v;
			} else if ($var_i == 4) {
				$var_r = $var_3;
				$var_g = $var_1;
				$var_b = $v;
			} else {
				$var_r = $v;
				$var_g = $var_1;
				$var_b = $var_2;
			}
			;

			$rgb['r'] = $var_r * 255;
			$rgb['g'] = $var_g * 255;
			$rgb['b'] = $var_b * 255;
		}
		return $rgb;

	}

	/**
	 * Converts a hex color (0x######) to a hsv object
	 *
	 * @param int $hex
	 * @returns array (h,s,v)
	 */
	public static function hex2hsv($hex) {
		return ColorUtils::rgb2hsv(ColorUtils::hex2rgb($hex));
	}

	/**
	 * Converts a hex color (0x######) to it's hsl equivalent
	 * @param int $hex
	 * @return array (h,s,l)
	 */
	public static function hex2hsl($hex) {
		return ColorUtils::rgb2hsl(ColorUtils::hex2rgb($hex));
	}

	/**
	 * Converts a hex color (0x######) to it's rgb equivalent
	 * @param int $hex
	 * @return array (r,g,b)
	 */
	public static function hex2rgb($hex) {
// 		$hex *= 1;
		$rgb = array(
				'r' => $hex >> 16,
				'g' => $hex >> 8 & 0xff,
				'b' => $hex & 0xff
		);
		return $rgb;
	}

	public static function rgb2hsl($rgb) {
		$r = $rgb['r'];
		$g = $rgb['g'];
		$b = $rgb['b'];

		$r /= 255;
		$g /= 255;
		$b /= 255;

	    $max = max( $r, $g, $b );
		$min = min( $r, $g, $b );

		$h = $s = 0;
		$l = ( $max + $min ) / 2;
		$d = $max - $min;

    	if( $d !== 0 ){
        	$s = $d / ( 1 - abs( 2 * $l - 1 ) );

			switch( $max ){
	            case $r:
	            	$h = 60 * fmod( ( ( $g - $b ) / $d ), 6 );
                        if ($b > $g) {
	                    $h += 360;
	                }
	                break;

	            case $g:
	            	$h = 60 * ( ( $b - $r ) / $d + 2 );
	            	break;

	            case $b:
	            	$h = 60 * ( ( $r - $g ) / $d + 4 );
	            	break;
	        }
		}

		$s*=100;
		$l*=100;
		return array( 'h' => round( $h, 2 ), 's' => round($s*10)/10, 'l' => round($l * 10) / 10);
	}


	/**
	 * Converts a rgb color (array(r,g,b)) to it's hsl equivalent
	 * @param array $rgb
	 * @return array (h,s,l)
	 */
	public static function rgb2hsl_OLD($rgb) {
		$r = $rgb['r'] / 255;
		$g = $rgb['g'] / 255;
		$b = $rgb['b'] / 255;
		$cMax = max($r,$g,$b);
		$cMin = min($r,$g,$b);
		$d = $cMax - $cMin;

		if($d == 0) {
			$h = 0;
		} else if($cMax == $r) {
			$h = fmod(($g - $b) / $d, 6);
		} else if($cMax == $g) {
			$h = ($b - $r) / $d + 2;

		} else {
			$h = ($r - $g) / $d + 4;
		}
		$h *= 60;
		if( $h < 0 )
			$h += 360;

		$l = ($cMax + $cMin) / 2;
		if( $d == 0 ) {
			$s = 0;
		} else {
			$s = $d/(1 - abs(2*$l-1));
		}
		$s*=100;
		$l*=100;
		return array('h' => round($h), 's' => round($s*10)/10, 'l' => round($l * 10) / 10);


	}

	public static function hsl2rgb($hsl) {
		$l = $hsl['l'] / 100;
		$s = $hsl['s'] / 100;
		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( ( $hsl['h'] / 60 ), 2 ) - 1 ) );
		$m = $l - ( $c / 2 );

		if ( $hsl['h'] < 60 ) {
			$r = $c;
			$g = $x;
			$b = 0;
		} else if ( $hsl['h'] < 120 ) {
			$r = $x;
			$g = $c;
			$b = 0;
		} else if ( $hsl['h'] < 180 ) {
			$r = 0;
			$g = $c;
			$b = $x;
		} else if ( $hsl['h'] < 240 ) {
			$r = 0;
			$g = $x;
			$b = $c;
		} else if ( $hsl['h'] < 300 ) {
			$r = $x;
			$g = 0;
			$b = $c;
		} else {
			$r = $c;
			$g = 0;
			$b = $x;
		}

		$r = ( $r + $m ) * 255;
		$g = ( $g + $m ) * 255;
		$b = ( $b + $m  ) * 255;

		return array( 'r' => round($r), 'g' => round($g), 'b' => round($b) );

	}

	/**
	 * Converts an rgb object to a hex STRING
	 *
	 * @param rgb
	 * @returns string
	 */
	public static function rgb2hex($rgb) {
		$hex = $rgb['r'] << 16 | $rgb['g'] << 8 | $rgb['b'];
		$hex = '000000' . dechex($hex);
		$hex = '#' . substr($hex, - 6);
		return $hex;
	}
	/**
	 * Converts a hsl color to it's hex equivalent
	 * @param array $hsl
	 * @return string The hex string
	 */
	public static function hsl2hex($hsl) {
		$rgb = ColorUtils::hsl2rgb($hsl);
		return ColorUtils::rgb2hex($rgb);
	}

	/**
	 * Converts an hsv color (array(h,s,v) to it's hex equivalent
	 * @param array $hsv
	 * @returns string The hex color
	 */
	public static function hsv2hex($hsv) {
		$rgb = ColorUtils::hsv2rgb($hsv);
		return ColorUtils::rgb2hex($rgb);
	}

	/**
	 * Converts a rgb color to a hsv color
	 *
	 * @param array $rgb (r,g,b)
	 * @returns array (h,s,v)
	 */
	public static function rgb2hsv($rgb) {
		$r = $rgb['r'] / 255;
		$g = $rgb['g'] / 255;
		$b = $rgb['b'] / 255; // Scale to unity.

		$minVal = min($r, $g, $b);
		$maxVal = max($r, $g, $b);
		$delta = $maxVal - $minVal;
		$hsv = array();
		$hsv['v'] = $maxVal;

		if ($delta == 0) {
			$hsv['h'] = 0;
			$hsv['s'] = 0;
		} else {
			$hsv['s'] = $delta / $maxVal;
			$del_R = ((($maxVal - $r) / 6) + ($delta / 2)) / $delta;
			$del_G = ((($maxVal - $g) / 6) + ($delta / 2)) / $delta;
			$del_B = ((($maxVal - $b) / 6) + ($delta / 2)) / $delta;

			if ($r == $maxVal) {
				$hsv['h'] = $del_B - $del_G;
			} else if ($g == $maxVal) {
				$hsv['h'] = (1 / 3) + $del_R - $del_B;
			} else if ($b == $maxVal) {
				$hsv['h'] = (2 / 3) + $del_G - $del_R;
			}

			if ($hsv['h'] < 0) {
				$hsv['h'] += 1;
			}
			if ($hsv['h'] > 1) {
				$hsv['h'] -= 1;
			}
		}
		return $hsv;
	}
}