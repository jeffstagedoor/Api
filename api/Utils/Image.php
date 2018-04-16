<?php
/**
 * file contains class Image
 */
namespace Jeff\Api\Utils;


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
Class Image {
	/** @var string filename of current image */
	private $filename = null;
	/** @var ImageRecource the image itself */
	private $image = null;

	/**
	 * The Constructor
	 *
	 * generates an ImageRecource of given filename. Uses `Image::getType()`.
	 * Will create a gif, jpg or png. Other filetypes throw an error
	 * @param string $filename filename of an image to work with
	 */
	public function __construct($filename) {
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

	/**
	 * returns the size of the image as array [width, height]
	 * @return array [width, height]
	 */
	public function getSize() {
		$width = imageSx($this->image);
		$height = imageSy($this->image);
		return Array($width, $height);
	}

	/**
	 * Uses {@see http://php.net/manual/en/function.getimagesize.php} to get the mime type of the image
	 * @return string type of the image: IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF,..
	 */
	public function getType() {
		$x = getimagesize($this->filename);
		return $x[2];
	}

	/**
	 * Returns current image
	 * @return ImageRecource
	 */
	public function getImage() {
		return $this->image;
	}

	/**
	 * Output image to the browser
	 * @return bool if successful
	 */
	public function show() {
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

	/**
	 * Resizes the image
	 * @param  int $new_width   the width to resize to
	 * @param  int $new_height  the height to resize to
	 * @return void
	 */
	public function resize($new_width, $new_height) {
		list($orig_width, $orig_height) = $this->getSize();
		$image_resized = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($image_resized, $this->image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
		$this->image = $image_resized;
	}

	/**
	 * Resizes the image to a given  max value with the ratio maintained
	 * @param  int $max_width   the max width to resize to
	 * @param  int $max_height  the max height to resize to
	 * @return this
	 */
	public function resizeMax($max_width, $max_height) {
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
		return $this;
	}

	/**
	 * Crops an image from given start coordinates (x, y) to given dimensions (width, height)
	 * @param  int $x
	 * @param  int $y
	 * @param  int $width
	 * @param  int $height
	 * @return this
	 */
	public function crop($x, $y, $width, $height) {
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
		return $this;
	}

	/**
	 * Crops an image to the center part by given dimensions (width, height)
	 * @param  int $width
	 * @param  int $height
	 * @return this
	 */
	public function cropCenter($width, $height) {
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
		return $this;
	}

	/**
	 * Saves the image to a given $path.$filename and converts to given type
	 * Maybe I should change that...!?
	 * @param  string  $path
	 * @param  string  $filename
	 * @param  integer $type     IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG,... 
	 *                           defaults to IMAGETYPE_JPEG
	 * @return the image as image, not as recource...?!
	 */
	public function save($path, $filename, $type=0) {
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

	/**
	 * Returns the appropriate header string for current image like 'image/gif', 'image/jpeg'. Defaults to jpeg if no matching type was found.
	 * @return string
	 */
	public function getHeader() {
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
}