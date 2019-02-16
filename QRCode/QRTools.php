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
			if((ord($data[$i]) < ord('0')) || (ord($data[$i]) > ord('9'))){
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
				return true;
				break;
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