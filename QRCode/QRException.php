<?php

namespace QRCode;

class qrException extends \Exception
{
	public static function InvalidInput($text)
	{
		return new static(sprintf('QRCode: %s', $text));
	}

	public static function EncoderError($text)
	{
		return new static(sprintf('QRCode: %s', $text));
	}
}
