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
 * Last update - 03.2019
 *
 */

namespace QRCode;

class QRInput {

	private $dataStr;
	private $dataStrLen;
	private $hint;
	private $pos;
	private $level;
	private $bstream = [];
	private $streams = [];
	private $maxLenlengths = [];
	private $lengthTableBits = [
		[10, 12, 14],
		[ 9, 11, 13],
		[ 8, 16, 16],
		[ 8, 10, 12]
	];

	private $anTable = [
		-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
		-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
		36, -1, -1, -1, 37, 38, -1, -1, -1, -1, 39, 40, -1, 41, 42, 43,
		 0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 44, -1, -1, -1, -1, -1,
		-1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,
		25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35
	];
	
	private $capacity = [
		[0, [0, 0, 0, 0]],
		[26, [7, 10, 13, 17]], // 1
		[44, [10, 16, 22, 28]],
		[70, [15, 26, 36, 44]],
		[100, [20, 36, 52, 64]],
		[134, [26, 48, 72, 88]], // 5
		[172, [36, 64, 96, 112]],
		[196, [40, 72, 108, 130]],
		[242, [48, 88, 132, 156]],
		[292, [60, 110, 160, 192]],
		[346, [72, 130, 192, 224]], //10
		[404, [80, 150, 224, 264]],
		[466, [96, 176, 260, 308]],
		[532, [104, 198, 288, 352]],
		[581, [120, 216, 320, 384]],
		[655, [132, 240, 360, 432]], //15
		[733, [144, 280, 408, 480]],
		[815, [168, 308, 448, 532]],
		[901, [180, 338, 504, 588]],
		[991, [196, 364, 546, 650]],
		[1085, [224, 416, 600, 700]], //20
		[1156, [224, 442, 644, 750]],
		[1258, [252, 476, 690, 816]],
		[1364, [270, 504, 750, 900]],
		[1474, [300, 560, 810, 960]],
		[1588, [312, 588, 870, 1050]], //25
		[1706, [336, 644, 952, 1110]],
		[1828, [360, 700, 1020, 1200]],
		[1921, [390, 728, 1050, 1260]],
		[2051, [420, 784, 1140, 1350]],
		[2185, [450, 812, 1200, 1440]], //30
		[2323, [480, 868, 1290, 1530]],
		[2465, [510, 924, 1350, 1620]],
		[2611, [540, 980, 1440, 1710]],
		[2761, [570, 1036, 1530, 1800]],
		[2876, [570, 1064, 1590, 1890]], //35
		[3034, [600, 1120, 1680, 1980]],
		[3196, [630, 1204, 1770, 2100]],
		[3362, [660, 1260, 1860, 2220]],
		[3532, [720, 1316, 1950, 2310]],
		[3706, [750, 1372, 2040, 2430]] //40
	];

	function __construct(int $level)
	{
		$this->level = $level;
	}
	
	private function getDataLength($version)
	{
		$ecc = $this->capacity[$version][1][$this->level];
		return [$this->capacity[$version][0] - $ecc, $ecc];
	}

	private function lookAnTable($c)
	{
		return (($c > 90) ? -1 : $this->anTable[$c]);
	}

	private function lengthIndicator($mode, $version)
	{
		if ($version <= 9) {
			$l = 0;
		} else if ($version <= 26) {
			$l = 1;
		} else {
			$l = 2;
		}

		return $this->lengthTableBits[$mode][$l];
	}

	private function encodeModeNum($size, $data)
	{
		$words = (int)($size / 3);

		$this->bstream[] = [4, 1];
		$this->bstream[] = [$this->maxLenlengths[QR_MODE_NUM], $size];

		for($i=0; $i<$words; $i++) {
			$val  = ($data[$i*3] - 48) * 100;
			$val += ($data[$i*3+1] - 48) * 10;
			$val += ($data[$i*3+2] - 48);
			$this->bstream[] = [10, $val];
		}

		if($size - $words * 3 == 1) {
			$val = $data[$words*3] - 48;
			$this->bstream[] = [4, $val];
		} elseif($size - $words * 3 == 2) {
			$val  = ($data[$words*3] - 48) * 10;
			$val += ($data[$words*3+1] - 48);
			$this->bstream[] = [7, $val];
		}
	}

