<?php

namespace Astrotomic\Pixpipe\Manipulators;

use Astrotomic\Pixpipe\Analyzers\FocusPointAnalyzer;
use Intervention\Image\Geometry\Point;
use Intervention\Image\Interfaces\ImageInterface;

class Size extends \League\Glide\Manipulators\Size
{
    protected function isSmartcrop(): bool
    {
        $fit = (string) $this->getParam('fit');

        return $fit === 'smartcrop';
    }

    public function getFit(): string
    {
        if ($this->isSmartcrop()) {
            return 'crop';
        }

        return parent::getFit();
    }

    public function resolveCropOffset(ImageInterface $image, int $width, int $height): array
    {
        if (! $this->isSmartcrop()) {
            return parent::resolveCropOffset($image, $width, $height);
        }

        /** @var Point $focus */
        $focus = $image->analyze(new FocusPointAnalyzer);

        // Compute the crop offset so that the focus point is as centered as possible
        $offset_x = (int) round($focus->x() - ($width / 2));
        $offset_y = (int) round($focus->y() - ($height / 2));

        // Clamp offsets to stay within the image bounds
        $max_offset_x = $image->width() - $width;
        $max_offset_y = $image->height() - $height;

        if ($offset_x < 0) {
            $offset_x = 0;
        }

        if ($offset_y < 0) {
            $offset_y = 0;
        }

        if ($offset_x > $max_offset_x) {
            $offset_x = $max_offset_x;
        }

        if ($offset_y > $max_offset_y) {
            $offset_y = $max_offset_y;
        }

        return [$offset_x, $offset_y];
    }
}
