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

class QRrawcode {

	private $blocks;
	private $count;
	private $b1;
	private $rsblocks = [];
	private $dataLength;
	private $eccLength;
	
	function __construct(array $dataCode, $dataLength, $eccLength, array $spec)
	{
		$this->count = 0;

		$this->b1 = $spec[0];
		$this->blocks = $spec[0] + $spec[3];
		
		$this->dataLength = $dataLength;
		$this->eccLength = $eccLength;
		
		$dl = $spec[1]; # rsDataCodes1
		$el = $spec[2]; # rsEccCodes1
		
		$blockNo = 0;
		$dataPos = 0;
		$eccPos = 0;
		
		$ecccode = array_fill(0, $this->eccLength, 0);

		$rs = new QRrsItem($el, 255 - $dl - $el);

		for($i = 0; $i < $spec[0]; $i++) { # rsBlockNum1

			$ecc = array_slice($ecccode,$eccPos);
			#$data = array_slice($dataCode, $dataPos);

			#$this->rsblocks[$blockNo] = ["dataLength" => $dl, "data" => $data, "ecc" => $rs->encode_rs_char($data, $ecc)];
			$this->rsblocks[$blockNo] = new QRrsblock($dl, array_slice($dataCode, $dataPos), $el,  $ecc, $rs);
			$ecccode = array_merge(array_slice($ecccode,0, $eccPos), $ecc);
			
			$dataPos += $dl;
			$eccPos += $el;
			$blockNo++;
		}

		if($spec[3] != 0) { # rsBlockNum2
			for($i = 0; $i < $spec[3]; $i++) {

				$ecc = array_slice($ecccode,$eccPos);
				
				#$this->rsblocks[$blockNo] = ["dataLength" => $dl, "data" => $data, "ecc" => $rs->encode_rs_char($data, $ecc)];
				$this->rsblocks[$blockNo] = new QRrsblock($dl, array_slice($dataCode, $dataPos), $el, $ecc, $rs);
				$ecccode = array_merge(array_slice($ecccode,0, $eccPos), $ecc);

				$dataPos += $dl;
				$eccPos += $el;
				$blockNo++;
			}
		}
	}

	public function getCode()
	{
		$ret = 0;

		if($this->count < $this->dataLength) {
			$row = $this->count % $this->blocks;
			$col = $this->count / $this->blocks;
			if($col >= $this->rsblocks[0]->dataLength) {
				$row += $this->b1;
			}
			$ret = $this->rsblocks[$row]->data[$col];
		} elseif($this->count < $this->dataLength + $this->eccLength) {
			$row = ($this->count - $this->dataLength) % $this->blocks;
			$col = ($this->count - $this->dataLength) / $this->blocks;
			$ret = $this->rsblocks[$row]->ecc[$col];
		} else {
			return 0;
		}
		$this->count++;

		return $ret;
	}
}

?>