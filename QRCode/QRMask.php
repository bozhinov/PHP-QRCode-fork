<?php

namespace QRCode;

class QRmask {

	private $runLength = [];
	private $width;
	private $level;
	private $frame;

	function __construct($width, $level, $frame)
	{
		$this->runLength = array_fill(0, QR_SPEC_WIDTH_MAX + 1, 0);
		$this->width = $width;
		$this->level = $level;
		$this->frame = $frame;
	}

	private function writeFormatInformation(&$frame, $mask)
	{
		$blacks = 0;
		$format = (new QRspec())->getFormatInfo($mask, $this->level);

		for($i=0; $i<8; $i++) {
			if($format & 1) {
				$blacks += 2;
				$v = 0x85;
			} else {
				$v = 0x84;
			}
			
			$frame[8][$this->width - 1 - $i] = chr($v);
			if($i < 6) {
				$frame[$i][8] = chr($v);
			} else {
				$frame[$i + 1][8] = chr($v);
			}
			$format = $format >> 1;
		}
		
		for($i=0; $i<7; $i++) {
			if($format & 1) {
				$blacks += 2;
				$v = 0x85;
			} else {
				$v = 0x84;
			}
			
			$frame[$this->width - 7 + $i][8] = chr($v);
			if($i == 0) {
				$frame[8][7] = chr($v);
			} else {
				$frame[8][6 - $i] = chr($v);
			}
			
			$format = $format >> 1;
		}

		return $blacks;
	}

	private function maskByNum($x, $y, $num)
	{
		$ret = false;
		
		switch($num){
			case 0:
				$ret = ($x+$y)&1;
				break;
			case 1:
				$ret = ($y&1);
				break;
			case 2:
				$ret = ($x%3);
				break;
			case 3:
				$ret = ($x+$y)%3;
				break;
			case 4:
				$ret = (((int)($y/2))+((int)($x/3)))&1;
				break;
			case 5:
				$ret = (($x*$y)&1)+($x*$y)%3;
				break;
			case 6:
				$ret = ((($x*$y)&1)+($x*$y)%3)&1;
				break;
			case 7:
				$ret = ((($x*$y)%3)+(($x+$y)&1))&1;
				break;
		}
		
		return $ret;
	}

	private function generateMaskNo($maskNo)
	{
		$bitMask = array_fill(0, $this->width, array_fill(0, $this->width, 0));
		
		for($y=0; $y<$this->width; $y++) {
			for($x=0; $x<$this->width; $x++) {
				if(ord($this->frame[$y][$x]) & 0x80) {
					$bitMask[$y][$x] = 0;
				} else {
					$maskFunc = $this->maskByNum($x, $y, $maskNo);
					$bitMask[$y][$x] = ($maskFunc == 0)?1:0;
				}
			}
		}
		
		return $bitMask;
	}

	private function makeMaskNo(&$masked, $maskNo)
	{
		$b = 0;

		$bitMask = $this->generateMaskNo($maskNo);

		$s = $this->frame;
		$masked = $this->frame;

		for($y=0; $y<$this->width; $y++) {
			for($x=0; $x<$this->width; $x++) {
				if($bitMask[$y][$x] == 1) {
					$masked[$y][$x] = chr(ord($s[$y][$x]) ^ (int)$bitMask[$y][$x]);
				}
				$b += (int)(ord($masked[$y][$x]) & 1);
			}
		}

		return $b;
	}

	private function makeMask($maskNo)
	{
		$masked = array_fill(0, $this->width, str_repeat("\0", $this->width));
		$this->makeMaskNo($masked, $maskNo);
		$this->writeFormatInformation($masked, $maskNo);
   
		return $masked;
	}

	private function calcN1N3($length)
	{
		$demerit = 0;

		for($i=0; $i<$length; $i++) {
			
			if($this->runLength[$i] >= 5) {
				$demerit += (QR_N1 + ($this->runLength[$i] - 5));
			}
			if($i & 1) {
				if(($i >= 3) && ($i < ($length-2)) && ($this->runLength[$i] % 3 == 0)) {
					$fact = (int)($this->runLength[$i] / 3);
					if(($this->runLength[$i-2] == $fact) &&
					   ($this->runLength[$i-1] == $fact) &&
					   ($this->runLength[$i+1] == $fact) &&
					   ($this->runLength[$i+2] == $fact)) {
						if(($this->runLength[$i-3] < 0) || ($this->runLength[$i-3] >= (4 * $fact))) {
							$demerit += QR_N3;
						} else if((($i+3) >= $length) || ($this->runLength[$i+3] >= (4 * $fact))) {
							$demerit += QR_N3;
						}
					}
				}
			}
		}
		return $demerit;
	}

