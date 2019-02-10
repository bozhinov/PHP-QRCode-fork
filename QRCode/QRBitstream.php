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
	
	public function append(array $data)
	{
		$this->data += $data;
	}

	public function appendNum(int $bits, int $num)
	{
		if ($bits == 0){
			return;
		}

		$this->data[] = [$bits, $num];
	}

	public function toByte()
	{
		if($this->size() == 0) {
			return [];
		}
		
		$dataStr = '';
		
		foreach($this->data as $d){
		
			$bit = decbin($d[1]);
			$diff = $d[0] - strlen($bit);
			
			if ($diff != 0){
				$bit = str_repeat("0", $diff).$bit;
			}

			$dataStr .= $bit;
		}
		
		$data = str_split($dataStr, 8);
		
		array_walk($data, function(&$val) {
			$val = bindec($val);
		});

		return $data;
	}

}
