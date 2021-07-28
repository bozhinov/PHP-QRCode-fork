<?php

namespace QRCode;

class Renderer
{
	private $target_image;
	private $h;
	private $encoded;
	private $options;

	function __construct($encoded, $opts)
	{
		$this->encoded = $encoded;
		$this->options = $opts;
	}

	public function createImage($img_resource = NULL, $startX = 0, $startY = 0)
	{
		$h = count($this->encoded);
		$imgH = $h + 2 * $this->options['margin'];
		$scale = min($this->options['size'], $imgH);
		$target_h = $imgH * $scale;
		$this->h = $target_h;

		if (is_null($img_resource)){
			$this->target_image = imagecreate($target_h, $target_h);
		} else {
			$this->target_image = $img_resource;
		}

		// Extract options
		list($R, $G, $B) = $this->options['bgColor']->get();
		$bgColorAlloc = imagecolorallocate($this->target_image, $R, $G, $B);
		list($R, $G, $B) = $this->options['color']->get();
		$colorAlloc = imagecolorallocate($this->target_image, $R, $G, $B);

		// Draw the background
		imagefilledrectangle($this->target_image, $startX, $startY, $startX + $target_h, $startY + $target_h, $bgColorAlloc);

		// Render the barcode
		for($y = 0; $y < $h; $y++) {
			for($x = 0; $x < $h; $x++) {
				if ($this->encoded[$y][$x] & 1) {
					imagefilledrectangle(
						$this->target_image,
						($x * $scale) + $this->options['margin'] * $scale + $startX,
						($y * $scale) + $this->options['margin'] * $scale + $startY,
						(($x + 1) * $scale - 1) + $this->options['margin'] * $scale + $startX,
						(($y + 1) * $scale - 1) + $this->options['margin'] * $scale + $startY,
						$colorAlloc
					);
				}
			}
		}
	}

	public function toPNG($filename)
	{
		if(is_null($filename)) {
			header("Content-type: image/png");
		}
		imagepng($this->target_image, $filename);
	}

	public function toJPG($filename, $quality)
	{
		if(is_null($filename)) {
			header("Content-type: image/jpeg");
		}
		imagejpeg($this->target_image, $filename, $quality);
	}

	public function toSVG($filename)
	{
		$content = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="'.$this->h.'px" height="'.$this->h.'px" viewBox="0 0 '.$this->h.' '.$this->h.'" enable-background="new 0 0 '.$this->h.' '.$this->h.'" xml:space="preserve">
<image id="image0" width="'.$this->h.'" height="'.$this->h.'" x="0" y="0" href="data:image/png;base64,'.$this->toBase64().'" />
</svg>';

		if(is_null($filename)) {
			header("Content-type: image/svg+xml");
			return $content;
		} else {
			file_put_contents($filename, $content);
		}
	}

	public function toBase64()
	{
		ob_start();
		imagePng($this->target_image);
		$imagedata = ob_get_contents();
		ob_end_clean();

		return base64_encode($imagedata);
	}

	public function toASCII()
	{
		$h = count($this->encoded);
		$ascii = "";

		for($y = 0; $y < $h; $y++) {
			for($x = 0; $x < $h; $x++) {
				if ($this->encoded[$y][$x]&1) {
					$ascii .= chr(219).chr(219);
				} else {
					$ascii .= "  ";
				}
			}
			$ascii .= "\r\n";
		}

		return $ascii;
	}

	public function toArray()
	{
		return $this->encoded;
	}
}