	private function evaluateSymbol($mask)
	{
		$head = 0;
		$demerit = 0;

		for($y=0; $y<$this->width; $y++) {
			$head = 0;
			$this->runLength[0] = 1;
			
			$frameY = $mask[$y];
			
			if ($y>0){
				$frameYM = $mask[$y-1];
			}
			
			for($x=0; $x<$this->width; $x++) {
				if(($x > 0) && ($y > 0)) {
					$b22 = ord($frameY[$x]) & ord($frameY[$x-1]) & ord($frameYM[$x]) & ord($frameYM[$x-1]);
					$w22 = ord($frameY[$x]) | ord($frameY[$x-1]) | ord($frameYM[$x]) | ord($frameYM[$x-1]);
					
					if(($b22 | ($w22 ^ 1))&1) {
						$demerit += QR_N2;
					}
				}
				if(($x == 0) && (ord($frameY[$x]) & 1)) {
					$this->runLength[0] = -1;
					$head = 1;
					$this->runLength[$head] = 1;
				} else if($x > 0) {
					if((ord($frameY[$x]) ^ ord($frameY[$x-1])) & 1) {
						$head++;
						$this->runLength[$head] = 1;
					} else {
						$this->runLength[$head]++;
					}
				}
			}

			$demerit += $this->calcN1N3($head+1);
		}

		for($x=0; $x<$this->width; $x++) {
			$head = 0;
			$this->runLength[0] = 1;
			
			for($y=0; $y<$this->width; $y++) {
				if($y == 0 && (ord($mask[$y][$x]) & 1)) {
					$this->runLength[0] = -1;
					$head = 1;
					$this->runLength[$head] = 1;
				} else if($y > 0) {
					if((ord($mask[$y][$x]) ^ ord($mask[$y-1][$x])) & 1) {
						$head++;
						$this->runLength[$head] = 1;
					} else {
						$this->runLength[$head]++;
					}
				}
			}
		
			$demerit += $this->calcN1N3($head+1);
		}

		return $demerit;
	}

	private function mask()
	{
		$minDemerit = PHP_INT_MAX;
		$bestMaskNum = 0;

		$checked_masks = [0,1,2,3,4,5,6,7];

		if (QR_FIND_FROM_RANDOM !== false) {

			$howManuOut = 8-(QR_FIND_FROM_RANDOM % 9);
			for ($i = 0; $i <  $howManuOut; $i++) {
				$remPos = rand (0, count($checked_masks)-1);
				unset($checked_masks[$remPos]);
				$checked_masks = array_values($checked_masks);
			}
		
		}

		$bestMask = $this->frame;

		foreach($checked_masks as $i) {
			$mask = array_fill(0, $this->width, str_repeat("\0", $this->width));

			$demerit = 0;
			$blacks = 0;
			$blacks  = $this->makeMaskNo($mask, $i);
			$blacks += $this->writeFormatInformation($mask, $i);
			$blacks  = (int)(100 * $blacks / ($this->width * $this->width));
			$demerit = (int)((int)(abs($blacks - 50) / 5) * QR_N4);
			$demerit += $this->evaluateSymbol($mask);
			
			if($demerit < $minDemerit) {
				$minDemerit = $demerit;
				$bestMask = $mask;
				$bestMaskNum = $i;
			}
		}

		return $bestMask;
	}

	public function get($mask)
	{
		if($mask < 0) {
		
			if (QR_FIND_BEST_MASK) {
				$masked = $this->mask();
			} else {
				$masked = $this->makeMask((intval(QR_DEFAULT_MASK) % 8));
			}
		} else {
			$masked = $this->makeMask($mask);
		}
		
		if($masked == NULL) {
			throw QRException::Std('Mask is null');
		}

		return $masked;
	}

}
