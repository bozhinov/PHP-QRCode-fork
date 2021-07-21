<?php 

namespace QRCode;

// keeping the alha on purpose

class qrColor
{
	private $R;
	private $G;
	private $B;
	private $Alpha;

	function __construct(int $R = 0, int $G = 0, int $B = 0, float $Alpha = 100)
	{
		switch (func_num_args()){
			case 1:
				$G = $R;
				$B = $R;
				break;
			case 4:
				($G < 0) 	AND $G = 0;
				($G > 255)	AND $G = 255;
				($B < 0) 	AND $B = 0;
				($B > 255)	AND $B = 255;
				($Alpha < 0)	AND $Alpha = 0;
				($Alpha > 100)	AND $Alpha = 100;
				break;
		}

		$this->R = $R;
		$this->G = $G;
		$this->B = $B;
		$this->Alpha = $Alpha;
	}

	public function get()
	{
		return [$this->R, $this->G, $this->B, $this->Alpha];
	}

	public function toHex()
	{
		$R = dechex($this->R);
		$G = dechex($this->G);
		$B = dechex($this->B);

		return  "#".(strlen($R) < 2 ? '0' : '').$R.(strlen($G) < 2 ? '0' : '').$G.(strlen($B) < 2 ? '0' : '').$B;
	}
}
