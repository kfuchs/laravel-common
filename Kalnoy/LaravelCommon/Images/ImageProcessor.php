<?php

namespace Kalnoy\LaravelCommon\Images;

use Closure;
use Exception;
use Intervention\Image\Image;
use Illuminate\Filesystem\Filesystem;

/**
 * Image processor is reponsible for making avatars and thumbnails.
 */
class ImageProcessor {

    /**
     * The image processor.
     *
     * @var \Intervention\Image\Image
     */
    protected $image;

    /**
     * Filesystem object,
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $file;

    /**
     * The path to where save images.
     *
     * @var string
     */
    protected $path;

    /**
     * Init processor.
     *
     * @param \Intervention\Image\Image $image
     */
    public function __construct(Image $image, Filesystem $file, $path)
    {
        $this->image = $image;
        $this->file = $file;
        $this->path = $path;
    }

    /**
     * Resize a image to fit a square of given length.
     * 
     * @param string $src
     * @param int $length
     * 
     * @return string
     */
    public function square($src, $length)
    {
        return $this->resize($src, $length, $length);
    }

    /**
     * Resize image to a given maximum width and height.
     * 
     * @param string $src
     * @param int|null $width
     * @param int|null $height
     *
     * @return string
     */
    public function resize($src, $width, $height)
    {
        return $this->cache('resize', $src, [ $width, $height ], function ($image, $params)
        {
            return $image->resize($params[0], $params[1], true, false);
        });
    }

    /**
     * Fit an image into image of specified size keeping aspect without cropping.
     * 
     * @param string $src
     * @param int $width
     * @param int $height
     * @param mixed $background
     * 
     * @return string
     */
    public function fit($src, $width, $height, $background = null)
    {
        $params = [ $width, $height, $background ];

        return $this->cache('fit', $src, $params, function ($image, $params)
        {
            list($w, $h, $bg) = $params;

            $image = $image->resize($w, $h, true, false);

            if ($image->width < $w or $image->height < $h)
            {
                $image = $image->resizeCanvas($w, $h, null, false, $bg);
            }

            return $image;
        });
    }

    /**
     * Fit image to a square of specified length keeping aspect ratio without
     * cropping.
     *
     * @param string $src
     * @param int $length
     * @param mixed $background
     *
     * @return string
     */
    public function fitToSquare($src, $length, $background = null)
    {
        return $this->fit($src, $length, $length, $background);
    }

    /**
     * Cut a square from image and fit to the square of specified length.
     *
     * @param string $src
     * @param int $length
     * @param int $x The x coordinate of the cropping center
     * @param int $y The y coordinate of the cropping center
     * @param int $halfLength Half-length of the cropping square
     *
     * @return string
     */
    public function cropToFitSquare($src, $length, $x, $y, $halfLength)
    {
        return $this->cache('crop', $src, [ $length ], function ($image, $params) use ($x, $y, $halfLength)
        {
            $image = $image->crop($halfLength * 2, $halfLength * 2, $x - $halfLength, $y - $halfLength);

            // Resize to the given length allowing upsizing
            $image = $image->resize($params[0], $params[0], false, true);

            return $image;
        });
    }

    /**
     * Process an image and save the results.
     *
     * @param string  $category
     * @param string  $src
     * @param Closure $processor
     *
     * @return string
     */
    public function cache($category, $src, $params, Closure $processor)
    {
        $root = public_path() . '/';

        if (empty($src) or ! $this->file->exists($root.$src)) return null;

        $result = $this->filename($src, null, $category.implode('', $params));

        $path = $this->path($result);

        // If target image doesn't exists we'll create one using processor
        if ( ! $this->file->exists($path))
        {
            try
            {
                $image = $this->image->make($root.$src);

                $processor($image, $params)->save($path)->destroy();
            }

            catch (Exception $e)
            {
                if (isset($image)) $image->destroy();

                return false;
            }
        }

        return $result;
    }

    /**
     * Generate a new random filename.
     *
     * @param string $original
     * @param string|null $ext
     * @param string|null $extra
     *
     * @return string
     */
    public function filename($original, $ext = null, $extra = null)
    {
        if ($ext === null) $ext = $this->file->extension($original);

        if ($extra !== null) $original .= $extra;

        return $this->path.'/'.substr($this->hash($original), 0, 4).'/'.str_random(11).'.'.$ext;
    }

    /**
     * Get a saveable path for image.
     *
     * @param string $path
     *
     * @return string
     */
    public function path($path)
    {
        $path = public_path($path);

        $dirname = pathinfo($path, PATHINFO_DIRNAME);

        // Make shure that target directory exists
        if ( ! $this->file->isDirectory($dirname))
        {
            $this->file->makeDirectory($dirname, 0755, true);
        }

        return $path;
    }

    /**
     * Compute a hash for the string.
     * 
     * @param string $value
     * 
     * @return string
     */
    protected function hash($value)
    {
        return sprintf('%x', crc32($value));
    }

    /**
     * Get an underlying image processor.
     *
     * @return \Intervention\Image\Image
     */
    public function getProcessor()
    {
        return $this->image;
    }

}