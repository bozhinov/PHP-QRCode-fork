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

	public $mode;
	public $size;
	public $data;
	public $bstream;
	public $version;

	private $tools;

	function __construct(int $mode, int $size, string $data, int $version)
	{
		$setData = str_split(substr($data, 0, $size));

		$this->tools = new QRTools();

		if(!$this->tools->Check($mode, $size, $setData)) {
			throw QRException::Std('Error m:'.$mode.',s:'.$size.',d:'.$data);
		}

		$this->bstream = [];
		$this->mode = $mode;
		$this->size = $size;
		$this->data = $setData;
		$this->version = $version;
	}

	private function encodeModeNum()
	{
		$words = (int)($this->size / 3);

		$val = 1;
		$this->bstream[] = [4, $val];
		$this->bstream[] = [$this->tools->lengthIndicator(QR_MODE_NUM, $this->version), $this->size];

		for($i=0; $i<$words; $i++) {
			$val  = (ord($this->data[$i*3]) - 48) * 100; # ord('0') == 48
			$val += (ord($this->data[$i*3+1]) - 48) * 10;
			$val += (ord($this->data[$i*3+2]) - 48);
			$this->bstream[] = [10, $val];
		}

		if($this->size - $words * 3 == 1) {
			$val = ord($this->data[$words*3]) - 48;
			$this->bstream[] = [4, $val];
		} else if($this->size - $words * 3 == 2) {
			$val  = (ord($this->data[$words*3]) - 48) * 10;
			$val += (ord($this->data[$words*3+1]) - 48);
			$this->bstream[] = [7, $val];
		}
	}

	private function encodeModeAn()
	{
		$words = (int)($this->size / 2);

		$this->bstream[] = [4, 2];
		$this->bstream[] = [$this->tools->lengthIndicator(QR_MODE_AN, $this->version), $this->size];

		for($i=0; $i<$words; $i++) {
			$val  = (int)($this->tools->lookAnTable(ord($this->data[$i*2])) * 45);
			$val += (int)($this->tools->lookAnTable(ord($this->data[$i*2+1])));

			$this->bstream[] = [11, $val];
		}

		if($this->size & 1) {
			$val = $this->tools->lookAnTable(ord($this->data[$words * 2]));
			$this->bstream[] = [6, $val];
		}
	}

	private function encodeMode8()
	{
		$this->bstream[] = [4, 4];
		$this->bstream[] = [$this->tools->lengthIndicator(QR_MODE_8, $this->version), $this->size];

		for($i=0; $i<$this->size; $i++) {
			$this->bstream[] = [8, ord($this->data[$i])];
		}
	}

	private function encodeModeKanji()
	{
		$this->bstream[] = [4, 8];
		$this->bstream[] = [$this->tools->lengthIndicator(QR_MODE_KANJI, $this->version), (int)($this->size / 2)];

		for($i=0; $i<$this->size; $i+=2) {
			$val = (ord($this->data[$i]) << 8) | ord($this->data[$i+1]);
			if($val <= 0x9ffc) {
				$val -= 0x8140;
			} else {
				$val -= 0xc140;
			}

			$h = ($val >> 8) * 0xc0;
			$val = ($val & 0xff) + $h;

			$this->bstream[] = [13, $val];
		}
	}

	private function encodeModeStructure()
	{
		$this->bstream[] = [4, 3];
		$this->bstream[] = [4, ord($this->data[1]) - 1];
		$this->bstream[] = [4, ord($this->data[0]) - 1];
		$this->bstream[] = [8, ord($this->data[2])];
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
				$bits = $this->tools->estimateBitsMode8($this->size);
				break;
			case QR_MODE_KANJI:
				$bits = $this->tools->estimateBitsModeKanji($this->size);
				break;
			case QR_MODE_STRUCTURE:
				return QR_STRUCTURE_HEADER_BITS;
			default:
				return 0;
		}

		$l = $this->tools->lengthIndicator($this->mode, $this->version);
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

		$words = $this->tools->maximumWords($this->mode, $this->version);

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
				case QR_MODE_STRUCTURE:
					$this->encodeModeStructure();
					break;
			}

		}

		return $this->bstream;
	}
}

?>