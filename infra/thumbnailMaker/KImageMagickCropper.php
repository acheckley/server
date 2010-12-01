<?php
class KImageMagickCropper extends KBaseCropper
{
	const RESIZE = 1;
	const RESIZE_WITH_PADDING = 2;
	const CROP = 3;
	const CROP_FROM_TOP = 4;
	
	protected $cmdPath;
	protected $srcWidth;
	protected $srcHeight;

	protected static $imageExtByType = array(
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_BMP => 'png',
		IMAGETYPE_JPEG => 'jpg',
	);
	
	/**
	 * @param string $filePath
	 * @param string $cmdPath
	 */
	public function __construct($srcPath, $targetPath, $cmdPath = 'convert', $forceJpeg = false)
	{
		$this->cmdPath = $cmdPath;
		
		list($this->srcWidth, $this->srcHeight, $type, $attr) = getimagesize($srcPath);

		// forceJpeg var is not used.
		// there is no return of the new target file (as string) after the extension change
//		if ($type == IMAGETYPE_BMP) // convert bmp to jpeg
//			$type = IMAGETYPE_JPEG;
//		
//		$ext = '';
//		if ($this->forceJpeg)
//			$ext = 'jpg';
//		elseif(isset(self::$imageExtByType[$type]))
//			$ext = self::$imageExtByType[$type];
//			
//		$targetPath = kFile::replaceExt($targetPath, $ext);
			
		parent::__construct($srcPath, $targetPath);
	}
	
	protected function getCommand($quality, $cropType, $width = 0, $height = 0, $cropX = 0, $cropY = 0, $cropWidth = 0, $cropHeight = 0, $bgcolor = 0xffffff)
	{
		$attributes = array();

		$exifData = @exif_read_data($this->srcPath);
		$orientation = isset($exifData["Orientation"]) ? $exifData["Orientation"] : 1;
		
		switch($orientation)
		{
			case 1: // nothing
			break;
		
			case 2: // horizontal flip
				$attributes[] = "-flop";
			break;
									
			case 3: // 180 rotate left
				$attributes[] = "-rotate 180";
			break;
						
			case 4: // vertical flip
				$attributes[] = "-flip";
			break;
					
			case 5: // vertical flip + 90 rotate right
				$attributes[] = "-transpose";
			break;
					
			case 6: // 90 rotate right
				$attributes[] = "-rotate 90";
			break;
					
			case 7: // horizontal flip + 90 rotate right
				$attributes[] = "-transverse";
			break;
					
			case 8:    // 90 rotate left
				$attributes[] = "-rotate 270";
			break;
		}

		if($quality)
			$attributes[] = "-quality $quality";
			
		// pre-crop
		if($cropX || $cropY || $cropWidth || $cropHeight)
		{
			if($cropType == self::CROP_FROM_TOP)
				$cropY = 0;
				
			$geometrics = "{$cropWidth}x{$cropHeight}";
			$geometrics .= ($cropX < 0 ? $cropX : "+$cropX");
			$geometrics .= ($cropY < 0 ? $cropY : "+$cropY");
			
			$attributes[] = "-crop $geometrics";
		}
		
		// crop or resize
		if($width || $height)
		{
			switch($cropType)
			{
				case self::RESIZE:
					$w = $width ? $width : '';
					$h = $height ? $height : '';
					$attributes[] = "-resize {$w}x{$h}";
					break;
					
				case self::RESIZE_WITH_PADDING:
					if($width && $height)
					{
						$borderWidth = 0;
						$borderHeight = 0;
						
						if($width < $height)
						{
							$w = $width;
							$h = ceil($this->srcHeight * ($width / $this->srcWidth));
							$borderHeight = ceil(($height - $h) / 2);
						}
						else 
						{
							$h = $height;
							$w = ceil($this->srcWidth * ($height / $this->srcHeight));
							$borderWidth = ceil(($width - $w) / 2);
						}
						
						$bgcolor = dechex($bgcolor);
						$attributes[] = "-bordercolor #$bgcolor";
						$attributes[] = "-resize {$w}x{$h}";
						$attributes[] = "-border {$borderWidth}x{$borderHeight}";
					}
					else 
					{
						$w = $width ? $width : '';
						$h = $height ? $height : '';
						$attributes[] = "-resize {$w}x{$h}";
					}
					break;
					
				case self::CROP:
				case self::CROP_FROM_TOP:
					$w = $width ? $width : $height;
					$h = $height ? $height : $width;
					
					$resizeWidth = '';
					$resizeHeight = '';
					
					if($width > $height)
						$resizeWidth = $width;
					else
						$resizeHeight = $height;
						
					if($cropType == self::CROP)
						$attributes[] = "-gravity Center";
					elseif($cropType == self::CROP_FROM_TOP)
						$attributes[] = "-gravity North";
						
					$attributes[] = "-resize {$resizeWidth}x{$resizeHeight}";
					$attributes[] = "-crop {$w}x{$h}+0+0";
					break;
			}
		}

		if(!count($attributes))
			return null;
			
		$options = implode(' ', $attributes);
		return "\"$this->cmdPath\" $options \"$this->srcPath\" \"$this->targetPath\"";
	}
}
