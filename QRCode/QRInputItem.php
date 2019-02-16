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
	
	private $QRinput;
	private $QRspec;
	
	function __construct($mode, int $size, array $data, $version) 
	{
		$setData = array_slice($data, 0, $size);
		
		if (empty($setData)){
			throw QRException::Std('trying to allocate more than we have in the array');
		}
		
		$this->QRinput = new QRinput();
		$this->QRspec = new QRspec();
		$this->bstream = [];
	
		if(!$this->QRinput->check($mode, $size, $setData)) {
			throw QRException::Std('Error m:'.$mode.',s:'.$size.',d:'.join(',',$setData));
			return null;
		}
		
		$this->mode = $mode;
		$this->size = $size;
		$this->data = $setData;
		$this->version = $version;
	}
	
	private function encodeModeNum()
	{		
		$words = (int)($this->size / 3);
		
		$val = 0x1;
		$this->bstream[] = [4, $val];
		$this->bstream[] = [$this->QRspec->lengthIndicator(QR_MODE_NUM, $this->version), $this->size];

		for($i=0; $i<$words; $i++) {
			$val  = (ord($this->data[$i*3  ]) - ord('0')) * 100;
			$val += (ord($this->data[$i*3+1]) - ord('0')) * 10;
			$val += (ord($this->data[$i*3+2]) - ord('0'));
			$this->bstream[] = [10, $val];
		}

		if($this->size - $words * 3 == 1) {
			$val = ord($this->data[$words*3]) - ord('0');
			$this->bstream[] = [4, $val];
		} else if($this->size - $words * 3 == 2) {
			$val  = (ord($this->data[$words*3  ]) - ord('0')) * 10;
			$val += (ord($this->data[$words*3+1]) - ord('0'));
			$this->bstream[] = [7, $val];
		}
	}

	private function encodeModeAn()
	{
		$words = (int)($this->size / 2);
		
		$this->bstream[] = [4, 0x02];
		$this->bstream[] = [$this->QRspec->lengthIndicator(QR_MODE_AN, $this->version), $this->size];

		for($i=0; $i<$words; $i++) {
			$val  = (int)$this->QRinput->lookAnTable(ord($this->data[$i*2])) * 45;
			$val += (int)$this->QRinput->lookAnTable(ord($this->data[$i*2+1]));

			$this->bstream[] = [11, $val];
		}

		if($this->size & 1) {
			$val = $this->QRinput->lookAnTable(ord($this->data[$words * 2]));
			$this->bstream[] = [6, $val];
		}
	}
	
	private function encodeMode8()
	{
		$this->bstream[] = [4, 0x4];
		$this->bstream[] = [$this->QRspec->lengthIndicator(QR_MODE_8, $this->version), $this->size];

		for($i=0; $i<$this->size; $i++) {
			$this->bstream[] = [8, ord($this->data[$i])];
		}
	}
	
	private function encodeModeKanji()
	{		
		$this->bstream[] = [4, 0x8];
		$this->bstream[] = [$this->QRspec->lengthIndicator(QR_MODE_KANJI, $this->version), (int)($this->size / 2)];

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
		$this->bstream[] = [4, 0x03];
		$this->bstream[] = [4, ord($this->data[1]) - 1];
		$this->bstream[] = [4, ord($this->data[0]) - 1];
		$this->bstream[] = [8, ord($this->data[2])];
	}
	
	public function estimateBitStreamSizeOfEntry()
	{
		$bits = 0;

		if($this->version == 0){
			$this->version = 1;
		}

		switch($this->mode) {
			case QR_MODE_NUM:
				$bits = $this->QRinput->estimateBitsModeNum($this->size);
				break;
			case QR_MODE_AN:
				$bits = $this->QRinput->estimateBitsModeAn($this->size);
				break;
			case QR_MODE_8:
				$bits = $this->QRinput->estimateBitsMode8($this->size);
				break;
			case QR_MODE_KANJI:
				$bits = $this->QRinput->estimateBitsModeKanji($this->size);
				break;
			case QR_MODE_STRUCTURE:
				return QR_STRUCTURE_HEADER_BITS;
			default:
				return 0;
		}

		$l = $this->QRspec->lengthIndicator($this->mode, $this->version);
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

		$words = $this->QRspec->maximumWords($this->mode, $this->version);

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