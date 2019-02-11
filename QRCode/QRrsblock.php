<?php

namespace QRCode;

class QRrsblock {
	public $dataLength;
	public $data = [];
	public $ecc = [];
	
	public function __construct($dl, $data, $el, &$ecc, QRrsItem $rs)
	{
		$ecc = $rs->encode_rs_char($data, $ecc);
	
		$this->dataLength = $dl;
		$this->data = $data;
		$this->ecc = $ecc;
	}
};

?>