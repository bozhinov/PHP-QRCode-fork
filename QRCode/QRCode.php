<?php
/*
 * PHP QR Code
 * Last update - 31.12.2019
 */

namespace QRCode;

// Encoding modes
define('QR_MODE_NUM', 0);
define('QR_MODE_AN', 1);
define('QR_MODE_8', 2);
define('QR_MODE_KANJI', 3);

// Levels of error correction.
define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

use QRCode\Encoder\Input;

class QRCode {

	private $options;
	private $renderer;

	function __construct(array $opts = [])
	{
		$this->setColor('color', 0, $opts);
		$this->setColor('bgColor', 255, $opts);

		$this->options['level'] = (isset($opts['level'])) ? $this->option_in_range($opts['level'], 0, 3) : 0;
		$this->options['size'] = (isset($opts['size'])) ? $this->option_in_range($opts['size'], 0, 20) : 3;
		$this->options['margin'] = (isset($opts['margin'])) ? $this->option_in_range($opts['margin'], 0, 20) : 4;
	}

	public function config(array $opts)
	{
		$this->__construct($opts);
	}

	private function setColor($value, $default, $opts)
	{
		if (!isset($opts[$value])) {
			$this->options[$value] = new qrColor($default);
		} else {
			if (!($opts[$value] instanceof qrColor)) {
				throw azException::InvalidInput("Invalid value for \"$value\". Expected an azColor object.");
			}
			$this->options[$value] = $opts[$value];
		}
	}

	private function option_in_range($value, int $start, int $end)
	{
		if (!is_numeric($value) || $value < $start || $value > $end) {
			throw azException::InvalidInput("Invalid value. Expected an integer between $start and $end.");
		}

		return $value;
	}

	public function encode(string $text, int $hint = -1)
	{
		if($text == '\0' || $text == '') {
			throw qrException::Std('empty string!');
		}

		if (!in_array($hint,[-1,0,1,2,3])){
			throw qrException::Std('unknown hint');
		}

		$encoded = (new Input($this->options['level']))->encodeString($text, $hint);
		$this->renderer = new Renderer($encoded, $this->options);

		return $this;
	}

	public function fromArray(array $encoded)
	{
		$this->renderer = new Renderer($encoded, $this->options);
	}

	public function toArray()
	{
		return $this->renderer->toArray();
	}

	public function toBase64()
	{
		return $this->renderer->toBase64();
	}

	public function toASCII()
	{
		return $this->renderer->toASCII();
	}

	public function forPChart(\pChart\pDraw $MyPicture, $X = 0, $Y = 0)
	{
		$this->renderer->createImage($MyPicture->gettheImage(), $X, $Y);
	}

	public function toFile(string $filename, int $quality = 90, bool $forWeb = false)
	{
		$ext = strtoupper(substr($filename, -3));
		($forWeb) AND $filename = null;

		$this->renderer->createImage();

		switch($ext)
		{
			case "PNG":
				$this->renderer->toPNG($filename);
				break;
			case "JPG":
				$this->renderer->toJPG($filename, $quality);
				break;
			case "SVG":
				$this->renderer->toSVG($filename);
				break;
			default:
				throw qrException::Std('file extension unsupported!');
		}
	}

	public function forWeb(string $ext, int $quality = 90)
	{
		$this->toFile($ext, $quality, true);
	}
}
