# imager

**Example - flip:**  
> Usage: $php demo-flip.php <image_path> <image_destination> <flip_mode>  
> args for flip mode. Use 1 for 'horizontal', 2 for 'vertical', 3 for 'both'  
>  E.G. $php demo-flip.php "/path/to/image/file" "/path/to/image/file2" 1   

**Example - rotate:** 
> Usage: php demo-rotate.php <image_path> <image_destination> <angle>  
> args for rotate: angle. Between 0 and 359  
>  E.G. $php demo-rotate.php "/path/to/image/file" "/path/to/image/file2" 45

**Example - filters:** 
> Usage: php demo-filter.php <image_path> <image_destination> <filter> [args]  
> Args: depending on filter different args needs (some filters not accept any arg)  
> E.G. Following should convert image in grayscale: $php demo-filter.php "/path/to/image/file" "/path/to/image/file2" 1  
>  
> Filters for apply without args:  
> IMG_FILTER_NEGATE = 0  
> IMG_FILTER_GRAYSCALE = 1  
> IMG_FILTER_GAUSSIAN_BLUR = 7  
> IMG_FILTER_SELECTIVE_BLUR = 8  
> IMG_FILTER_EMBOSS = 6  
> IMG_FILTER_MEAN_REMOVAL = 9
>
> Filters for apply with args:  
> IMG_FILTER_BRIGHTNESS = 2  (1 arg)  
> IMG_FILTER_CONTRAST = 3  (1 arg)  
> IMG_FILTER_COLORIZE = 4  (3 args)  
> IMG_FILTER_EDGEDETECT = 5  (1 arg)  
> IMG_FILTER_SMOOTH = 10  (1 arg)  
> IMG_FILTER_PIXELATE = 11  (2 arg)  
> IMG_FILTER_SCATTER = 12  (2 arg)  
> 


