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
	private $tools;

	function __construct(int $version, int $level)
	{
		$this->level = $level;
		$this->setVersion($version);
		$this->tools = new QRTools();
	}

	private function setVersion($version)
	{
		if($version <= 0 || $version > QR_SPEC_VERSION_MAX) {
			throw QRException::Std('Invalid version');
		}

		$this->version = $version;
	}

	/* Momchil: version can't be 0 */
	private function getMinimumVersion($bits)
	{
		$size = floor(($bits + 7) / 8);
		for($i=1; $i<= QR_SPEC_VERSION_MAX; $i++) {
			$words = $this->tools->capacity[$i][QR_CAP_WORDS] - $this->tools->capacity[$i][QR_CAP_EC][$this->level];
			if($words >= $size){
				return $i;
			}
		}

		throw QRException::Std('Unable to determine minimal version!');
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
			$version = $this->getMinimumVersion($bits);

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

		$bits = $this->createBitStream();
		$ver = $this->getMinimumVersion($bits);
		
		if($ver > $this->version) {
			$this->setVersion($ver);
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
		$maxwords = $this->tools->getDataLength($this->version, $this->level);
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

	public function encodeMask()
	{
		$dataCode = $this->getByteStream();

		$width = $this->tools->getWidth($this->version);

		return (new QRmask($dataCode, $width, $this->level, $this->version))->get();
	}

}

?>