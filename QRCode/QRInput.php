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
	private $tools;
	private $bstream = [];
	private $lengthTableBits = [
		[10, 12, 14],
		[9, 11, 13],
		[8, 16, 16],
		[8, 10, 12]
	];

	function __construct(int $level)
	{
		$this->level = $level;
		$this->tools = new QRTools();
	}

	private function encodeModeNum($size, $data)
	{
		$words = (int)($size / 3);

		$this->bstream[] = [4, 1];
		$this->bstream[] = [$this->lengthTableBits[QR_MODE_NUM][0], $size];

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
		$this->bstream[] = [$this->lengthTableBits[QR_MODE_AN][0], $size];

		for($i=0; $i<$words; $i++) {
			$val = ($this->tools->lookAnTable($data[$i*2]) * 45) + $this->tools->lookAnTable($data[$i*2+1]);
			$this->bstream[] = [11, $val];
		}

		if($size & 1) {
			$val = $this->tools->lookAnTable($data[$words * 2]);
			$this->bstream[] = [6, $val];
		}
	}

	private function encodeMode8($size, $data)
	{
		$this->bstream[] = [4, 4];
		$this->bstream[] = [$this->lengthTableBits[QR_MODE_8][0], $size];

		for($i=0; $i<$size; $i++) {
			$this->bstream[] = [8, $data[$i]];
		}
	}

	private function encodeModeKanji($size, $data)
	{
		$this->bstream[] = [4, 8];
		$this->bstream[] = [$this->lengthTableBits[QR_MODE_KANJI][0], (int)($size / 2)];

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

	private function addStream($mode, array $data)
	{
		$size = count($data);
		$bits = $this->lengthTableBits[$mode][0];
		$maxWords = (1 << $bits) - 1;

		if($mode == QR_MODE_KANJI) {
			$maxWords *= 2; // the number of bytes is required
		}

		if ($size > $maxWords * 2){
			throw QRException::Std('string too long. Max length - '.strval($maxWords * 2));
		}

		if($size > $maxWords) {
			
			$this->add(array_slice($data, 0, $maxWords), $mode);
			$this->add(array_slice($data, $maxWords), $mode);

		} else {

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
	}

	private function getMinimumVersion($bits)
	{
		$size = (int)(($bits + 7) / 8);
		for($i=1; $i<= QR_SPEC_VERSION_MAX; $i++) {
			if($this->tools->getDataLength($i, $this->level) >= $size){
				return $i;
			}
		}
	}

	private function get_bstream_size()
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

		$data = str_split($dataStr, 8);

		array_walk($data, function(&$val) {
			$val = bindec($val);
		});

		return $data;
	}

	private function getBytes()
	{
		$bits = $this->get_bstream_size();
		$version = $this->getMinimumVersion($bits);

		$maxwords = $this->tools->getDataLength($version, $this->level);
		$maxbits = $maxwords * 8;

		if ($maxbits - $bits < 5) {
			$this->bstream[] = [$maxbits - $bits, 0];
			return [$this->toByte(), $version];
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

		return [$this->toByte(), $version];
	}

	private function isdigitat($pos)
	{
		if ($pos >= $this->dataStrLen){
			return false;
		}
		return ($this->dataStr[$pos] >= 48 && $this->dataStr[$pos] <= 57);
	}

	private function isalnumat($pos)
	{
		if ($pos >= $this->dataStrLen){
			return false;
		}
		return ($this->tools->lookAnTable($this->dataStr[$pos]) >= 0);
	}

	private function identifyMode($pos)
	{
		if ($pos >= $this->dataStrLen){
			return -1; // all int input
		}

		switch (true){
			case $this->isdigitat($pos):
				return QR_MODE_NUM;
			case $this->isalnumat($pos):
				return QR_MODE_AN;
			case ($this->hint == QR_MODE_KANJI):
				if ($pos+1 < $this->dataStrLen) {
					$word = ($this->dataStr[$pos]) << 8 | $this->dataStr[$pos+1];
					if(($word >= 33088 && $word <= 40956) || ($word >= 57408 && $word <= 60351)) {
						return QR_MODE_KANJI;
					}
				}
		}

		return QR_MODE_8;
	}

	private function eatNum($p = 0)
	{
		while($this->isdigitat($p)) {
			$p++;
		}
		return $p;
	}

	private function eatAn($p = 0)
	{
		while($this->isalnumat($p)) {
			$p++;
		}
		return $p;
	}

	private function eatKanji($p = 0)
	{
		while($this->identifyMode($p) == QR_MODE_KANJI) {
			$p += 2;
		}
		return $p;
	}

	private function eat8()
	{
		$p = 0;

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
		if ($hint == QR_MODE_8) {

			$this->addStream(QR_MODE_8, $dataStr);

		} else {

			$this->dataStr = $dataStr;
			$this->dataStrLen = count($this->dataStr);
			$this->hint = $hint;
			$mod = $hint;

			while ($this->dataStrLen > 0)
			{
				if ($hint == -1){
					$mod = $this->identifyMode(0);
				}

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

				if($length == 0){
					break;
				}

				$this->addStream($mod, array_slice($this->dataStr, 0, $length));


				$this->dataStrLen -= $length;
				$this->dataStr = array_slice($this->dataStr, $length);
			}
		}

		list($bstream, $version) = $this->getBytes();

		return (new QRmask($bstream, $this->tools->getWidth($version), $this->level, $version))->get();
	}
}

?>