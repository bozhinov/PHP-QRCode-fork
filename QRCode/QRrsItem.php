<?php

namespace QRCode;

class QRrsItem {

	public $mm;                  // Bits per symbol 
	public $nn;                  // Symbols per block (= (1<<mm)-1) 
	public $alpha_to = [];  // log lookup table 
	public $index_of = [];  // Antilog lookup table 
	public $genpoly = [];   // Generator polynomial 
	public $nroots;              // Number of generator roots = number of parity symbols 
	public $fcr;                 // First consecutive root, index form 
	public $prim;                // Primitive element, index form 
	public $iprim;               // prim-th root of 1, index form 
	public $pad;                 // Padding bytes in shortened block 
	public $gfpoly;

	public function modnn($x)
	{
		while ($x >= $this->nn) {
			$x -= $this->nn;
			$x = ($x >> $this->mm) + ($x & $this->nn);
		}

		return $x;
	}
	
	function __construct($symsize, $gfpoly, $fcr, $prim, $nroots, $pad)
	{
		// Common code for intializing a Reed-Solomon control block (char or int symbols)
		// Copyright 2004 Phil Karn, KA9Q
		// May be used under the terms of the GNU Lesser General Public License (LGPL)
		
		// Check parameter ranges
		if($symsize < 0 || $symsize > 8){
			throw QRException::Std('bad range');
		}
		if($fcr < 0 || $fcr >= (1<<$symsize)){
			throw QRException::Std('bad range');
		}
		if($prim <= 0 || $prim >= (1<<$symsize)){
			throw QRException::Std('bad range');
		}
		if($nroots < 0 || $nroots >= (1<<$symsize)){
			throw QRException::Std("Can't have more roots than symbol values!");
		}
		if($pad < 0 || $pad >= ((1<<$symsize) -1 - $nroots)){
			throw QRException::Std('Too much padding');
		}

		$this->mm = $symsize;
		$this->nn = (1<<$symsize)-1;
		$this->pad = $pad;

		$this->alpha_to = array_fill(0, $this->nn+1, 0);
		$this->index_of = array_fill(0, $this->nn+1, 0);

		// PHP style macro replacement ;)
		$NN =& $this->nn;
		$A0 =& $NN;

		// Generate Galois field lookup tables
		$this->index_of[0] = $A0; // log(zero) = -inf
		$this->alpha_to[$A0] = 0; // alpha**-inf = 0
		$sr = 1;

		for($i=0; $i<$this->nn; $i++) {
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

		$this->fcr = $fcr;
		$this->prim = $prim;
		$this->nroots = $nroots;
		$this->gfpoly = $gfpoly;

		/* Find prim-th root of 1, used in decoding */
		for($iprim=1;($iprim % $prim) != 0;$iprim += $this->nn); // intentional empty-body loop!

		$this->iprim = (int)($iprim / $prim);
		$this->genpoly[0] = 1;

		for ($i = 0,$root=$fcr*$prim; $i < $nroots; $i++, $root += $prim) {
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
		}
		
		// convert rs->genpoly[] to index form for quicker encoding
		for ($i = 0; $i <= $nroots; $i++){
			$this->genpoly[$i] = $this->index_of[$this->genpoly[$i]];
		}
	}

	public function encode_rs_char($data, $parity)
	{
		$A0 = $this->nn;

		$parity = array_fill(0, $this->nroots, 0);

		for($i=0; $i< ($this->nn - $this->nroots-$this->pad); $i++) {

			$feedback = $this->index_of[$data[$i] ^ $parity[0]];
			if($feedback != $A0) {
				// feedback term is non-zero

				// This line is unnecessary when GENPOLY[NROOTS] is unity, as it must
				// always be for the polynomials constructed by init_rs()
				$feedback = $this->modnn($this->nn - $this->genpoly[$this->nroots] + $feedback);

				for($j=1;$j<$this->nroots;$j++) {
					$parity[$j] ^= $this->alpha_to[$this->modnn($feedback + $this->genpoly[$this->nroots-$j])];
				}
			}

			// Shift 
			array_shift($parity);
			if($feedback != $A0) {
				array_push($parity, $this->alpha_to[$this->modnn($feedback + $this->genpoly[0])]);
			} else {
				array_push($parity, 0);
			}
		}

		return $parity;
	}
}
