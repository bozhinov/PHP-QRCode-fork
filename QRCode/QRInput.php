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
	private $items;
	private $version = 1;
	private $level;
	private $tools;
	
	function __construct(int $level)
	{
		$this->level = $level;
		$this->tools = new QRTools();
	}

	private function setVersion($version)
	{
		if ($version > $this->version){
			$this->version = $version;
		}
	}

	/* Momchil: version can't be 0 */
	private function getMinimumVersion($bits)
	{
		$size = (int)(($bits + 7) / 8);
		for($i=1; $i<= QR_SPEC_VERSION_MAX; $i++) {
			if($this->tools->getDataLength($i, $this->level) >= $size){
				return $i;
			}
		}

		throw QRException::Std('Unable to determine minimal version!');
	}

	private function estimateBitStreamSize($version)
	{
		$bits = 0;

		foreach($this->items as $item) {
			$bits += $item->estimateBitStreamSizeOfEntry($version);
		}

		return $bits;
	}

	private function estimateVersion()
	{
		$version = 0;
		$prev = 0;
		do {
			$prev = $version;
			$bits = $this->estimateBitStreamSize($prev);
			$version = $this->getMinimumVersion($bits);

		} while ($version > $prev);

		$this->setVersion($version);
	}

	private function createBitStream()
	{
		$total = 0;

		foreach($this->items as $item) {

			$bitsData = $item->encodeBitStream();

			$total += count($bitsData);
		}

		return $total;
	}

	private function get_bstream_size($bstream)
	{
		$size = 0;

		foreach($bstream as $d){
			$size += $d[0];
		}

		return $size;
	}

	private function bstream_toByte($bstream)
	{
		$dataStr = "";

		foreach($bstream as $d){

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

	private function getByteStream()
	{
		$this->estimateVersion();
		$this->setVersion($this->getMinimumVersion($this->createBitStream()));

		$bstream = [];

		foreach($this->items as $item) {
			$bstream = array_merge($bstream, $item->getBStream());
		}

		$bits = $this->get_bstream_size($bstream);
		$maxwords = $this->tools->getDataLength($this->version, $this->level);
		$maxbits = $maxwords * 8;

		if ($maxbits - $bits < 5) {
			$bstream[] = [$maxbits - $bits, 0];
			return $this->bstream_toByte($bstream);
		}

		$bits += 4;
		$words = floor(($bits + 7) / 8);

		$bstream[] = [$words * 8 - $bits + 4, 0];

		$padlen = $maxwords - $words;

		if($padlen > 0) {
			for($i=0; $i<$padlen; $i+=2) {
				$bstream[] = [8, 236];
				$bstream[] = [8, 17];
			}
		}

		return $this->bstream_toByte($bstream);
	}

	private function append($mode, $size, $data)
	{
		$this->items[] = new QRinputItem($mode, $size, $data);
	}

	private function encodeMask()
	{
		$dataCode = $this->getByteStream();

		$width = $this->tools->getWidth($this->version);

		return (new QRmask($dataCode, $width, $this->level, $this->version))->get();
	}

	private function isdigitat($pos)
	{
		if ($pos >= $this->dataStrLen){
			return false;
		}
		$ord = $this->dataStr[$pos];
		return ($ord >= 48 && $ord <= 57);
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

		if($this->isdigitat($pos)) {
			return QR_MODE_NUM;
		} elseif($this->isalnumat($pos)) {
			return QR_MODE_AN;
		} else {
			return QR_MODE_8;
		}
	}

	private function eatNum()
	{
		$p = 0;
		while($this->isdigitat($p)) {
			$p++;
		}

		$mode = $this->identifyMode($p);

		if($mode == QR_MODE_8) {
			$dif = $this->tools->estimateBitsModeNum($p) + 14 - (8 * $p); // estimateBitsMode8
			if($dif > 0) {
				return $this->eat8();
			}
		}
		if($mode == QR_MODE_AN) {
			$dif = $this->tools->estimateBitsModeNum($p) + 14
				 + $this->tools->estimateBitsModeAn(1)
				 - $this->tools->estimateBitsModeAn($p + 1);
			if($dif > 0) {
				return $this->eatAn();
			}
		}

		$this->append(QR_MODE_NUM, $p, $this->dataStr);

		return $p;
	}

	private function eatAn()
	{
		$p = 0;

		while($this->isalnumat($p)) {
			if($this->isdigitat($p)) {
				$q = $p;
				while($this->isdigitat($q)) {
					$q++;
				}

				$dif = $this->tools->estimateBitsModeAn($p)
					 + $this->tools->estimateBitsModeNum($q - $p) + 14
					 - $this->tools->estimateBitsModeAn($q);

				if($dif < 0) {
					break;
				} else {
					$p = $q;
				}
			} else {
				$p++;
			}
		}

		$run = $p;

		if(!$this->isalnumat($p)) {
			$dif = $this->tools->estimateBitsModeAn($run) + 13 - (8 * $run);
			if($dif > 0) {
				return $this->eat8();
			}
		}

		$this->append(QR_MODE_AN, $run, $this->dataStr);

		return $run;
	}

	private function eatKanji()
	{
		$p = 0;

		while($this->identifyMode($p) == QR_MODE_KANJI) {
			$p += 2;
		}

		$this->append(QR_MODE_KANJI, $p, $this->dataStr);

		return $p;
	}

	private function eat8()
	{
		$p = 1;

		while($p < $this->dataStrLen) {

			switch($this->identifyMode($p)){
				case QR_MODE_KANJI:
					break 2;
				case QR_MODE_NUM:
					$q = $p;
					while($this->isdigitat($q)) {
						$q++;
					}
					$dif = (8 * ($p - $q)) + $this->tools->estimateBitsModeNum($q - $p) + 14;
					if($dif < 0) {
						break 2;
					} else {
						$p = $q;
					}
					break;
				case QR_MODE_AN:
					$q = $p;
					while($this->isalnumat($q)) {
						$q++;
					}
					$dif = (8 * ($p - $q)) + $this->tools->estimateBitsModeAn($q - $p) + 13;
					if($dif < 0) {
						break 2;
					} else {
						$p = $q;
					}
					break;
				default:
					$p++;
			}
		}

		$this->append(QR_MODE_8, $p, $this->dataStr);

		return $p;
	}

	private function toUpper()
	{
		$p = 0;

		while ($p<$this->dataStrLen) {
			$mode = $this->identifyMode(array_slice($this->dataStr, $p));
			if($mode == QR_MODE_KANJI) {
				$p += 2;
			} else {
				if ($this->dataStr[$p] >= 97 && $this->dataStr[$p] <= 122) {
					$this->dataStr[$p] -= 32;
				}
				$p++;
			}
		}
	}

	public function encodeString($dataStr, $casesensitive, $hint)
	{
		$this->dataStr = $dataStr;
		$this->dataStrLen = count($this->dataStr);

		if(!$casesensitive){
			$this->toUpper();
		}
		
		if ($hint == QR_MODE_8) {
			$this->append(QR_MODE_8, $this->dataStrLen, $dataStr);
			return $this->encodeMask();
		}

		while ($this->dataStrLen > 0)
		{
			if ($hint == -1){
				$mod = $this->identifyMode(0);
			} else {
				$mod = $hint;
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
					$length = $this->eat8();
			}

			if($length == 0){
				break;
			}

			$this->dataStrLen -= $length;
			$this->dataStr = array_slice($this->dataStr, $length);
		}

		return $this->encodeMask();
	}
}

?>