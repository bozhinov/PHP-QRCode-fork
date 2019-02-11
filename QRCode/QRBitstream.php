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

class QRbitstream {

	public $data = [];
	
	public function flush(){
		$this->data = [];
	}
	
	public function size()
	{
		return count($this->data);
	}
	
	private function allocate($setLength)
	{
		return array_fill(0, $setLength, 0);
	}
	
	public function append(array $data)
	{
		$this->data = array_merge($this->data, $data);
	}

	public function appendNum($bits, $num)
	{
		if ($bits == 0){
			return;
		}
		
		$bstream = $this->allocate($bits);
		
		$mask = 1 << ($bits - 1);
		for($i=0; $i<$bits; $i++) {
			if($num & $mask) {
				$bstream[$i] = 1;
			} else {
				$bstream[$i] = 0;
			}
			$mask = $mask >> 1;
		}

		$this->append($bstream);
	}

	public function toByte()
	{
		$size = $this->size();

		if($size == 0) {
			return [];
		}

		$data = array_fill(0, (int)(($size + 7) / 8), 0);
		$bytes = (int)($size / 8);

		$p = 0;
		
		for($i=0; $i<$bytes; $i++) {
			$v = 0;
			for($j=0; $j<8; $j++) {
				$v = $v << 1;
				$v |= $this->data[$p];
				$p++;
			}
			$data[$i] = $v;
		}
		
		if($size & 7) {
			$v = 0;
			for($j=0; $j<($size & 7); $j++) {
				$v = $v << 1;
				$v |= $this->data[$p];
				$p++;
			}
			$data[$bytes] = $v;
		}

		return $data;
	}

}
