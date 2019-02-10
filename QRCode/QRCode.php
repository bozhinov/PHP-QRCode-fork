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
use QRCode\FrameFiller;
use QRCode\QRRawCode;
use QRCode\QRImage;
use QRCode\QRInput;
use QRCode\QRInputItem;
use QRCode\QRMask;
use QRCode\QRrsItem;
use QRCode\QRSpec;
use QRCode\QRSplit;

// QRMask
define('QR_N1', 3);
define('QR_N2', 3);
define('QR_N3', 40);
define('QR_N4', 10);

// QRImage
define('QR_IMAGE', true);

// QRinputItem
define('QR_STRUCTURE_HEADER_BITS', 20);
//define('MAX_STRUCTURED_SYMBOLS', 16); # UNUSED

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

// Supported output formats
define('QR_FORMAT_TEXT', 0);
define('QR_FORMAT_PNG', 1);

define('QR_FIND_BEST_MASK', true); // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
define('QR_FIND_FROM_RANDOM', false); // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
define('QR_DEFAULT_MASK', 2); // when QR_FIND_BEST_MASK === false
define('QR_PNG_MAXIMUM_SIZE', 1024);// maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images

define('QR_SPEC_VERSION_MAX', 40);
define('QR_SPEC_WIDTH_MAX', 177);
define('QR_CAP_WIDTH', 0);
define('QR_CAP_WORDS', 1);
define('QR_CAP_REMINDER', 2);
define('QR_CAP_EC', 3);

class QRcode {

	private $version;
	private $width;
	private $data;
	
	# QRencode
	private $casesensitive = true;
	private $eightbit = false;
	private $size = 3;
	private $margin = 4;
	private $structured = 0; // not supported yet
	private $level = QR_ECLEVEL_L;
	private $hint = QR_MODE_8;
	
	function __construct(int $level = QR_ECLEVEL_L, int $size = 3, int $margin = 4, bool $eightbit = false, bool $casesensitive = true)
	{
		$this->size = $size;
		$this->margin = $margin;
		$this->eightbit = $eightbit;
		$this->casesensitive = $casesensitive;
		
		if (!in_array($level,[0,1,2,3,4])){
			throw QRException::Std('unknown error correction level');
		}
		$this->level = $level;
	}

	private function encodeString8bit($string)
	{
		if(is_null($string)) {
			throw QRException::Std('empty string!');
		}

		$input = new QRinput($this->version, $this->level);

		$input->append(QR_MODE_8, strlen($string), str_split($string));

		return $input->encodeMask(-1);
	}

	private function encodeString($string)
	{
		if($this->hint != QR_MODE_8 && $this->hint != QR_MODE_KANJI) {
			throw QRException::Std('bad hint');
		}

		$input = new QRinput($this->version, $this->level);

		# appends to input
		(new QRsplit($string, $input, $this->hint))->splitString($this->casesensitive);

		# ["version" => $version, "width" => $width, "data" => $masked]
		return $input->encodeMask(-1);
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
	
	public function encode($intext, $outfile = false) 
	{
		if($this->eightbit) {
			$encoded = $this->encodeString8bit($intext);
		} else {
			$encoded = $this->encodeString($intext);
		}
		
		$this->version = $encoded['version'];
		$this->width = $encoded['width'];
		$this->data = $encoded['data'];

		if ($outfile!== false) {
			file_put_contents($outfile, join("\n", $this->binarize($this->data)));
		} else {
			return $this->binarize($this->data);
		}
	}

	public function png($text, $outfile = false, $saveandprint = false)
	{
		$tab = $this->encode($text);

		$maxSize = (int)(QR_PNG_MAXIMUM_SIZE / (count($tab)+2*$this->margin));

		$pixelPerPoint = min(max(1, $this->size), $maxSize);

		(new QRimage($tab, $pixelPerPoint, $this->margin))->png($outfile, $saveandprint);
	}

	public function text($text, $outfile = false)
	{
		return $this->encode($text, $outfile);
	}

	public function raw($text, $outfile = false)
	{
		if($this->eightbit) {
			$encoded = $this->encodeString8bit($text);
		} else {
			$encoded = $this->encodeString($text);
		}
		
		$this->version = $encoded['version'];
		$this->width = $encoded['width'];
		$this->data = $encoded['data'];
		
		return $this->data;
	}
}

?>