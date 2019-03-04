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
	private $QRStreams;

	function __construct(int $level)
	{
		$this->level = $level;
		$this->tools = new QRTools();
		$this->QRStreams = new QRStreams($level);
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

			$this->QRStreams->add(QR_MODE_8, $dataStr);

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

				$this->QRStreams->add($mod, array_slice($this->dataStr, 0, $length));


				$this->dataStrLen -= $length;
				$this->dataStr = array_slice($this->dataStr, $length);
			}
		}

		list($bstream, $version) = $this->QRStreams->getBytes();

		return (new QRmask($bstream, $this->tools->getWidth($version), $this->level, $version))->get();
	}
}

?>