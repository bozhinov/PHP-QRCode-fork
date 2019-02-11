<?php

namespace QRCode;

class QRinput {

	public $items;
	private $version;
	private $level;
	private $anTable;
	
	private $QRspec;

	function __construct($version = 0, $level = QR_ECLEVEL_L)
	{		
		$this->level = $level;
		$this->setVersion($version);
		
		$this->QRspec = new QRspec();
		
		$this->anTable = [
			-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
			-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
			36, -1, -1, -1, 37, 38, -1, -1, -1, -1, 39, 40, -1, 41, 42, 43,
			 0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 44, -1, -1, -1, -1, -1,
			-1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,
			25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, -1, -1, -1, -1, -1,
			-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
			-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1
		];
	}

	public function setVersion($version)
	{
		if($version < 0 || $version > QR_SPEC_VERSION_MAX || $this->level > QR_ECLEVEL_H) {
			throw QRException::Std('Invalid version no');
		}
		
		$this->version = $version;
	}
	
	public function getVersion()
	{
		return $this->version;
	}

	public function append($mode, $size, $data)
	{
		$this->items[] = new QRinputItem($mode, $size, $data, $this->version);
	}
		
	private function checkModeNum($size, $data)
	{
		for($i=0; $i<$size; $i++) {
			if((ord($data[$i]) < ord('0')) || (ord($data[$i]) > ord('9'))){
				return false;
			}
		}

		return true;
	}

	public function estimateBitsModeNum($size)
	{
		$w = (int)$size / 3;
		$bits = $w * 10;
		
		switch($size - $w * 3) {
			case 1:
				$bits += 4;
				break;
			case 2:
				$bits += 7;
				break;
			default:
				break;
		}

		return $bits;
	}

	public function lookAnTable($c)
	{
		return (($c > 127) ? -1 : $this->anTable[$c]);
	}

	private function checkModeAn($size, $data)
	{
		for($i=0; $i<$size; $i++) {
			if ($this->lookAnTable(ord($data[$i])) == -1) {
				return false;
			}
		}

		return true;
	}

	public function estimateBitsModeAn($size)
	{
		$w = (int)($size / 2);
		$bits = $w * 11;
		
		if($size & 1) {
			$bits += 6;
		}

		return $bits;
	}

	public function estimateBitsMode8($size)
	{
		return $size * 8;
	}

	public function estimateBitsModeKanji($size)
	{
		return (int)(($size / 2) * 13);
	}

	private function checkModeKanji($size, $data)
	{
		if($size & 1){
			return false;
		}

		for($i=0; $i<$size; $i+=2) {
			$val = (ord($data[$i]) << 8) | ord($data[$i+1]);
			if( $val < 0x8140 || ($val > 0x9ffc && $val < 0xe040) || $val > 0xebbf) {
				return false;
			}
		}

		return true;
	}

	/*
	 * Validation
	*/
	public function check($mode, $size, $data)
	{
		if($size <= 0) {
			return false;
		}

		switch($mode) {
			case QR_MODE_NUM:      
				return $this->checkModeNum($size, $data);
				break;
			case QR_MODE_AN:
				return $this->checkModeAn($size, $data);
				break;
			case QR_MODE_KANJI:
				return $this->checkModeKanji($size, $data);
				break;
			case QR_MODE_8:
				return true;
				break;
			case QR_MODE_STRUCTURE:
				return true;
				break;
			default:
				break;
		}

		return false;
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
			$version = $this->QRspec->getMinimumVersion((int)(($bits + 7) / 8), $this->level);
			if ($version < 0) {
				return -1;
			}
		} while ($version > $prev);

		return $version;
	}

	private function createBitStream()
	{
		$total = 0;

		foreach($this->items as $item) {
			
			list($bitsSize, $bitsData) = $item->encodeBitStream();
				
			$total += $bitsSize;
		}

		return $total;
	}

	private function convertData()
	{
		$ver = $this->estimateVersion();
		if($ver > $this->version) {
			$this->setVersion($ver);
		}

		while (true) {
			
			$bits = $this->createBitStream();
			$ver = $this->QRspec->getMinimumVersion((int)(($bits + 7) / 8), $this->level);
			
			if($ver < 0) {
				throw QRException::Std('WRONG VERSION');
			} elseif($ver > $this->version) {
				$this->setVersion($ver);
			} else {
				break;
			}
		}
	}

	private function getByteStream()
	{		
		$this->convertData();

		$bstream = new QRbitstream();
				
		foreach($this->items as $item) {
			$bstream->append($item->bstream->data);
		}
				
		$bits = $bstream->size();
		$maxwords = $this->QRspec->getDataLength($this->version, $this->level);
		$maxbits = $maxwords * 8;

		if ($maxbits == $bits) {
			throw QRException::Std('null imput string');
		}

		if ($maxbits - $bits < 5) {
			$bstream->appendNum($maxbits - $bits, 0);
			# return; # Momchil: no idea why that's here
		}

		$bits += 4;
		$words = (int)(($bits + 7) / 8);

		$bstream->appendNum($words * 8 - $bits + 4, 0);

		$padlen = $maxwords - $words;
		
		if($padlen > 0) {
			
			for($i=0; $i<$padlen; $i++) {
				$bstream->appendNum(8, ($i&1) ? 17 : 236);
			}
		}
		
		return $bstream->toByte();
	}
	
	public function encodeMask($mask)
	{
		if($this->version < 0 || $this->version > QR_SPEC_VERSION_MAX) {
			throw QRException::Std('wrong version');
		}
		if($this->level > QR_ECLEVEL_H) {
			throw QRException::Std('wrong level');
		}
		
		$dataCode = $this->getByteStream();
				
		$width = $this->QRspec->getWidth($this->version);
		
		$frame = (new FrameFiller($this->version))->getFrame($dataCode, $this->level);
		
		$masked =(new QRmask($width, $this->level, $frame))->get($mask);
		
		return ["version" => $this->version, "width" => $width, "data" => $masked];
	}

}

