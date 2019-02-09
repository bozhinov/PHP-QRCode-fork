<?php

namespace QRCode;

class QRsplit {

	private $dataStr = '';
	private $modeHint;
	private $input;
	private $la;
	private $ln;

	function __construct($dataStr, QRinput $input, $modeHint) 
	{
		if(is_null($dataStr) || $dataStr == '\0' || $dataStr == '') {
			throw QRException::Std('empty string!');
		}
		
		$this->dataStr  = $dataStr;
		$this->input    = $input;
		$this->modeHint = $modeHint;

		$version = $this->input->getVersion();
		
		$QRspec  = new QRspec();
		$this->la = $QRspec->lengthIndicator(QR_MODE_AN, $version);
		$this->ln = $QRspec->lengthIndicator(QR_MODE_NUM, $version);
	}

	private function isdigitat($str, $pos)
	{
		if ($pos >= strlen($str)){
			return false;
		}
		
		return ((ord($str[$pos]) >= ord('0'))&&(ord($str[$pos]) <= ord('9')));
	}

	private function isalnumat($str, $pos)
	{
		if ($pos >= strlen($str)){
			return false;
		}

		return ($this->input->lookAnTable(ord($str[$pos])) >= 0);
	}

	private function identifyMode($pos)
	{
		if ($pos >= strlen($this->dataStr)){
			return QR_MODE_NUL;
		}
		
		$c = $this->dataStr[$pos];
		
		if($this->isdigitat($this->dataStr, $pos)) {
			return QR_MODE_NUM;
		} else if($this->isalnumat($this->dataStr, $pos)) {
			return QR_MODE_AN;
		} else if($this->modeHint == QR_MODE_KANJI) {
		
			if ($pos+1 < strlen($this->dataStr)) 
			{
				$d = $this->dataStr[$pos+1];
				$word = (ord($c) << 8) | ord($d);
				if(($word >= 0x8140 && $word <= 0x9ffc) || ($word >= 0xe040 && $word <= 0xebbf)) {
					return QR_MODE_KANJI;
				}
			}
		}

		return QR_MODE_8;
	} 

	private function eatNum()
	{
		$p = 0;
		while($this->isdigitat($this->dataStr, $p)) {
			$p++;
		}
		
		$run = $p;
		$mode = $this->identifyMode($p);
		
		if($mode == QR_MODE_8) {
			$dif = $this->input->estimateBitsModeNum($run) + 4 + $this->ln
				 + $this->input->estimateBitsMode8(1)         // + 4 + l8
				 - $this->input->estimateBitsMode8($run + 1); // - 4 - l8
			if($dif > 0) {
				return $this->eat8();
			}
		}
		if($mode == QR_MODE_AN) {
			$dif = $this->input->estimateBitsModeNum($run) + 4 + $this->ln
				 + $this->input->estimateBitsModeAn(1)        // + 4 + la
				 - $this->input->estimateBitsModeAn($run + 1);// - 4 - la
			if($dif > 0) {
				return $this->eatAn();
			}
		}
		
		$this->input->append(QR_MODE_NUM, $run, str_split($this->dataStr));

		return $run;
	}

	private function eatAn()
	{
		$p = 0;
		
		while($this->isalnumat($this->dataStr, $p)) {
			if($this->isdigitat($this->dataStr, $p)) {
				$q = $p;
				while($this->isdigitat($this->dataStr, $q)) {
					$q++;
				}
				
				$dif = $this->input->estimateBitsModeAn($p) // + 4 + la
					 + $this->input->estimateBitsModeNum($q - $p) + 4 + $this->ln
					 - $this->input->estimateBitsModeAn($q); // - 4 - la
					 
				if($dif < 0) {
					break;
				} else {
					$p = $q;
				}
			} else {
				$p++;
			}
		}

		$run = $p;

		if(!$this->isalnumat($this->dataStr, $p)) {
			$dif = $this->input->estimateBitsModeAn($run) + 4 + $this->la
				 + $this->input->estimateBitsMode8(1) // + 4 + l8
				  - $this->input->estimateBitsMode8($run + 1); // - 4 - l8
			if($dif > 0) {
				return $this->eat8();
			}
		}

		$ret = $this->input->append(QR_MODE_AN, $run, str_split($this->dataStr));
		if($ret < 0){
			return -1;
		}

		return $run;
	}

	private function eatKanji()
	{
		$p = 0;
		
		while($this->identifyMode($p) == QR_MODE_KANJI) {
			$p += 2;
		}
		
		$ret = $this->input->append(QR_MODE_KANJI, $p, str_split($this->dataStr));
		if($ret < 0){
			return -1;
		}

		return $run;
	}

	private function eat8()
	{
		$p = 1;
		$dataStrLen = strlen($this->dataStr);
		
		while($p < $dataStrLen) {
			
			$mode = $this->identifyMode($p);
			if($mode == QR_MODE_KANJI) {
				break;
			}
			if($mode == QR_MODE_NUM) {
				$q = $p;
				while($this->isdigitat($this->dataStr, $q)) {
					$q++;
				}
				$dif = $this->input->estimateBitsMode8($p) // + 4 + l8
					 + $this->input->estimateBitsModeNum($q - $p) + 4 + $this->ln
					 - $this->input->estimateBitsMode8($q); // - 4 - l8
				if($dif < 0) {
					break;
				} else {
					$p = $q;
				}
			} else if($mode == QR_MODE_AN) {
				$q = $p;
				while($this->isalnumat($this->dataStr, $q)) {
					$q++;
				}
				$dif = $this->input->estimateBitsMode8($p)  // + 4 + l8
					 + $this->input->estimateBitsModeAn($q - $p) + 4 + $this->la
					 - $this->input->estimateBitsMode8($q); // - 4 - l8
				if($dif < 0) {
					break;
				} else {
					$p = $q;
				}
			} else {
				$p++;
			}
		}

		$run = $p;
		$ret = $this->input->append(QR_MODE_8, $run, str_split($this->dataStr));
		
		if($ret < 0){
			return -1;
		}

		return $run;
	}

	private function splitString()
	{
		while (strlen($this->dataStr) > 0)
		{
			if($this->dataStr == ''){
				return 0;
			}

			$mode = $this->identifyMode(0);
			
			switch ($mode) {
				case QR_MODE_NUM: 
					$length = $this->eatNum();
					break;
				case QR_MODE_AN:  
					$length = $this->eatAn();
					break;
				case QR_MODE_KANJI:
					if ($hint == QR_MODE_KANJI){
						$length = $this->eatKanji();
					} else {
						$length = $this->eat8();
					}
					break;
				default: 
					$length = $this->eat8();
			}

			if($length == 0){
				return 0;
			} elseif($length < 0){
				return -1;
			}
			
			$this->dataStr = substr($this->dataStr, $length);
		}
	}

	private function toUpper()
	{
		$stringLen = strlen($this->dataStr);
		$p = 0;
		
		while ($p<$stringLen) {
			$mode = $this->identifyMode(substr($this->dataStr, $p), $this->modeHint);
			if($mode == QR_MODE_KANJI) {
				$p += 2;
			} else {
				if (ord($this->dataStr[$p]) >= ord('a') && ord($this->dataStr[$p]) <= ord('z')) {
					$this->dataStr[$p] = chr(ord($this->dataStr[$p]) - 32);
				}
				$p++;
			}
		}

		return $this->dataStr;
	}

	public function splitStringToQRinput($casesensitive = true)
	{
		if(!$casesensitive){
			$this->toUpper();
		}
		
		return $this->splitString();
	}
}