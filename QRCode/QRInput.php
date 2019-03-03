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
	private $hint;
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
		} elseif($this->hint == QR_MODE_KANJI) {
			if ($pos+1 < $this->dataStrLen) {
				$word = ($this->dataStr[$pos]) << 8 | $this->dataStr[$pos+1];
				if(($word >= 33088 && $word <= 40956) || ($word >= 57408 && $word <= 60351)) {
					return QR_MODE_KANJI;
				}
			}
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

		return $p;
	}

	private function eatKanji()
	{
		$p = 0;

		while($this->identifyMode($p) == QR_MODE_KANJI) {
			$p += 2;
		}

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

		return $p;
	}

	public function encodeString($dataStr, $hint)
	{
		$this->dataStr = $dataStr;
		$this->dataStrLen = count($this->dataStr);
		
		if ($hint == QR_MODE_8) {
			$this->items[] = new QRinputItem(QR_MODE_8, $dataStr);
		} else {
		
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
				
				$this->items[] = new QRinputItem($mod, array_slice($this->dataStr, 0, $length));

				$this->dataStrLen -= $length;
				$this->dataStr = array_slice($this->dataStr, $length);
			}
		}

		return (new QRmask($this->getByteStream(), $this->tools->getWidth($this->version), $this->level, $this->version))->get();
	}
}

?>