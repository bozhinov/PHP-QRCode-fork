<?php

namespace QRCode;

class qrException extends \Exception
{
	public static function Std($text)
	{
		return new static(sprintf('QRCode: %s', $text));
	}
}
