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
		$this->data = array_merge($this->data, $data);
	}

	public function appendNum($bits, $num)
	{
		if ($bits == 0){
			return;
		}
				
		$bstream = str_split(decbin($num));
		$diff = $bits - count($bstream);
		
		if ($diff != 0){
			$bstream = array_merge(array_fill(0, $diff, 0),$bstream);
		}

		$this->append($bstream);
	}

	public function toByte()
	{
		if($this->size() == 0) {
			return [];
		}
		
		$sectors = array_chunk($this->data, 8);
		$data = [];
		
		foreach($sectors as $sector){
			$data[] = bindec(implode("",$sector));
		}

		return $data;
	}

}
