<?php
/**
*	Class Image
*	
*	Class for Image-resizing, cropping, outputting, ...
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;


Class Image {
	private $filename = null;
	private $image = null;


	function __construct($filename) {
		$this->filename = $filename;
		switch ($this->getType()) {
			case IMAGETYPE_GIF:
				$this->image = imagecreatefromgif($filename);
				break;
			case IMAGETYPE_JPEG:
				$this->image = imagecreatefromjpeg($filename);
				break;
			case IMAGETYPE_PNG:
				$this->image = imagecreatefrompng($filename);
				break;
			
			default:
				echo "ERROR -> unsupported image-type";
				break;
		}
		
	}

	function getSize() {
		$width = imageSx($this->image);
		$height = imageSy($this->image);
		return Array($width, $height);
	}

	function getType() {
		$x = getimagesize($this->filename);
		return $x[2];
	}

	function show() {
		switch ($this->getType()) {
			case IMAGETYPE_GIF:
				return imagegif($this->image, null);
				break;
			case IMAGETYPE_JPEG:
				return imagejpeg($this->image, null, 100);
				break;
			case IMAGETYPE_PNG:
				return imagepng($this->image, null, 0);
				break;
			default:
				return imagejpeg($this->image, null, 100);
				break;
		}
	}

	function resize($new_width, $new_height) {
		list($orig_width, $orig_height) = $this->getSize();
		$image_resized = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($image_resized, $this->image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
		$this->image = $image_resized;
	}

	function resizeMax($max_width, $max_height) {
		list($orig_width, $orig_height) = $this->getSize();
		$ratio = round($orig_width/$orig_height,4);

		#echo "\norig_width: ".$orig_width. " orig_height: ".$orig_height;
		#echo "\nratio: ".$ratio;


		// first check/change the width
		if($orig_width > $max_width) {
			#echo "\nreducing width to ".$max_width;
			$new_height = $max_width*$ratio;
			$image_resized = imagecreatetruecolor($max_width, $new_height);
			imagecopyresampled($image_resized, $this->image, 0, 0, 0, 0, $max_width, $new_height, $orig_width, $orig_height);
			$this->image = $image_resized;
			// reset orig_height if it has changed
			$orig_width = $max_width;
			$orig_height = $new_height;
			#echo "\nnew height: ".$orig_height;
		}
		// then check/change the height
		if($orig_height > $max_height) {
			#echo "\nreducing height to ".$max_height;
			$new_width = $max_height*$ratio;
			#echo "\nnew width: ".$new_width;
			$image_resized = imagecreatetruecolor($new_width, $max_height);
			imagecopyresampled($image_resized, $this->image, 0, 0, 0, 0, $new_width, $max_height, $orig_width, $orig_height);
			$this->image = $image_resized;
		}
	}


	function crop($x, $y, $width, $height) {
		list($orig_width, $orig_height) = $this->getSize();
		// error-testing:
		if($x>$orig_width) return false;
		if($y>$orig_height) return false;
		
		if($x+$width>$orig_width) {
			$width = $orig_width-$x;
			$width = ($width<0) ? 10 : $width;
		}
		if($y+$height>$orig_height) {
			$height = $orig_height-$y;
			$height = ($height<0) ? 10 : $height;
		}
		$image_cropped = imagecreatetruecolor($width, $height);
		imagecopyresampled($image_cropped, $this->image, 0, 0, $x, $y, $width, $height, $width, $height);
		$this->image = $image_cropped;
	}

	function cropCenter($width, $height) {
		list($orig_width, $orig_height) = $this->getSize();
		
		if($width>$orig_width) {
			$width = $orig_width;
			$ratio = $width/$orig_width;
			$height = $height * $ratio;
		}

		if($height>$orig_height) {
			$height = $orig_height;
			$ratio = $height/$orig_height;
			$width = $width * $ratio;
		}

		$x=($orig_width-$width)/2;
		$y=($orig_height-$height)/2;
		$image_cropped = imagecreatetruecolor($width, $height);
		imagecopyresampled($image_cropped, $this->image, 0, 0, $x, $y, $width, $height, $width, $height);
		$this->image = $image_cropped;


	}

	function save($path, $filename, $type=0) {
		#echo "in image.php saving to: ".$path.$filename;
		switch ($type) {
			case IMAGETYPE_GIF:
				return imagegif($this->image, $path.DIRECTORY_SEPARATOR.$filename);
				break;
			case IMAGETYPE_JPEG:
				return imagejpeg($this->image, $path.DIRECTORY_SEPARATOR.$filename, 100);
				break;
			case IMAGETYPE_PNG:
				return imagepng($this->image, $path.DIRECTORY_SEPARATOR.$filename, 0);
				break;
			default:
				return imagejpeg($this->image, $path.DIRECTORY_SEPARATOR.$filename, 100);
				break;
		}
		
	}

	function getHeader() {
		switch ($this->getType()) {
			case IMAGETYPE_GIF:
				return 'image/gif';
				break;
			case IMAGETYPE_JPEG:
				return 'image/jpeg';
				break;
			case IMAGETYPE_PNG:
				return 'image/png';;
				break;
			default:
				return 'image/jpeg';
				break;
		}
	}

	function getImage() {
		return $this->image;
	}
}