<?php

namespace QRCode;

class QRrawcode {

	public $blocks;
	public $rsblocks = []; //of RSblock
	public $count;
	public $dataLength;
	public $eccLength;
	public $b1;

	public function __construct(array $dataCode, array $spec)
	{
		$this->count = 0;

		$ecccode = array_fill(0, $this->eccLength, 0);

		list($this->b1, $this->dataLength, $this->eccLength, $this->blocks) = $this->parseSpec($spec);

		$dl = $spec[1]; # rsDataCodes1
		$el = $spec[2]; # rsEccCodes1
		$rs = new QRrsItem(8, 0x11d, 0, 1, $el, 255 - $dl - $el);

		$blockNo = 0;
		$dataPos = 0;
		$eccPos = 0;

		for($i=0; $i < $spec[0]; $i++) { # rsBlockNum1

			$ecc = array_slice($ecccode,$eccPos);
			$data = array_slice($dataCode, $dataPos);
			$ecc2 = $rs->encode_rs_char($data, $ecc);

			$this->rsblocks[$blockNo] = ["dataLength" => $dl, "data" => $data, "ecc" => $ecc2];
			$ecccode = array_merge(array_slice($ecccode,0, $eccPos), $ecc);
			
			$dataPos += $dl;
			$eccPos += $el;
			$blockNo++;
		}

		if($spec[3] == 0){ # rsBlockNum2
			return;
		}

		for($i=0; $i < $spec[3]; $i++) { # rsBlockNum2

			$ecc = array_slice($ecccode,$eccPos);
			$ecc2 = $rs->encode_rs_char($data, $ecc);

			$this->rsblocks[$blockNo] = ["dataLength" => $dl, "data" => $data, "ecc" => $ecc2];
			$ecccode = array_merge(array_slice($ecccode,0, $eccPos), $ecc);

			$dataPos += $dl;
			$eccPos += $el;
			$blockNo++;
		}
	}

	private function parseSpec($spec){
		return [$spec[0], ($spec[0] * $spec[1]) + ($spec[3] * $spec[4]), ($spec[0] + $spec[3]) * $spec[2], $spec[0] + $spec[3]];
	}

	public function getCode()
	{
		$ret = 0;

		if($this->count < $this->dataLength) {
			$row = $this->count % $this->blocks;
			$col = $this->count / $this->blocks;
			if($col >= $this->rsblocks[0]["dataLength"]) {
				$row += $this->b1;
			}
			$ret = $this->rsblocks[$row]["data"][$col];
		} else if($this->count < $this->dataLength + $this->eccLength) {
			$row = ($this->count - $this->dataLength) % $this->blocks;
			$col = ($this->count - $this->dataLength) / $this->blocks;
			$ret = $this->rsblocks[$row]["ecc"][$col];
		} else {
			return 0;
		}
		$this->count++;

		return $ret;
	}
}

?>