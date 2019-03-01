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

	private $mm;		// Bits per symbol 
	private $nn;		// Symbols per block (= (1<<mm)-1) 
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

		$this->rsInit($el, 255 - $dl - $el);

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

	private function modnn($x)
	{
		while ($x >= $this->nn) {
			$x -= $this->nn;
			$x = ($x >> $this->mm) + ($x & $this->nn);
		}

		return $x;
	}

	private function rsInit(int $nroots, int $pad)
	{
		// Common code for intializing a Reed-Solomon control block (char or int symbols)
		// Copyright 2004 Phil Karn, KA9Q
		// May be used under the terms of the GNU Lesser General Public License (LGPL)
		$symsize = 8;
		$gfpoly	= 0x11d;
		$fcr = 0;
		$prim = 1;

		// Check parameter ranges
		if($nroots < 0 || $nroots >= (1<<$symsize)){
			throw QRException::Std("Can't have more roots than symbol values!");
		}
		if($pad < 0 || $pad >= ((1<<$symsize) -1 - $nroots)){
			throw QRException::Std('Too much padding');
		}

		$this->parity = array_fill(0, $nroots, 0);

		$this->mm = $symsize;
		$this->nn = (1<<$symsize)-1;
		$this->pad = $pad;

		$this->alpha_to = array_fill(0, $this->nn+1, 0);
		$this->index_of = $this->alpha_to;

		// Generate Galois field lookup tables
		$this->index_of[0] = $this->nn; // log(zero) = -inf
		$this->alpha_to[$this->nn] = 0; // alpha**-inf = 0
		$sr = 1;

		for($i = 0; $i < $this->nn; $i++) {
			$this->index_of[$sr] = $i;
			$this->alpha_to[$i] = $sr;
			$sr <<= 1;
			if($sr & (1<<$symsize)) {
				$sr ^= $gfpoly;
			}
			$sr &= $this->nn;
		}

		if($sr != 1){
			throw QRException::Std('field generator polynomial is not primitive!');
		}

		/* Form RS code generator polynomial from its roots */
		$this->genpoly = array_fill(0, $nroots+1, 0);
		$this->nroots = $nroots;
		$this->genpoly[0] = 1;

		$root = $fcr * $prim;

		for ($i = 0; $i < $nroots; $i++) {

			$this->genpoly[$i+1] = 1;

			// Multiply rs->genpoly[] by  @**(root + x)
			for ($j = $i; $j > 0; $j--) {
				if ($this->genpoly[$j] != 0) {
					$this->genpoly[$j] = $this->genpoly[$j-1] ^ $this->alpha_to[$this->modnn($this->index_of[$this->genpoly[$j]] + $root)];
				} else {
					$this->genpoly[$j] = $this->genpoly[$j-1];
				}
			}
			// rs->genpoly[0] can never be zero
			$this->genpoly[0] = $this->alpha_to[$this->modnn($this->index_of[$this->genpoly[0]] + $root)];

			$root += $prim;
		}

		// convert rs->genpoly[] to index form for quicker encoding
		for ($i = 0; $i <= $nroots; $i++){
			$this->genpoly[$i] = $this->index_of[$this->genpoly[$i]];
		}
	}

	private function encode_rs_char($data)
	{
		$parity = $this->parity;

		for($i = 0; $i < ($this->nn - $this->nroots - $this->pad); $i++) {

			$feedback = $this->index_of[$data[$i] ^ $parity[0]];
			if($feedback != $this->nn) {
				// feedback term is non-zero
				for($j=1; $j < $this->nroots; $j++) {
					$parity[$j] ^= $this->alpha_to[$this->modnn($feedback + $this->genpoly[$this->nroots-$j])];
				}
			}

			// Shift 
			array_shift($parity);
			if($feedback != $this->nn) {
				array_push($parity, $this->alpha_to[$this->modnn($feedback + $this->genpoly[0])]);
			} else {
				array_push($parity, 0);
			}
		}

		return $parity;
	}
}
