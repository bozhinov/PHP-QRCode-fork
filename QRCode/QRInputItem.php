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

class QRinputItem {

	private $mode;
	private $size;
	private $data;
	private $version;
	private $tools;
	
	public $bstream;
	
	private $lengthTableBits = [
		[10, 12, 14],
		[9, 11, 13],
		[8, 16, 16],
		[8, 10, 12]
	];

	function __construct(int $mode, int $size, array $data, int $version)
	{
		$data = array_slice($data, 0, $size);
		$this->tools = new QRTools();

		if(!$this->tools->Check($mode, $size, $data)) {
			throw QRException::Std('InputItem check failed');
		}

		$this->bstream = [];
		$this->mode = $mode;
		$this->size = $size;
		$this->data = $data;
		$this->version = $version;
	}
	
	private function maximumWords()
	{
		if($this->version <= 9) {
			$l = 0;
		} elseif($this->version <= 26) {
			$l = 1;
		} else {
			$l = 2;
		}

		$bits = $this->lengthTableBits[$this->mode][$l];
		$words = (1 << $bits) - 1;
		
		if($this->mode == QR_MODE_KANJI) {
			$words *= 2; // the number of bytes is required
		}

		return $words;
	}
	
	private function lengthIndicator($mode)
	{
		if ($this->version <= 9) {
			$l = 0;
		} else if ($this->version <= 26) {
			$l = 1;
		} else {
			$l = 2;
		}

		return $this->lengthTableBits[$mode][$l];
	}

	private function encodeModeNum()
	{
		$words = (int)($this->size / 3);

		$val = 1;
		$this->bstream[] = [4, $val];
		$this->bstream[] = [$this->lengthIndicator(QR_MODE_NUM), $this->size];

		for($i=0; $i<$words; $i++) {
			$val  = ($this->data[$i*3] - 48) * 100; # ord('0') == 48
			$val += ($this->data[$i*3+1] - 48) * 10;
			$val += ($this->data[$i*3+2] - 48);
			$this->bstream[] = [10, $val];
		}

		if($this->size - $words * 3 == 1) {
			$val = $this->data[$words*3] - 48;
			$this->bstream[] = [4, $val];
		} elseif($this->size - $words * 3 == 2) {
			$val  = ($this->data[$words*3] - 48) * 10;
			$val += ($this->data[$words*3+1] - 48);
			$this->bstream[] = [7, $val];
		}
	}

	private function encodeModeAn()
	{
		$words = (int)($this->size / 2);

		$this->bstream[] = [4, 2];
		$this->bstream[] = [$this->lengthIndicator(QR_MODE_AN), $this->size];

		for($i=0; $i<$words; $i++) {
			$val  = (int)($this->tools->lookAnTable($this->data[$i*2]) * 45);
			$val += (int)($this->tools->lookAnTable($this->data[$i*2+1]));

			$this->bstream[] = [11, $val];
		}

		if($this->size & 1) {
			$val = $this->tools->lookAnTable($this->data[$words * 2]);
			$this->bstream[] = [6, $val];
		}
	}

	private function encodeMode8()
	{
		$this->bstream[] = [4, 4];
		$this->bstream[] = [$this->lengthIndicator(QR_MODE_8), $this->size];

		for($i=0; $i<$this->size; $i++) {
			$this->bstream[] = [8, $this->data[$i]];
		}
	}

	private function encodeModeKanji()
	{
		$this->bstream[] = [4, 8];
		$this->bstream[] = [$this->lengthIndicator(QR_MODE_KANJI), (int)($this->size / 2)];

		for($i=0; $i<$this->size; $i+=2) {
			$val = ($this->data[$i] << 8) | $this->data[$i+1];
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

	public function estimateBitStreamSizeOfEntry()
	{
		$bits = 0;

		switch($this->mode) {
			case QR_MODE_NUM:
				$bits = $this->tools->estimateBitsModeNum($this->size);
				break;
			case QR_MODE_AN:
				$bits = $this->tools->estimateBitsModeAn($this->size);
				break;
			case QR_MODE_8:
				$bits = 8 * $this->size;
				break;
			case QR_MODE_KANJI:
				$bits = $this->tools->estimateBitsModeKanji($this->size);
				break;
			default:
				return 0;
		}

		$l = $this->lengthIndicator($this->mode);
		$m = 1 << $l;
		$num = (int)(($this->size + $m - 1) / $m);

		$bits += $num * (4 + $l);

		return $bits;
	}

	public function encodeBitStream(int $size = -1, array $data = [])
	{
		if ($size == -1){
			$size = $this->size;
		}
		if (empty($data)){
			$data = $this->data;
		}

		$words = $this->maximumWords();

		if($this->size > $words) {

			$bstreamData1 = $this->encodeBitStream($words);
			$bstreamData2 = $this->encodeBitStream($this->size - $words, array_slice($this->data, $words));

			$this->bstream = array_merge($bstreamData1, $bstreamData2);

		} else {

			switch($this->mode) {
				case QR_MODE_NUM:
					$this->encodeModeNum();
					break;
				case QR_MODE_AN:
					$this->encodeModeAn();
					break;
				case QR_MODE_8:
					$this->encodeMode8();
					break;
				case QR_MODE_KANJI:
					$this->encodeModeKanji();
					break;
			}

		}

		return $this->bstream;
	}
}

?>