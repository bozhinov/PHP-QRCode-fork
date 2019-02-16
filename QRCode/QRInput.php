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

class QRinput {

	public $items;
	private $version;
	private $level;
	
	private $QRspec;

	function __construct($version = 0, $level = QR_ECLEVEL_L)
	{
		$this->level = $level;
		$this->setVersion($version);
		
		$this->QRspec = new QRspec();
	}

	public function setVersion($version)
	{
		if($version < 0 || $version > QR_SPEC_VERSION_MAX || $this->level > QR_ECLEVEL_H) {
			throw QRException::Std('Invalid version no');
		}

		$this->version = $version;
	}

	public function append($mode, $size, $data)
	{
		$this->items[] = new QRinputItem($mode, $size, $data, $this->version);
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
			$version = $this->QRspec->getMinimumVersion(floor(($bits + 7) / 8), $this->level);
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
			
			$bitsData = $item->encodeBitStream();

			$total += count($bitsData);
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
			$ver = $this->QRspec->getMinimumVersion(floor(($bits + 7) / 8), $this->level);
			
			if($ver < 0) {
				throw QRException::Std('WRONG VERSION');
			} elseif($ver > $this->version) {
				$this->setVersion($ver);
			} else {
				break;
			}
		}
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
		if(empty($bstream)) {
			return [];
		}

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
		$this->convertData();

		$bstream = [];

		foreach($this->items as $item) {
			$bstream = array_merge($bstream, $item->bstream);
		}

		$bits = $this->get_bstream_size($bstream);
		$maxwords = $this->QRspec->getDataLength($this->version, $this->level);
		$maxbits = $maxwords * 8;
		
		if ($maxbits == $bits) {
			throw QRException::Std('null imput string');
		}

		if ($maxbits - $bits < 5) {
			$bstream[] = [$maxbits - $bits, 0];
			throw QRException::Std('Please let me know how you got here');
			# return; # Momchil: no idea why that's here
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

		$masked = (new QRmask($width, $this->level, $frame))->get($mask);

		return ["version" => $this->version, "width" => $width, "data" => $masked];
	}

}

?>