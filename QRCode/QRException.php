<?php

namespace QRCode;

class QRException extends \Exception
{
	public static function Std($text)
	{
		return new static(sprintf('QRCode: %s', $text));
	}
}

?>