<?php

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
		$this->data = array_values(array_merge($this->data, $data));
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

	public function appendBytes($size, $data)
	{
		if ($size == 0){
			return;
		}
		
		$bstream = $this->allocate($size * 8);
		$p=0;

		for($i=0; $i<$size; $i++) {
			$mask = 0x80;
			for($j=0; $j<8; $j++) {
				if($data[$i] & $mask) {
					$bstream[$p] = 1;
				} else {
					$bstream[$p] = 0;
				}
				$p++;
				$mask = $mask >> 1;
			}
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