	private function encodeModeAn($size, $data)
	{
		$words = (int)($size / 2);

		$this->bstream[] = [4, 2];
		$this->bstream[] = [$this->maxLenlengths[QR_MODE_AN], $size];

		for($i=0; $i<$words; $i++) {
			$val = ($this->lookAnTable($data[$i*2]) * 45) + $this->lookAnTable($data[$i*2+1]);
			$this->bstream[] = [11, $val];
		}

		if($size & 1) {
			$val = $this->lookAnTable($data[$words * 2]);
			$this->bstream[] = [6, $val];
		}
	}

	private function encodeMode8($size, $data)
	{
		$this->bstream[] = [4, 4];
		$this->bstream[] = [$this->maxLenlengths[QR_MODE_8], $size];

		for($i=0; $i<$size; $i++) {
			$this->bstream[] = [8, $data[$i]];
		}
	}

	private function encodeModeKanji($size, $data)
	{
		$this->bstream[] = [4, 8];
		$this->bstream[] = [$this->maxLenlengths[QR_MODE_KANJI], (int)($size / 2)];

		for($i=0; $i<$size; $i+=2) {
			$val = ($data[$i] << 8) | $data[$i+1];
			if($val <= 40956) {
				$val -= 33088;
			} else {
				$val -= 49472;
			}

			$val = ($val & 255) + (($val >> 8) * 192);

			$this->bstream[] = [13, $val];
		}
	}
 
	private function estimateVersion($version)
	{
		$bits = 0;
		foreach($this->streams as $stream) {
			list($mode, $size, ) = $stream;
			switch($mode) {
				case QR_MODE_NUM:
					$bits += (int)($size / 3) * 10;
					switch($size % 3) {
						case 1:
							$bits += 4;
							break;
						case 2:
							$bits += 7;
							break;
					}
					break;
				case QR_MODE_AN:
					$bits += (int)($size / 2) * 11;
					if($size & 1) {
						$bits += 6;
					}
					break;
				case QR_MODE_8:
					$bits += ($size * 8);
					break;
				case QR_MODE_KANJI:
					$bits += ((int)(($size / 2) * 13));
					break;
			}

			$l = $this->lengthIndicator($mode, $version);
            $m = 1 << $l;
            $num = (int)(($size + $m - 1) / $m);
            $bits += $num * (4 + $l);
		}

		return $this->getMinimumVersion($bits);
	}

	private function encodeStream()
	{
		$version = 1;
        do {
			$prev = $version;
			$package = $this->estimateVersion($version);
			$version = $package[0];
		} while ($version > $prev);

		$this->maxLenlengths = [
			QR_MODE_NUM => $this->lengthIndicator(QR_MODE_NUM, $version),
			QR_MODE_AN => $this->lengthIndicator(QR_MODE_AN, $version),
			QR_MODE_8 => $this->lengthIndicator(QR_MODE_8, $version),
			QR_MODE_KANJI => $this->lengthIndicator(QR_MODE_KANJI, $version)
		];

		foreach($this->streams as $pos => $stream) {

			list($mode, $size, $data) = $stream;

			$maxWords = (1 << $this->maxLenlengths[$mode]) - 1;
			if ($mode == QR_MODE_KANJI){
				$maxWords *= 2;
			}

			if($size > $maxWords) {
				array_splice($this->streams, $pos, 1, array_chunk($data, $maxWords));
			}
		}

		foreach($this->streams as $stream) {

			list($mode, $size, $data) = $stream;

			switch($mode) {
				case QR_MODE_NUM:
					$this->encodeModeNum($size, $data);
					break;
				case QR_MODE_AN:
					$this->encodeModeAn($size, $data);
					break;
				case QR_MODE_8:
					$this->encodeMode8($size, $data);
					break;
				case QR_MODE_KANJI:
					$this->encodeModeKanji($size, $data);
					break;
			}
		}

		return $package;
	}

	private function getMinimumVersion($bits)
	{
		$size = (int)(($bits + 7) / 8);
		for($i=1; $i<= 40; $i++) { # QR_SPEC_VERSION_MAX = 40
			list($dataLength, $ecc) = $this->getDataLength($i);
			if($dataLength >= $size){
				$width = $i * 4 + 17;
				return [$i, $dataLength, $ecc, $width, $this->level, $bits];
			}
		}
	}

	private function get_actual_bstream_size() # UNUSED
	{
		$size = 0;
		foreach($this->bstream as $d){
			$size += $d[0];
		}
		return $size;
	}

	private function toByte()
	{
		$dataStr = "";

		foreach($this->bstream as $d){

			list($bits, $num) = $d;

			$bin = decbin($num);
			$diff = $bits - strlen($bin);

			if ($diff != 0){
				$bin = str_repeat("0", $diff).$bin;
			}

			$dataStr .= $bin;
		}

		$data = [];

		foreach(str_split($dataStr, 8) as $val){
			$data[] = bindec($val);
		}

		return $data;
	}

