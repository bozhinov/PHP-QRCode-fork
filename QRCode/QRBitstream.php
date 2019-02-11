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

	public $data = "";
		
	public function size()
	{
		return strlen($this->data);
	}
	
	private function allocate($setLength)
	{
		return array_fill(0, $setLength, 0);
	}
	
	public function append(string $data)
	{
		$this->data .= $data;
	}

	public function appendNum($bits, $num)
	{
		if ($bits == 0){
			return;
		}
		
		$bit = decbin($num);
		$diff = $bits - strlen($bit);
		
		if ($diff != 0){
			$bit = str_repeat("0", $diff).$bit;
		}

		$this->append($bit);
	}

	public function toByte()
	{
		$size = $this->size();

		if($size == 0) {
			return [];
		}

		$data = str_split($this->data, 8);
		
		array_walk($data, function(&$val) {
			$val = bindec($val);
		});

		return $data;
	}

}
