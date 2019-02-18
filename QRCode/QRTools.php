<?php
/*
 * PHP QR Code
 *
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
 *
 * PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010 Dominik Dzienia <deltalab at poczta dot fm>
 *
 * Code modifications by Momchil Bozhinov <momchil at bojinov dot info>
 * Last update - 02.2019
 *
 */

namespace QRCode;

class QRTools {

	private $anTable = [
			-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
			-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
			36, -1, -1, -1, 37, 38, -1, -1, -1, -1, 39, 40, -1, 41, 42, 43,
			 0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 44, -1, -1, -1, -1, -1,
			-1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,
			25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, -1, -1, -1, -1, -1,
			-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
			-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1
		];
		
	private $lengthTableBits = [
		[10, 12, 14],
		[9, 11, 13],
		[8, 16, 16],
		[8, 10, 12]
	];
	
	public $capacity = [
		[0, 0, 0, [0, 0, 0, 0]],
		[21, 26, 0, [7, 10, 13, 17]], // 1
		[25, 44, 7, [10, 16, 22, 28]],
		[29, 70, 7, [15, 26, 36, 44]],
		[33, 100, 7, [20, 36, 52, 64]],
		[37, 134, 7, [26, 48, 72, 88]], // 5
		[41, 172, 7, [36, 64, 96, 112]],
		[45, 196, 0, [40, 72, 108, 130]],
		[49, 242, 0, [48, 88, 132, 156]],
		[53, 292, 0, [60, 110, 160, 192]],
		[57, 346, 0, [72, 130, 192, 224]], //10
		[61, 404, 0, [80, 150, 224, 264]],
		[65, 466, 0, [96, 176, 260, 308]],
		[69, 532, 0, [104, 198, 288, 352]],
		[73, 581, 3, [120, 216, 320, 384]],
		[77, 655, 3, [132, 240, 360, 432]], //15
		[81, 733, 3, [144, 280, 408, 480]],
		[85, 815, 3, [168, 308, 448, 532]],
		[89, 901, 3, [180, 338, 504, 588]],
		[93, 991, 3, [196, 364, 546, 650]],
		[97, 1085, 3, [224, 416, 600, 700]], //20
		[101, 1156, 4, [224, 442, 644, 750]],
		[105, 1258, 4, [252, 476, 690, 816]],
		[109, 1364, 4, [270, 504, 750, 900]],
		[113, 1474, 4, [300, 560, 810, 960]],
		[117, 1588, 4, [312, 588, 870, 1050]], //25
		[121, 1706, 4, [336, 644, 952, 1110]],
		[125, 1828, 4, [360, 700, 1020, 1200]],
		[129, 1921, 3, [390, 728, 1050, 1260]],
		[133, 2051, 3, [420, 784, 1140, 1350]],
		[137, 2185, 3, [450, 812, 1200, 1440]], //30
		[141, 2323, 3, [480, 868, 1290, 1530]],
		[145, 2465, 3, [510, 924, 1350, 1620]],
		[149, 2611, 3, [540, 980, 1440, 1710]],
		[153, 2761, 3, [570, 1036, 1530, 1800]],
		[157, 2876, 0, [570, 1064, 1590, 1890]], //35
		[161, 3034, 0, [600, 1120, 1680, 1980]],
		[165, 3196, 0, [630, 1204, 1770, 2100]],
		[169, 3362, 0, [660, 1260, 1860, 2220]],
		[173, 3532, 0, [720, 1316, 1950, 2310]],
		[177, 3706, 0, [750, 1372, 2040, 2430]] //40
	];
	
	public function getWidth($version)
	{
		return $this->capacity[$version][QR_CAP_WIDTH];
	}
	
	public function getDataLength($version, $level)
	{
		return $this->capacity[$version][QR_CAP_WORDS] - $this->capacity[$version][QR_CAP_EC][$level];
	}
		
	public function estimateBitsModeNum($size)
	{
		$w = floor($size / 3);
		$bits = $w * 10;
		
		switch($size - $w * 3) {
			case 1:
				$bits += 4;
				break;
			case 2:
				$bits += 7;
				break;
			default:
				break;
		}

		return $bits;
	}
	
	public function estimateBitsModeAn($size)
	{
		$w = floor($size / 2);
		$bits = $w * 11;
		
		if($size & 1) {
			$bits += 6;
		}

		return $bits;
	}

	public function estimateBitsMode8($size)
	{
		return $size * 8;
	}

	public function estimateBitsModeKanji($size)
	{
		return floor(($size / 2) * 13);
	}

	public function lookAnTable($c)
	{
		return (($c > 127) ? -1 : $this->anTable[$c]);
	}

	private function checkModeAn($size, $data)
	{
		for($i=0; $i<$size; $i++) {
			if ($this->lookAnTable(ord($data[$i])) == -1) {
				return false;
			}
		}

		return true;
	}

	private function checkModeKanji($size, $data)
	{
		if($size & 1){
			return false;
		}
		
		for($i=0; $i<$size; $i+=2) {
			$val = (ord($data[$i]) << 8) | ord($data[$i+1]);
			if($val < 0x8140 || ($val > 0x9ffc && $val < 0xe040) || $val > 0xebbf) {
				return false;
			}
		}

		return true;
	}

	private function checkModeNum($size, $data)
	{
		for($i=0; $i<$size; $i++) {
			# ord('0') = 48 && ord('9') = 57
			$num = ord($data[$i]);
			if($num < 48 || $num > 57){
				return false;
			}
		}

		return true;
	}

	/*
	 * Validation
	*/
	public function Check($mode, $size, $data)
	{
		if($size <= 0) {
			return false;
		}

		switch($mode) {
			case QR_MODE_NUM:
				return $this->checkModeNum($size, $data);
				break;
			case QR_MODE_AN:
				return $this->checkModeAn($size, $data);
				break;
			case QR_MODE_KANJI:
				return $this->checkModeKanji($size, $data);
				break;
			case QR_MODE_8:
			case QR_MODE_STRUCTURE:
				return true;
				break;
			default:
				break;
		}

		return false;
	}
	
	public function lengthIndicator($mode, $version)
	{
		if ($mode == QR_MODE_STRUCTURE){
			return 0;
		}

		if ($version <= 9) {
			$l = 0;
		} else if ($version <= 26) {
			$l = 1;
		} else {
			$l = 2;
		}

		return $this->lengthTableBits[$mode][$l];
	}

	public function maximumWords($mode, $version)
	{
		if($mode == QR_MODE_STRUCTURE){
			return 3;
		}

		if($version <= 9) {
			$l = 0;
		} elseif($version <= 26) {
			$l = 1;
		} else {
			$l = 2;
		}

		$bits = $this->lengthTableBits[$mode][$l];
		$words = (1 << $bits) - 1;
		
		if($mode == QR_MODE_KANJI) {
			$words *= 2; // the number of bytes is required
		}

		return $words;
	}

}

?>