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

class QRrsItem {

	#private $mm;		// Bits per symbol = 8
	#private $nn;		// Symbols per block (= (1<<mm)-1)  = 255
	private $alpha_to;	// log lookup table 
	private $index_of;	// Antilog lookup table 
	private $genpoly;	// Generator polynomial 
	private $nroots;	// Number of generator roots = number of parity symbols 
	private $pad;		// Padding bytes in shortened block 
	private $parity;

	// RawCode
	private $blocks;
	private $count;
	private $b1;
	private $rsblocks = [];
	private $dataLength;
	private $eccLength;

	function __construct(array $dataCode, int $dataLength, int $eccLength, array $spec)
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

		$this->nroots = $spec[2];

		// Check parameter ranges
		if($this->nroots >= 256){
			throw QRException::Std("Can't have more roots than symbol values!");
		}

		$this->pad = 255 - $dl - $el;

		if($this->pad < 1){
			throw QRException::Std('Too much padding');
		}

		$this->rsInit();

		for($i = 0; $i < $spec[0]; $i++) { # rsBlockNum1

			$data = array_slice($dataCode, $dataPos);

			$this->rsblocks[$blockNo] = ["dataLength" => $dl, "data" => $data, "ecc" => $this->encode_rs_char($data)];

			$dataPos += $dl;
			$blockNo++;
		}

		if($spec[3] != 0) { # rsBlockNum2
			for($i = 0; $i < $spec[3]; $i++) {

				$this->rsblocks[$blockNo] = ["dataLength" => $dl, "data" => $data, "ecc" => $this->encode_rs_char($data)];

				$dataPos += $dl;
				$blockNo++;
			}
		}
	}

	public function getCode()
	{
		if($this->count < $this->dataLength) {
			$row = $this->count % $this->blocks;
			$col = $this->count / $this->blocks;
			if($col >= $this->rsblocks[0]['dataLength']) {
				$row += $this->b1;
			}
			$ret = $this->rsblocks[$row]['data'][$col];
		} elseif($this->count < $this->dataLength + $this->eccLength) {
			$row = ($this->count - $this->dataLength) % $this->blocks;
			$col = ($this->count - $this->dataLength) / $this->blocks;
			$ret = $this->rsblocks[$row]['ecc'][$col];
		} else {
			throw QRException::Std('Can not get code');
		}
		$this->count++;

		return $ret;
	}

	private function rsInit()
	{
		// Common code for intializing a Reed-Solomon control block (char or int symbols)
		// Copyright 2004 Phil Karn, KA9Q
		// May be used under the terms of the GNU Lesser General Public License (LGPL)

		$this->parity = array_fill(0, $this->nroots, 0);
		$this->genpoly = $this->parity;
		array_unshift($this->genpoly,1);
		$this->alpha_to = array_fill(0, 256, 0);
		$this->index_of = $this->alpha_to;

		// Generate Galois field lookup tables
		$this->index_of[0] = 255; // log(zero) = -inf
		$sr = 1;

		for($i = 0; $i < 255; $i++) {
			$this->index_of[$sr] = $i;
			$this->alpha_to[$i] = $sr;
			$sr <<= 1;
			if($sr & 256) {
				$sr ^= 285; # gfpoly
			}
			$sr &= 255;
		}

		if($sr != 1){
			throw QRException::Std('field generator polynomial is not primitive!');
		}

		/* Form RS code generator polynomial from its roots */
		for ($i = 0; $i < $this->nroots; $i++) {

			$this->genpoly[$i+1] = 1;

			// Multiply rs->genpoly[] by  @**(root + x)
			for ($j = $i; $j > 0; $j--) {
				if ($this->genpoly[$j] != 0) {
					$this->genpoly[$j] = $this->genpoly[$j-1] ^ $this->alpha_to[($this->index_of[$this->genpoly[$j]] + $i) % 255];
				} else {
					$this->genpoly[$j] = $this->genpoly[$j-1];
				}
			}
			// rs->genpoly[0] can never be zero
			$this->genpoly[0] = $this->alpha_to[($this->index_of[$this->genpoly[0]] + $i) % 255];
		}

		// convert rs->genpoly[] to index form for quicker encoding
		for ($i = 0; $i <= $this->nroots; $i++){
			$this->genpoly[$i] = $this->index_of[$this->genpoly[$i]];
		}
	}

	private function encode_rs_char($data)
	{
		$parity = $this->parity;

		for($i = 0; $i < (255 - $this->nroots - $this->pad); $i++) {

			$feedback = $this->index_of[$data[$i] ^ $parity[0]];
			if($feedback != 255) {
				// feedback term is non-zero
				for($j=1; $j < $this->nroots; $j++) {
					$parity[$j] ^= $this->alpha_to[($feedback + $this->genpoly[$this->nroots-$j]) % 255];
				}
			}

			// Shift 
			array_shift($parity);
			if($feedback != 255) {
				$parity[] = $this->alpha_to[($feedback + $this->genpoly[0]) % 255];
			} else {
				$parity[] = 0;
			}
		}

		return $parity;
	}
}

?>