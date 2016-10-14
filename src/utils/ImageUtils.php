<?php

namespace fur\bright\utils;

class ImageUtils {
	
	public static function ImageColorize(&$image, $hex) {
		$rgb = ColorUtils::hex2rgb($hex);
		if(function_exists('imagefilter')) {
			imagefilter($image, IMG_FILTER_COLORIZE, $rgb['r'], $rgb['g'], $rgb['b']);
		}
	}
	
	public static function ImageHue(&$image, $hue, $saturation) {
		
		$width = imagesx($image);
		$height = imagesy($image);
	
		for($x = 0; $x < $width; $x++) {
			for($y = 0; $y < $height; $y++) {
				$rgb = imagecolorat($image, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				$alpha = ($rgb & 0x7F000000) >> 24;
				$hsl = ColorUtils::rgb2hsl(array('r' => $r, 'g' => $g, 'b' => $b));
				$h = $hsl['h'] / 360;
				$s = $hsl['s'] / 100;
				$h += $hue / 360;
				$s += $saturation / 100;
				if($h > 1) $h--;
				if($s > 1) $s--;
				
				$hsl['h'] = $h * 360;
				$hsl['s'] = $s * 100;
				
				$rgb = ColorUtils::hsl2rgb($hsl);
				
				imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $rgb['r'], $rgb['g'], $rgb['b'], $alpha));
			}
		}
	}

	/**
	 * Checks if the image is a logo, by checking if the outer pixels all have the same color
	 * This method returns true when:
	 * - The image is a png or gif
	 * - The colors of the outermost pixels are the same color
	 * @param string $path
	 * @return bool
	 */
	public static function IsLogo($path) {
		if(!file_exists($path))
			return false;
		
		try {
			$info = getimagesize($path);
			$img = null;
			$transparent = false;
			switch($info[2]) {
				case IMAGETYPE_GIF:
				case IMAGETYPE_PNG:
					$img = $info[2] == IMAGETYPE_GIF ? imagecreatefromgif($path) : imagecreatefrompng($path);
					$rgba = imagecolorat($img,0,0);
					$alpha = ($rgba & 0x7F000000) >> 24;
					$transparent = $alpha == 127;
					break;
				case IMAGETYPE_JPEG:
				case IMAGETYPE_JPEG2000:
					$img = imagecreatefromjpeg($path);
					break;
				default:
					// Unknown image type
					throw new \Exception("invalid image");
			}
			$sameColor = 0;
			$totalPixels = ($info[0] * 2) + ($info[1] * 2) - 4;
			if($transparent) {
				// Image has transparency, logo for sure
				return true;
			}else {
			
				$color = ColorUtils::dec2hex(imagecolorat($img, 0, 0));
				// Check top and bottom line for color differences,
				for($x = 0; $x < $info[0]; $x++) {
					if(ColorUtils::compareColors($color, ColorUtils::dec2hex(imagecolorat($img, $x, 0)), 10, $x, 0)) {
						$sameColor++;
					}
					if(ColorUtils::compareColors($color, ColorUtils::dec2hex(imagecolorat($img, $x, $info[1]-1)), 10, $x, $info[1]-1)) {
						$sameColor++;
					}
				}
				// Check left and right line for color differences,
				for($y = 0; $y < $info[1]; $y++) {
					if(ColorUtils::compareColors($color, ColorUtils::dec2hex(imagecolorat($img, 0, $y)), 10, 0, $y)) {
						$sameColor++;
					}
					if(ColorUtils::compareColors($color, ColorUtils::dec2hex(imagecolorat($img, $info[0]-1, $y)), 10, $info[0]-1, $y)) {
						$sameColor++;
					}
				}
			}

			imagedestroy($img);
			// 90% of the outer pixels are the same color
			return ($sameColor / $totalPixels > .9);
		} catch(\Exception $e) {

		}
		return false;
	}
}