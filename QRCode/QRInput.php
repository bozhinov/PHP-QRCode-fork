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

class QRInput {

	private $dataStr;
	private $dataStrLen;
	private $hint;
	private $level;
	private $version;
	private $bstream = [];
	private $streams = [];
	private $capacity;
	private $maxTableBits = [1023,511,255,255];
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
		 -1,  -1,  -1,  -1,  -1,  -1,  -1,  -1,  -1,  9, 44, -1, -1, -1, -1, -1,
		-1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,
		25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35
	];

	function __construct(int $level)
	{
		$this->level = $level;
		$this->capacity = new QRCap();
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
		$this->bstream[] = [$this->lengthIndicator(QR_MODE_NUM, $this->version), $size];

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
		$this->bstream[] = [$this->lengthIndicator(QR_MODE_AN, $this->version), $size];

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
		$this->bstream[] = [$this->lengthIndicator(QR_MODE_8, $this->version), $size];

		for($i=0; $i<$size; $i++) {
			$this->bstream[] = [8, $data[$i]];
		}
	}

	private function encodeModeKanji($size, $data)
	{
		$this->bstream[] = [4, 8];
		$this->bstream[] = [$this->lengthIndicator(QR_MODE_KANJI, $this->version), (int)($size / 2)];

		for($i=0; $i<$size; $i+=2) {
			$val = ($data[$i] << 8) | $data[$i+1];
			if($val <= 40956) {
				$val -= 33088;
			} else {
				$val -= 49472;
			}

			$h = ($val >> 8) * 192;
			$val = ($val & 255) + $h;

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

		return [$this->getMinimumVersion($bits), $bits];
	}
	
	private function encodeStream()
	{
		$version = 0;
        do {
			$prev = $version;
			list($version, $bits) = $this->estimateVersion($version);
		} while ($version > $prev);
		
		$this->version = $version;
		
		foreach($this->streams as $stream) {

			list($mode, $size, $data) = $stream;

			switch($mode) {
				case QR_MODE_NUM:
					$this->encodeModeNum($size, $data, $version);
					break;
				case QR_MODE_AN:
					$this->encodeModeAn($size, $data, $version);
					break;
				case QR_MODE_8:
					$this->encodeMode8($size, $data, $version);
					break;
				case QR_MODE_KANJI:
					$this->encodeModeKanji($size, $data, $version);
					break;
			}
		}
		
		return $bits;
	}

	private function addStream($mode, array $data)
	{
		$size = count($data);
		$maxWords = $this->maxTableBits[$mode];

		if($mode == QR_MODE_KANJI) {
			$maxWords *= 2; // the number of bytes is required
		}

		if($size > $maxWords) {
			foreach(array_chunk($data, $maxWords) as $chunk) {
				$this->addStream($mode, $chunk);
			}
		} else {
			$this->streams[] = [$mode, $size, $data];
		}
	}
	
	private function getMinimumVersion($bits)
	{
		$size = (int)(($bits + 7) / 8);
		for($i=1; $i<= 40; $i++) { # QR_SPEC_VERSION_MAX = 40
			$dataLength = $this->capacity->getDataLength($i, $this->level);
			if($dataLength >= $size){
				return $i;
			}
		}
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
		$bits = $this->encodeStream();

		$dataLength = $this->capacity->getDataLength($this->version, $this->level);			
		$package = [$this->version, $dataLength, $this->capacity->getWidth($this->version), $this->level];

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

	private function is_digit($pos)
	{
		if ($pos >= $this->dataStrLen){
			return false;
		}
		return ($this->dataStr[$pos] >= 48 && $this->dataStr[$pos] <= 57);
	}

	private function is_alnum($pos)
	{
		if ($pos >= $this->dataStrLen){
			return false;
		}

		return ($this->lookAnTable($this->dataStr[$pos]) >= 0);
	}
	
	private function is_kanji($pos)
	{
		if ($pos+1 < $this->dataStrLen) {
			$word = ($this->dataStr[$pos]) << 8 | $this->dataStr[$pos+1];
			if(($word >= 33088 && $word <= 40956) || ($word >= 57408 && $word <= 60351)) {
				return true;
			}
		}
		return false;
	}

	private function identifyMode($pos)
	{
		switch (true){
			case $this->is_digit($pos):
				$mode = QR_MODE_NUM;
				break;
			case $this->is_alnum($pos):
				$mode = QR_MODE_AN;
				break;
			case ($this->hint == QR_MODE_KANJI):
				# Kanji is not auto detected unless hinted but otherwise it breaks bulgarian chars and possibly others
				$mode = ($this->is_kanji($pos)) ? QR_MODE_KANJI : QR_MODE_8;
				break;
			default:
				$mode = QR_MODE_8;
		}

		return $mode;
	}

	private function eatNum($p = 0)
	{
		# the first pos was already identified
		$p++;
		
		while($this->is_digit($p)) {
			$p++;
		}
		return $p;
	}

	private function eatAn($p = 0)
	{
		$p++;
		
		while($this->is_alnum($p)) {
			$p++;
		}
		return $p;
	}

	private function eatKanji($p = 0)
	{
		$p += 2;
		
		while($this->is_kanji($p)) {
			$p += 2;
		}
		return $p;
	}

	private function eat8($p = 0)
	{
		$p++;

		while($p < $this->dataStrLen) {

			switch($this->identifyMode($p)){
				case QR_MODE_KANJI:
					break 2;
				case QR_MODE_NUM:
					$q = $this->eatNum($p);
					if(($q - $p) > 3) {
						break 2;
					} else {
						$p = $q;
					}
					break;
				case QR_MODE_AN:
					$q = $this->eatAn($p);
					if(($q - $p) > 5) {
						break 2;
					} else {
						$p = $q;
					}
					break;
				default:
					$p++;
			}
		}

		return $p;
	}

	public function encodeString($dataStr, $hint)
	{
		if (($hint != QR_MODE_KANJI) && ($hint != -1)) {

			$this->addStream($hint, $dataStr);

		} else {

			$this->dataStr = $dataStr;
			$this->dataStrLen = count($this->dataStr);
			$this->hint = $hint;

			while ($this->dataStrLen > 0)
			{
				$mod = $this->identifyMode(0);

				switch ($mod) {
					case QR_MODE_NUM:
						$length = $this->eatNum();
						break;
					case QR_MODE_AN:
						$length = $this->eatAn();
						break;
					case QR_MODE_KANJI:
						$length = $this->eatKanji();
						break;
					default:
						$mod = QR_MODE_8;
						$length = $this->eat8();
				}

				$this->addStream($mod, array_slice($this->dataStr, 0, $length));
				$this->dataStrLen -= $length;
				$this->dataStr = array_slice($this->dataStr, $length);
			}
		}

		$package = $this->getPackage();
		return (new QRmask($package))->get();
	}
}

?>