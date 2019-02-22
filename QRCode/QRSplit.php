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

class QRsplit {

	private $dataStr;
	private $dataStrLen;
	private $casesensitive;
	private $hint;
	private $input;
	private $tools;
	private $la;
	private $ln;

	function __construct(bool $casesensitive, int $hint, int $version, int $level)
	{
		$this->hint = $hint;
		$this->casesensitive = $casesensitive;

		$this->tools = new QRTools();
		$this->input = new QRinput($version, $level);

		$this->la = $this->tools->lengthIndicator(QR_MODE_AN, $version);
		$this->ln = $this->tools->lengthIndicator(QR_MODE_NUM, $version);
	}

	private function isdigitat($pos)
	{
		if ($pos >= $this->dataStrLen){
			return false;
		}
		# ord('0') = 48 && ord('9') = 57
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
			return QR_MODE_NUL;
		}

		if($this->isdigitat($pos)) {
			return QR_MODE_NUM;
		} elseif($this->isalnumat($pos)) {
			return QR_MODE_AN;
		} elseif($this->hint == QR_MODE_KANJI) {

			if ($pos+1 < $this->dataStrLen) 
			{
				$word = ($this->dataStr[$pos]) << 8 | $this->dataStr[$pos+1];
				if(($word >= 33088 && $word <= 40956) || ($word >= 57408 && $word <= 60351)) {
					return QR_MODE_KANJI;
				}
			}
		}

		return QR_MODE_8;
	}

	private function eatNum()
	{
		$p = 0;
		while($this->isdigitat($p)) {
			$p++;
		}

		$run = $p;
		$mode = $this->identifyMode($p);

		if($mode == QR_MODE_8) {
			$dif = $this->tools->estimateBitsModeNum($run) + 4 + $this->ln
				 + $this->tools->estimateBitsMode8(1)         // + 4 + l8
				 - $this->tools->estimateBitsMode8($run + 1); // - 4 - l8
			if($dif > 0) {
				return $this->eat8();
			}
		}
		if($mode == QR_MODE_AN) {
			$dif = $this->tools->estimateBitsModeNum($run) + 4 + $this->ln
				 + $this->tools->estimateBitsModeAn(1)        // + 4 + la
				 - $this->tools->estimateBitsModeAn($run + 1);// - 4 - la
			if($dif > 0) {
				return $this->eatAn();
			}
		}

		$this->input->append(QR_MODE_NUM, $run, $this->dataStr);

		return $run;
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

				$dif = $this->tools->estimateBitsModeAn($p) // + 4 + la
					 + $this->tools->estimateBitsModeNum($q - $p) + 4 + $this->ln
					 - $this->tools->estimateBitsModeAn($q); // - 4 - la

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
			$dif = $this->tools->estimateBitsModeAn($run) + 4 + $this->la
				 + $this->tools->estimateBitsMode8(1) // + 4 + l8
				 - $this->tools->estimateBitsMode8($run + 1); // - 4 - l8
			if($dif > 0) {
				return $this->eat8();
			}
		}

		$this->input->append(QR_MODE_AN, $run, $this->dataStr);

		return $run;
	}

	private function eatKanji()
	{
		$p = 0;

		while($this->identifyMode($p) == QR_MODE_KANJI) {
			$p += 2;
		}

		$this->input->append(QR_MODE_KANJI, $p, $this->dataStr);

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
					$dif = $this->tools->estimateBitsMode8($p) // + 4 + l8
						 + $this->tools->estimateBitsModeNum($q - $p) + 4 + $this->ln
						 - $this->tools->estimateBitsMode8($q); // - 4 - l8
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
					$dif = $this->tools->estimateBitsMode8($p)  // + 4 + l8
						 + $this->tools->estimateBitsModeAn($q - $p) + 4 + $this->la
						 - $this->tools->estimateBitsMode8($q); // - 4 - l8
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

		$this->input->append(QR_MODE_8, $p, $this->dataStr);

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
				#  ord('a') = 97 && ord('z') = 122
				if ($this->dataStr[$p] >= 97 && $this->dataStr[$p] <= 122) {
					$this->dataStr[$p] -= 32;
				}
				$p++;
			}
		}
	}

	public function splitString(array $dataStr)
	{
		$this->dataStr = $dataStr;
		$this->dataStrLen = count($this->dataStr);

		if(!$this->casesensitive){
			$this->toUpper();
		}

		while ($this->dataStrLen > 0)
		{
			switch ($this->identifyMode(0)) {
				case QR_MODE_NUM:
					$length = $this->eatNum();
					break;
				case QR_MODE_AN:
					$length = $this->eatAn();
					break;
				case QR_MODE_KANJI:
					if ($this->hint == QR_MODE_KANJI){
						$length = $this->eatKanji();
					} else {
						$length = $this->eat8();
					}
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

		return $this->input->encodeMask();
	}
}

?>