	private function getPackage()
	{
		$package = $this->encodeStream();
		$bits = array_pop($package);
		$maxwords = $package[1];
		$maxbits = $maxwords * 8;

		if ($maxbits - $bits < 5) {
			$this->bstream[] = [$maxbits - $bits, 0];
			$package[] = $this->toByte();
			return $package;
		}

		$bits += 4;
		$words = floor(($bits + 7) / 8);

		$this->bstream[] = [$words * 8 - $bits + 4, 0];

		$padlen = $maxwords - $words;

		if($padlen > 0) {
			for($i=0; $i<$padlen; $i+=2) {
				$this->bstream[] = [8, 236];
				$this->bstream[] = [8, 17];
			}
		}
		$package[] = $this->toByte();

		return $package;
	}

	private function is_digit()
	{
		if ($this->pos >= $this->dataStrLen){
			return false;
		}
		return ($this->dataStr[$this->pos] >= 48 && $this->dataStr[$this->pos] <= 57);
	}

	private function is_alnum()
	{
		if ($this->pos >= $this->dataStrLen){
			return false;
		}
		return ($this->lookAnTable($this->dataStr[$this->pos]) >= 0);
	}

	private function is_kanji()
	{
		if ($this->pos+1 < $this->dataStrLen) {
			$word = ($this->dataStr[$this->pos]) << 8 | $this->dataStr[$this->pos+1];
			if(($word >= 33088 && $word <= 40956) || ($word >= 57408 && $word <= 60351)) {
				return true;
			}
		}
		return false;
	}

	private function identifyMode()
	{
		switch (true){
			case $this->is_digit():
				$mode = QR_MODE_NUM;
				break;
			case $this->is_alnum():
				$mode = QR_MODE_AN;
				break;
			case ($this->hint == QR_MODE_KANJI):
				# Kanji is not auto detected unless hinted but otherwise it breaks bulgarian chars and possibly others
				$mode = ($this->is_kanji()) ? QR_MODE_KANJI : QR_MODE_8;
				break;
			default:
				$mode = QR_MODE_8;
		}

		return $mode;
	}

	private function eatNum()
	{
		# the first pos was already identified
		$this->pos++;

		while($this->is_digit()) {
			$this->pos++;
		}
	}

	private function eatAn()
	{
		$this->pos++;

		while($this->is_alnum()) {
			$this->pos++;
		}
	}

	private function eatKanji()
	{
		$this->pos += 2;

		while($this->is_kanji()) {
			$this->pos += 2;
		}
	}

	private function eat8()
	{
		$this->pos++;

		while($this->pos < $this->dataStrLen) {

			switch($this->identifyMode()){
				case QR_MODE_KANJI:
					break 2;
				case QR_MODE_NUM:
					$old_pos = $this->pos;
					$this->eatNum();
					if(($this->pos - $old_pos) > 3) {
						$this->pos = $old_pos;
						break 2;
					}
					break;
				case QR_MODE_AN:
					$old_pos = $this->pos;
					$this->eatAn();
					if(($this->pos - $old_pos) > 5) {
						$this->pos = $old_pos;
						break 2;
					}
					break;
				default:
					$this->pos++;
			}
		}
	}

	public function encodeString($text, $hint)
	{
		$this->dataStr = [];
		foreach(str_split($text)as $val){
			$this->dataStr[] = ord($val);
		}

		$this->dataStrLen = count($this->dataStr);

		if (($hint != QR_MODE_KANJI) && ($hint != -1)) {

			$this->streams[] = [$hint, $this->dataStrLen, $this->dataStr];

		} else {

			$this->hint = $hint;
			$this->pos = 0;
			$prev = 0;

			while ($this->dataStrLen > $this->pos)
			{
				$prev = $this->pos;
				$mode = $this->identifyMode();

				switch ($mode) {
					case QR_MODE_NUM:
						$this->eatNum();
						break;
					case QR_MODE_AN:
						$this->eatAn();
						break;
					case QR_MODE_KANJI:
						$this->eatKanji();
						break;
					default:
						$mode = QR_MODE_8;
						$this->eat8();
				}

				$size = $this->pos - $prev;
				$this->streams[] = [$mode, $size, array_slice($this->dataStr, $prev, $size)];
			}
		}

		$package = $this->getPackage();
		return (new QRmask($package))->get();
	}
}

?>