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

use QRCode\QRException;
use QRCode\QRFrame;
use QRCode\QRImage;
use QRCode\QRTools;
use QRCode\QRInput;
use QRCode\QRInputItem;
use QRCode\QRMask;
use QRCode\QRrsItem;
use QRCode\QRSplit;

// QRMask
define('QR_N1', 3);
define('QR_N2', 3);
define('QR_N3', 40);
define('QR_N4', 10);

// QRinputItem
define('QR_STRUCTURE_HEADER_BITS', 20);

// Encoding modes
define('QR_MODE_NUL', -1);
define('QR_MODE_NUM', 0);
define('QR_MODE_AN', 1);
define('QR_MODE_8', 2);
define('QR_MODE_KANJI', 3);
define('QR_MODE_STRUCTURE', 4);

// Levels of error correction.
define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

define('QR_SPEC_VERSION_MAX', 40);
define('QR_SPEC_WIDTH_MAX', 177);
define('QR_CAP_WIDTH', 0);
define('QR_CAP_WORDS', 1);
define('QR_CAP_REMINDER', 2);
define('QR_CAP_EC', 3);

class QRcode {

	# QRencode
	private $casesensitive;
	private $eightbit;
	private $size;
	private $margin;
	#private $structured = 0; // not supported yet
	private $level;
	private $hint = QR_MODE_8;
	
	function __construct(int $level = QR_ECLEVEL_L, int $size = 3, int $margin = 4, bool $eightbit = false, bool $casesensitive = true)
	{
		$this->size = $size;
		$this->margin = $margin;
		$this->eightbit = $eightbit;
		$this->casesensitive = $casesensitive;
		
		if (!in_array($level,[0,1,2,3])){
			throw QRException::Std('unknown error correction level');
		}
		$this->level = $level;
	}

	private function encodeString8bit($string)
	{
		$input = new QRinput(1, $this->level);

		$input->append(QR_MODE_8, strlen($string), $string);

		return $input->encodeMask();
	}

	private function encodeString($string)
	{
		if($this->hint != QR_MODE_8 && $this->hint != QR_MODE_KANJI) {
			throw QRException::Std('bad hint');
		}

		return (new QRsplit($this->casesensitive, $this->hint, 1, $this->level))->splitString($string);
	}
	
	private function binarize($frame)
	{
		$len = count($frame);
		foreach ($frame as &$frameLine) {
			
			for($i=0; $i<$len; $i++) {
				$frameLine[$i] = (ord($frameLine[$i])&1)?'1':'0';
			}
		}
		
		return $frame;
	}
	
	public function jpg(string $text, $outfile)
	{
		$encoded = $this->raw($text);

		$tab = $this->binarize($encoded);

		$maxSize = count($tab)+2*$this->margin;

		$pixelPerPoint = min($this->size, $maxSize);

		(new QRimage($tab, $pixelPerPoint, $this->margin))->jpg($outfile, 90);
	}

	public function png(string $text, $outfile)
	{
		$encoded = $this->raw($text);

		$tab = $this->binarize($encoded);

		$maxSize = count($tab)+2*$this->margin;

		$pixelPerPoint = min($this->size, $maxSize);

		(new QRimage($tab, $pixelPerPoint, $this->margin))->png($outfile);
	}

	public function raw(string $text)
	{
		if($this->eightbit) {
			$encoded = $this->encodeString8bit($text);
		} else {
			$encoded = $this->encodeString($text);
		}

		return $encoded;
	}
}

?>