<?php

/**
 * This class can rotate, resize, crop or strech or convert to grayscale an image.
 * Suported images types are: jpg, jpeg, gif, webp and png.
 * 
 * Inputs: 
 * @var $sourcePath 		- path to original image
 * @var $whereToPlace 	- path to new, resized image.  (default is original source file - if this param is not set, original will be replaced with new resized image)
 * @var $imw 			- width of new resized image.  (default: 200)
 * @var $imh				- height of new resized image. (default: 150)
 * @var $mode			- mode (normal, crop, strech)
 * 
 * @author Aleksandar Todorovic<aleksandar.todorovic.xyz@gmail.com>
 * @created 2018.02.14.
 * 
 **/
class Imager
{
    private $messages = [];
    private $allowedExtexsions = ['jpg', 'jpeg', 'gif', 'png', 'webp'];
    private $allowedMIMETypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    // Demand extension to fit mime type - double check.
    private $extMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'png' => 'image/png', 'webp' => 'image/webp'];

    private const SQUARE = 0;
    private const LANDSCAPE = 1;
    private const PORTRAIT = 2;

    /**
     * Resize image.
     * @param string $sourcePath
     * @param string $whereToPlace
     * @param int $imw
     * @param int $imh
     * @param string $mode
     * @param int $rotate
     * @return bool
     * 
     * Tips:
     * 1. After upload original image from temp move to quarantine and change original file name (with no execution permission)
     * 2. Than use image from quarantine for rsize to size you need and put with random generated name (crypt) to desired dir.
     *     (to keep track, record in database original filename and new generated name)
     * 3. repeat step 2 using desired size to make thumb (in dir dedicated for thumbs sith same name as image in setp 2.)
     * 
     */
    public function resize(string $sourcePath = "", string $whereToPlace = "", int $imw = 200, int $imh = 150, string $mode = "normal", int $rotate = 0): bool
    {
        if (!$this->validate($sourcePath)) {
            return false;
        }

        if ($rotate < 0 || $rotate > 359) {
            $this->messages['errors'][] = 'Invalid rotation angle.';
            return false;
        }

        // if sourcePath not sent go back
        if (trim($sourcePath) === "") {
            $this->messages['errors'][] = "File source path sent.";
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $sourcePath);
        finfo_close($finfo);
        if (!in_array($mimeType, $this->allowedMIMETypes)) {
            $this->messages['errors'][] = "Invalid file type.";
            return false;
        }

        // Get extensions. 
        $originalExtension = $this->getExtension($sourcePath);

        $exif = $this->getExif($sourcePath);
        if ($exif === false) {
            // No exif found. Investigate file data.

        }

        if (file_exists($whereToPlace)) {
            $newExtension = $this->getExtension($whereToPlace);
        } else {
            $newExtension = $originalExtension;
        }

        // If no destination file name, simple modify original image.
        if ($whereToPlace == "") {
            $whereToPlace = $sourcePath;
        }

        # check size
        $imageDat = $this->getTheImageSize($sourcePath);
        $width = $imageDat['w'];
        $height = $imageDat['h'];
        $mime = $imageDat['mime'];

        # Create temp image from old image
        # $imt - Image created from original [original size]
        # $im  - New image [new size]
        $imt = $this->imgGdSourceTemporary($sourcePath, $originalExtension);

        # If source image is on location of new, delete source image.
        if ($sourcePath == $whereToPlace) {
            @unlink($sourcePath);
        }

        if ($width > $imw || $height > $imh) {

            if (!$imt) {
                $this->messages['errors'][] = "Plaese check if GD enabled.";
            } else {
                if ($mode == "strech") {
                    // create new
                    $im = $this->strech($imt, $imw, $imh);
                } elseif ($mode == "crop") {
                    // Crop image to new size. (Will be cropped).
                    $im = $this->crop($imt, $imw, $imh, $width, $height);
                } elseif ($mode == "normal") {
                    // Proportional resize image to new size. (Will be resized).
                    $im = $this->proportionalResize($imt, $imw, $imh, $width, $height, $imw, $imh);
                } else {
                    $this->messages['errors'][] = "Mode not supported.";
                    return false;
                }

                # Like shadow
                if (!$this->saveImage($im, $whereToPlace, $newExtension)) {
                    $this->messages['errors'][] = "Failed to save image.";
                }

                if (!chmod($sourcePath, 0644)) {
                    $error = error_get_last();
                    $this->messages['errors'][] = "chmod sourcePath failed: " . $error['message'];
                }
                if (!chmod($whereToPlace, 0644)) {
                    $error = error_get_last();
                    $this->messages['errors'][] = "chmod whereToPlace failed: " . $error['message'];
                }
                return true;
            }
        } else {

            # Image is smaller than new size. Just copy.
            if (!$this->saveImage($imt, $whereToPlace, $newExtension)) {
                $this->messages['errors'][] = "Failed to save image.";
            }

            // Destroy image.
            if (!imagedestroy($imt)) {
                $error = error_get_last();
                $this->messages['errors'][] = "imagedestroy failed: " . $error['message'];
            }

            return true;
        }
        return false;
    }

    /**
     * Strech image to new size. (Will be deformed).
     * @param \GdImage $imt
     * @param int $imw
     * @param int $imh
     * @return \GdImage | false	
     * 
     */
    private function strech(\GdImage $imt, int $imw, int $imh): \GdImage | false
    {
        // create new
        $im = @imagecreatetruecolor($imw, $imh);

        // Copy old to new and destroy old. (image would be mostly  deformed as expected)
        imagecopyresampled($im, $imt, 0, 0, 0, 0, $imw, $imh, imagesx($imt), imagesy($imt));
        imagedestroy($imt);

        return $im;
    }

    /**
     * Crop image to new size. (Will be cropped).
     * TODO: Define details what part of image will be cropped.
     * @param \GdImage $imt
     * @param int $imw
     * @param int $imh
     * @param int $width
     * @param int $height
     * @return \GdImage | false
     * 
     */
    private function crop(\GdImage $imt, int $imw, int $imh, int $width, int $height): \GdImage | false
    {
        $aspectRatio = $this->getAspectRatio($width, $height);

        $temph = ceil($imw * $aspectRatio);
        $tempw = $imw;

        # Create new
        $imTemp = @imagecreatetruecolor($tempw, $temph);

        # Copy old to new and destroy old
        imagecopyresampled($imTemp, $imt, 0, 0, 0, 0, $tempw, $temph, imagesx($imt), imagesy($imt));
        imagedestroy($imt);

        # Create new (for cropping)
        $im = @imagecreatetruecolor($imw, $imh);

        // Cropy old to new and destroy old
        // Find position for cropping.
        // TODO: define details what part of image will be cropped.
        $ls = 0;
        $rs = 0;

        // Crop positionante
        if ($tempw > $imw) {
            $ls = ceil(($tempw - $imw) / 2);
            $rs = 0;
        }
        // end crop position
        if (!imagecopy($im, $imTemp, 0, 0, $ls, $rs, $tempw, $temph)) {
            $this->messages['errors'][] = "Can't imagecopy.";
        }
        imagedestroy($imTemp);

        return $im;
    }

    /**
     * Proportional resize image to new size. (Will be resized).
     * @param \GdImage $imt
     * @param int $imw
     * @param int $imh
     * @param int $width
     * @param int $height
     * @param int $maxWidth
     * @param int $maxHeight
     * @return \GdImage | false
     * 
     */
    private function proportionalResize(\GdImage $imt, int $imw, int $imh, int $width, int $height, int $maxWidth, int $maxHeight): \GdImage | false
    {
        # Find aspect ratio
        $aspectRatio = $this->getAspectRatio($width, $height);
        $orientation = $this->getOrientation($width, $height);

        // Don't allow upscaling. (if image is smaller than max size, keep it as is)
        // Keep aspect ratio.
        if ($width > $imw) {
            $width = $imw;
            $height = ceil($imw / $aspectRatio);
        }
        if ($height >  $imh) {
            $height = $imh;
            $width = ceil($height * $aspectRatio);
        }

        // Just remember original size for future TODO: check if needed.
        $tempw = $width;
        $temph = $height;

        # Create new
        $im = imagecreatetruecolor($tempw, $temph);
        if (!$im) {
            $this->messages['errors'][] = "Can't created img: '" . $tempw . "x" .  $temph;
            return false;
        }

        # Copy old to new and destroy old
        $result = imagecopyresampled($im, $imt, 0, 0, 0, 0, $tempw, $temph, imagesx($imt), imagesy($imt));
        if (!$result) {
            $this->messages['errors'][] = "Failed imagecopyresampled : '" . $tempw . "x" .  $temph;
        }

        // Destroy temportary image.
        if (!imagedestroy($imt)) {
            $this->messages['errors'][] = "Failed imagedestroy";
        }

        return $im;
    }

    /**
     * Get aspect ratio of image.
     * @param int $width
     * @param int $height
     * @return float
     * 
     */
    private function getAspectRatio(int $width, int $height): float
    {
        return $width / $height;
    }

    /**
     * Get image orientation.
     * @param int $width
     * @param int $height
     * @return int
     * Note: 0 - square, 1 - landscape, 2 - portrait (defined in constants)
     */
    private function getOrientation(int $width, int $height): int
    {
        if ($width > $height) {
            return self::LANDSCAPE;
        } elseif ($width < $height) {
            return self::PORTRAIT;
        } else {
            return self::SQUARE;
        }
    }

    /**
     * Create image from source.
     * @param string $imagepath
     * @param string $originalExtension
     * @return \GdImage | false
     */
    private function imgGdSourceTemporary(string $imagepath, string $originalExtension = ''): \GdImage | false
    {
        if (!$this->validate($imagepath)) {
            return false;
        }

        // If original extension not passed, find it.
        if ($originalExtension === '') {
            $originalExtension = $this->getExtension($imagepath);
        }

        // Default image temporarry.
        $imt = false;

        //Try to create tempImg Based on extension. 
        // TODO: Check type.
        if ($originalExtension == "jpg" || $originalExtension == "jpeg") {
            $imt = imagecreatefromjpeg($imagepath);
        }

        if ($originalExtension == "png") {
            $imt = imagecreatefrompng($imagepath);
        }

        if ($originalExtension == "gif") {
            $imt = imagecreatefromgif($imagepath);
        }

        if ($originalExtension == "webp") {
            $imt = imagecreatefromwebp($imagepath);
        }

        return $imt;
    }

    /**
     * Save an image to a file based on its extension.
     * @param \GdImage $image The image resource to save.
     * @param string $filePath The destination file path.
     * @param string $extension The file extension (e.g., jpg, png, etc.).
     * @return bool True on success, false on failure.
     */
    private function saveImage(\GdImage $image, string $filePath, string $extension): bool
    {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return imagejpeg($image, $filePath, 80);
            case 'gif':
                return imagegif($image, $filePath);
            case 'png':
                return imagepng($image, $filePath);
            case 'webp':
                return imagewebp($image, $filePath, 90);
            default:
                $this->messages['errors'][] = "Unsupported file extension: $extension.";
                return false;
        }
    }

    /**
     * Make picture using temporarry: imt.
     * @param \GdImage $imt
     * @param string $whereToPlace
     * @param string $extension
     * @return void
     */
    private function makeImage($imt, string $whereToPlace, string $extension = ''): void
    {
        // If extension does not sent, use ext from destination file.
        if ($extension === '') {
            $extension = $this->getExtension($whereToPlace);
        }

        // Make picture.
        if (!$this->saveImage($imt, $whereToPlace, $extension)) {
            $this->messages['errors'][] = "Error: Failed to save image.";
        }
    }

    /**
     * Get image size.
     * @param string $filePath
     * @return array 'w', 'h', 'mime'
     * @see https://www.php.net/manual/en/function.getimagesize.php
     * @see https://www.php.net/manual/en/function.getimagesizeinfo.php
     * 
     */
    private function getTheImageSize(string $filePath = ''): array
    {
        $arrImg = @getimagesize($filePath);

        if ($arrImg === false) {
            // Something went wrong. Maybe file not real image.
            // TODO: Check type before.
            return ['w' => 0, 'h' => 0, 'mime' => ''];
        }

        $width = $arrImg[0];
        $height = $arrImg[1];
        $mime = $arrImg['mime'];

        return ['w' => $width, 'h' => $height, 'mime' => $mime];
    }

    /**
     * Get file extension.
     * @param string $filePath
     * @return string
     * 
     */
    public function getExtension(string $filePath = ''): string
    {
        if (!file_exists($filePath) || !is_file($filePath)) {
            $this->messages['errors'][] = "File '" . $filePath . "' not found.";
            return '';
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, $this->allowedExtexsions)) {
            return $extension;
        }

        $this->messages['errors'][] = "Extension is not '" . $filePath . "' not found.";
        return '';
    }

    /**
     * Get file name without extension.
     * @param string $filePath
     * @return mixed
     * 
     */
    public function getFileName(string $filePath = ''): mixed
    {
        $fileName = substr($filePath, strrpos($filePath, '/'));
        // Remove extension
        return substr($fileName, 0, strrpos($filePath, '.'));
    }

    /**
     * Try to read exif if exists. (Not all images have exif data).
     * Get exif data from image.
     * @param string $imagePath
     * @return array|false
     * @see https://www.php.net/manual/en/function.exif-read-data.php
     * @see https://www.php.net/manual/en/function.exif-imagetype.php
     * @see https://www.php.net/manual/en/function.exif-thumbnail.php
     * @see https://www.php.net/manual/en/function.exif-tagname.php
     * @see https://www.php.net/manual/en/function.exif-data-ifd0.php
     */
    private function getExif(string $imagePath): array|false
    {
        // Does file exist?
        if (!file_exists($imagePath)) {
            return false;
        }

        // Get exif data if any.
        return @exif_read_data($imagePath);
    }

    // TODO: Not in use yet.
    private function getImageData(string $imagePath): array
    {
        $data['width'] = 0;
        $data['height'] = 0;
        $data['orientation'] = self::SQUARE;
        $data['mime_type'] = '';

        // Mime type
        $data['mime_type'] = $this->getMIME($imagePath);

        // Image size
        $imageDat = $this->getTheImageSize($imagePath);
        if ($imageDat !== false) {
            $data['width'] = $width = $imageDat['w'];
            $data['height'] = $height = $imageDat['h'];
            $data['mime'] = $imageDat['mime']; // Doubled check mime type.
        } // No need else. Called method always returns something,

        // Orientation (landscape, portrait, square)
        if ($width > $height) {
            $data['orientation'] = self::LANDSCAPE;
        }
        if ($width < $height) {
            $data['orientation'] =  self::PORTRAIT;
        }

        return $data;
    }

    /**
     * Get MIME type of image.
     * @param string $imagePath
     * @return string
     * 
     */
    private function getMIME(string $imagePath): string
    {
        // Get extension in lower case.
        $extension = strtolower($this->getExtension($imagePath));

        $mime = image_type_to_mime_type(exif_imagetype($imagePath)); // String
        $xit = exif_imagetype($imagePath); // Int

        $mt = $mime !== false ? $mime : '';
        if (in_array($mt, $this->allowedMIMETypes)) {
            // Estension should fit to MIME type - double check.
            if ($this->extMime[$extension] !== $mime) {
                $this->messages['errors'][] = "Extension does not fit to MIME type.";
                return '';
            }

            // MIME type is allowed and fit to extension.
            return $mt;
        }
        // MIME type is not allowed.
        $this->messages['errors'][] = "MIME type is not allowed (" . $mt . ").";
        return '';
    }

    /**
     * Rotate image.
     * Note: Size is longer side of image (regardless width or height - due to aspect ratio will be keept)
     * @param string $imagepath
     * @param string $imageDestination
     * @param int $angle
     * @return bool
     * 
     */
    public function rotateImage(string $imagepath,  string $imageDestination = "", int $angle = 0): bool
    {
        $result = false;

        // Validate
        if (!$this->validate($imagepath)) {
            $result = false;
        }

        // If destination not posted, revrite image.
        if ($imageDestination === '') {
            $imageDestination = $imagepath;
        }

        // Create temporarry img based on original.
        $imt = $this->imgGdSourceTemporary($imagepath);

        $rotatedImage = imagerotate($imt, $angle, 0);

        if ($imt && $rotatedImage) {
            $this->makeImage($rotatedImage, $imageDestination);
            $result = true;
        }

        @imagedestroy($imt);
        @imagedestroy($rotatedImage);

        return $result;
    }

    /**
     * Validate image.
     * @param string $imagePath
     * @return bool
     */
    private function validate(string $imagePath): bool
    {
        // Check file existance.
        if (!file_exists($imagePath) || !is_file($imagePath)) {
            $this->messages['errors'][] = "Image source file not found.";
            return false;
        }

        // Check MIME type
        if (!$this->getMIME($imagePath)) {
            $this->messages['errors'][] = "MIME type not fit.";
            return false;
        }

        return true;
    }

    /**
     * Flip image.
     * @param string $imagepath
     * @param string $imageDestination
     * @param int $mode
     * @return bool
     */
    public function flipImage(string $imagepath, string $imageDestination = '', int $mode = 1): bool
    {
        $result = false;

        $modes = [1, 2, 3]; // 1 - horizontal, 2 - vertical, 3 - both.
        if (!in_array($mode, $modes)) {
            $this->messages['errors'][] = "Flip mode not supported.";
            return false;
        }

        // Validate
        if (!$this->validate($imagepath)) {
            $result = false;
        }

        // If destination not posted, revrite image.
        if ($imageDestination === '') {
            $imageDestination = $imagepath;
        }

        // Create temporarry img based on original.
        $imt = $this->imgGdSourceTemporary($imagepath);

        if ($imt) {
            $result = imageflip($imt, $mode);
            $this->makeImage($imt, $imageDestination);
            imagedestroy($imt);
            $result = true;
        }

        return $result;
    }

    /**
     * Convert image using one of filters.
     * @param string $imagepath
     * @param string $imageDestination
     * @param int $filter
     * @see https://www.php.net/manual/en/function.imagefilter.php
     * @return bool
     * 
     */
    public function filterImage(string $imagepath, string $imageDestination = '', int $filter = 1, string $args = ''): bool
    {
        $result = false;

        $allowedFilters = [
            IMG_FILTER_NEGATE,
            IMG_FILTER_GRAYSCALE,
            IMG_FILTER_GAUSSIAN_BLUR,
            IMG_FILTER_SELECTIVE_BLUR,
            IMG_FILTER_EMBOSS,
            IMG_FILTER_MEAN_REMOVAL,
        ];

        $allowedArgFilters = [
            IMG_FILTER_BRIGHTNESS,
            IMG_FILTER_CONTRAST,
            IMG_FILTER_COLORIZE,
            IMG_FILTER_EDGEDETECT,
            IMG_FILTER_SMOOTH,
            IMG_FILTER_PIXELATE,
            IMG_FILTER_SCATTER,
        ];

        $argsDefinitions = [
            2 => [-255, 255], // brightness
            3 => [-100, 100], // contrast
            4 => [[0, 255], [0, 255], [0, 255]], // colorize
            5 => [0, 1], // edge
            10 => [0, 1], // smooth
            11 => [[0, 255], [0, 255]], // pixelate
            12 => [[0, 255], [0, 255]], // scatter
        ];


        if (!in_array($filter, $allowedFilters) && (!in_array($filter, $allowedArgFilters) && $args === '')) {
            $this->messages['errors'][] = "Filter not supported. Supported filters are: " . join(", ", $allowedFilters);
            $this->messages['errors'][] = "Filter not supported without args. Supported filters are: " . $this->implodeMultidimensionalArray($argsDefinitions);
            return false;
        }

        // Validate
        if (!$this->validate($imagepath)) {
            $result = false;
        }

        // If destination not posted, revrite image.
        if ($imageDestination === '') {
            $imageDestination = $imagepath;
        }

        // Create temporarry img based on original.
        $imt = $this->imgGdSourceTemporary($imagepath);

        if ($imt && imagefilter($imt, $filter)) {
            $this->makeImage($imt, $imageDestination);
        }

        @imagedestroy($imt);

        return $result;
    }

    private function implodeMultidimensionalArray($array): string
    {
        $val = '';
        if (is_array($array) && count($array) > 0) {
            foreach ($array as $key => $element) {
                if (is_array($element)) {
                    $val .= $this->implodeMultidimensionalArray($element);
                } else {
                    $val .= $key . '=' . $element . '&';
                }
            }
        } else {
            $val = $array;
        }
        return rtrim($val, '&');
    }

    /**
     * Messages getter.
     * @return array
     */
    public function getMessages(): array 
    {
        return $this->messages;
    }
}
