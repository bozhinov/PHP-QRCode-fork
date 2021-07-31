<?php
/*
 * PHP QR Code
 * Last update - 31.07.2021
 */

namespace QRCode;

use QRCode\Encoder\Encoder;

class QRCode {

	private $options;
	private $renderer;

	function __construct(array $opts = [])
	{
		$this->setColor('color', 0, $opts);
		$this->setColor('bgColor', 255, $opts);

		$this->options['level'] = 0;
		if (isset($opts['level'])){
			$this->options['level'] = $this->return_key_if_found(strtoupper($opts['level']), ["L", "M", "Q", "H"]);
		}
		$this->options['size'] = (isset($opts['size'])) ? $this->option_in_range($opts['size'], 0, 20) : 3;
		$this->options['margin'] = (isset($opts['margin'])) ? $this->option_in_range($opts['margin'], 0, 20) : 4;
	}

	public function config(array $opts)
	{
		$this->__construct($opts);
	}

	private function return_key_if_found(string $val, array $possibilities)
	{
		$ret = array_search($val, $possibilities);
		if ($ret === FALSE){
			throw qrException::InvalidInput("Invalid value specified.");
		}

		return $ret;
	}

	private function setColor($value, $default, $opts)
	{
		if (!isset($opts[$value])) {
			$this->options[$value] = new qrColor($default);
		} else {
			if (!($opts[$value] instanceof qrColor)) {
				throw qrException::InvalidInput("Invalid value for \"$value\". Expected an azColor object.");
			}
			$this->options[$value] = $opts[$value];
		}
	}

	private function option_in_range($value, int $start, int $end)
	{
		if (!is_numeric($value) || $value < $start || $value > $end) {
			throw qrException::InvalidInput("Invalid value. Expected an integer between $start and $end.");
		}

		return $value;
	}

	public function encode(string $text, string $hint = "undefined")
	{
		if($text == '\0' || $text == '') {
			throw qrException::InvalidInput('empty string!');
		}

		if ($hint == "undefined"){
			$hint = -1;
		} else {
			$hint = $this->return_key_if_found(strtolower($hint), ["numeric", "alphanumeric", "byte", "kanji"]);
		}

		$encoded = (new Encoder($this->options['level']))->encodeString($text, $hint);
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
				throw qrException::InvalidInput('file extension unsupported!');
		}
	}

	public function forWeb(string $ext, int $quality = 90)
	{
		$this->toFile($ext, $quality, true);
	}
}
