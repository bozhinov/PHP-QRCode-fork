<?php 

namespace QRCode;

class QRinputItem {

	public $mode;
	public $size;
	public $data;
	public $bstream;
	public $version;
	
	private $QRinput;
	private $QRspec;
	
	function __construct($mode, $size, $data, $version) 
	{
		$setData = array_slice($data, 0, $size);
		
		if (count($setData) < $size) {
			$setData = array_merge($setData, array_fill(0,$size-count($setData),0));
		}
		
		$this->QRinput = new QRinput();
		$this->QRspec = new QRspec();
		$this->bstream = new QRbitstream();
	
		if(!$this->QRinput->check($mode, $size, $setData)) {
			throw QRException::Std('Error m:'.$mode.',s:'.$size.',d:'.join(',',$setData));
			return null;
		}
		
		$this->mode = $mode;
		$this->size = $size;
		$this->data = $setData;
		$this->version = $version;
	}
	
	private function encodeModeNum()
	{		
		$words = (int)($this->size / 3);
		
		$val = 0x1;
		$this->bstream->appendNum(4, $val);
		$this->bstream->appendNum($this->QRspec->lengthIndicator(QR_MODE_NUM, $this->version), $this->size);

		for($i=0; $i<$words; $i++) {
			$val  = (ord($this->data[$i*3  ]) - ord('0')) * 100;
			$val += (ord($this->data[$i*3+1]) - ord('0')) * 10;
			$val += (ord($this->data[$i*3+2]) - ord('0'));
			$this->bstream->appendNum(10, $val);
		}

		if($this->size - $words * 3 == 1) {
			$val = ord($this->data[$words*3]) - ord('0');
			$this->bstream->appendNum(4, $val);
		} else if($this->size - $words * 3 == 2) {
			$val  = (ord($this->data[$words*3  ]) - ord('0')) * 10;
			$val += (ord($this->data[$words*3+1]) - ord('0'));
			$this->bstream->appendNum(7, $val);
		}
	}

	private function encodeModeAn()
	{
		$words = (int)($this->size / 2);
		
		$this->bstream->appendNum(4, 0x02);
		$this->bstream->appendNum($this->QRspec->lengthIndicator(QR_MODE_AN, $this->version), $this->size);

		for($i=0; $i<$words; $i++) {
			$val  = (int)$this->QRinput->lookAnTable(ord($this->data[$i*2  ])) * 45;
			$val += (int)$this->QRinput->lookAnTable(ord($this->data[$i*2+1]));

			$this->bstream->appendNum(11, $val);
		}

		if($this->size & 1) {
			$val = $this->QRinput->lookAnTable(ord($this->data[$words * 2]));
			$this->bstream->appendNum(6, $val);
		}
	}
	
	private function encodeMode8()
	{
		$this->bstream->appendNum(4, 0x4);
		$this->bstream->appendNum($this->QRspec->lengthIndicator(QR_MODE_8, $this->version), $this->size);

		for($i=0; $i<$this->size; $i++) {
			$this->bstream->appendNum(8, ord($this->data[$i]));
		}
	}
	
	private function encodeModeKanji()
	{		
		$this->bstream->appendNum(4, 0x8);
		$this->bstream->appendNum($this->QRspec->lengthIndicator(QR_MODE_KANJI, $this->version), (int)($this->size / 2));

		for($i=0; $i<$this->size; $i+=2) {
			$val = (ord($this->data[$i]) << 8) | ord($this->data[$i+1]);
			if($val <= 0x9ffc) {
				$val -= 0x8140;
			} else {
				$val -= 0xc140;
			}
			
			$h = ($val >> 8) * 0xc0;
			$val = ($val & 0xff) + $h;

			$this->bstream->appendNum(13, $val);
		}
	}

	private function encodeModeStructure()
	{
		$this->bstream->appendNum(4, 0x03);
		$this->bstream->appendNum(4, ord($this->data[1]) - 1);
		$this->bstream->appendNum(4, ord($this->data[0]) - 1);
		$this->bstream->appendNum(8, ord($this->data[2]));
	}
	
	public function estimateBitStreamSizeOfEntry()
	{
		$bits = 0;

		if($this->version == 0){
			$this->version = 1;
		}

		switch($this->mode) {
			case QR_MODE_NUM:
				$bits = $this->QRinput->estimateBitsModeNum($this->size);
				break;
			case QR_MODE_AN:
				$bits = $this->QRinput->estimateBitsModeAn($this->size);
				break;
			case QR_MODE_8:
				$bits = $this->QRinput->estimateBitsMode8($this->size);
				break;
			case QR_MODE_KANJI:
				$bits = $this->QRinput->estimateBitsModeKanji($this->size);
				break;
			case QR_MODE_STRUCTURE:
				return QR_STRUCTURE_HEADER_BITS;
			default:
				return 0;
		}

		$l = $this->QRspec->lengthIndicator($this->mode, $this->version);
		$m = 1 << $l;
		$num = (int)(($this->size + $m - 1) / $m);

		$bits += $num * (4 + $l);

		return $bits;
	}
	
	public function encodeBitStream()
	{

		$words = $this->QRspec->maximumWords($this->mode, $this->version);
		
		if($this->size > $words) {

			list($bstreamSize1, $bstreamData1) = (new QRinputItem($this->mode, $words, $this->data, $this->version))->encodeBitStream();
			list($bstreamSize2, $bstreamData2) = (new QRinputItem($this->mode, $this->size - $words, array_slice($this->data, $words), $this->version))->encodeBitStream();

			$this->bstream->flush();
			$this->bstream->append($bstreamData1);
			$this->bstream->append($bstreamData2);
			
		} else {
			
			switch($this->mode) {
				case QR_MODE_NUM:
					$this->encodeModeNum();
					break;
				case QR_MODE_AN:
					$this->encodeModeAn();
					break;
				case QR_MODE_8:
					$this->encodeMode8();
					break;
				case QR_MODE_KANJI:
					$this->encodeModeKanji();
					break;
				case QR_MODE_STRUCTURE:
					$this->encodeModeStructure();
					break;
			}

		}

		return [$this->bstream->size(), $this->bstream->data];
	}
}

?>