<?php

namespace Astrotomic\Pixpipe\Analyzers;

use Intervention\Image\Colors\Rgb\Color;
use Intervention\Image\Colors\Rgb\Colorspace as RgbColorspace;
use Intervention\Image\Geometry\Point;
use Intervention\Image\Interfaces\AnalyzerInterface;
use Intervention\Image\Interfaces\ImageInterface;

class FocusPointAnalyzer implements AnalyzerInterface
{
    protected ImageInterface $image;

    protected int $width;

    protected int $height;

    public function analyze(ImageInterface $image): Point
    {
        // Store original dimensions
        $this->height = $image->height();
        $this->width = $image->width();

        // Prepare working image: ensure RGB colorspace, scale down for speed, light blur to reduce noise
        $this->image = (clone $image)
            ->setColorspace(new RgbColorspace)
            ->scaleDown(256, 256)
            ->blur(1);

        $w = $this->image->width();
        $h = $this->image->height();

        // Handle very small images gracefully: return center
        if ($w < 3 || $h < 3 || $this->width < 1 || $this->height < 1) {
            return new Point(max(0, intdiv($this->width, 2)), max(0, intdiv($this->height, 2)));
        }

        // Build luminance map (alpha-aware)
        $lum = [];
        for ($y = 0; $y < $h; $y++) {
            $row = [];
            for ($x = 0; $x < $w; $x++) {
                $c = Color::create($this->image->pickColor($x, $y)->toHex());
                $r = $c->red()->value();
                $g = $c->green()->value();
                $b = $c->blue()->value();
                $a = $c->alpha()->normalize(); // 0..1

                // Perceived luminance (Rec. 709) with alpha weighting (transparent -> less relevant)
                $y709 = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b; // 0..255
                $row[] = $y709 * $a; // reduce contribution by transparency
            }
            $lum[$y] = $row;
        }

        // Compute simple gradient magnitude map and apply slight center bias
        $cx = ($w - 1) / 2.0;
        $cy = ($h - 1) / 2.0;
        $sigma = max(1.0, min($w, $h) / 3.0);

        $bestScore = -1.0;
        $bestX = (int) floor($cx);
        $bestY = (int) floor($cy);

        for ($y = 1; $y < $h - 1; $y++) {
            for ($x = 1; $x < $w - 1; $x++) {
                // Central differences
                $dx = $lum[$y][$x + 1] - $lum[$y][$x - 1];
                $dy = $lum[$y + 1][$x] - $lum[$y - 1][$x];
                $grad = abs($dx) + abs($dy);

                // Center bias (mild), encourages central composition when edges tie
                $dist2 = ($x - $cx) * ($x - $cx) + ($y - $cy) * ($y - $cy);
                $centerBias = exp(-$dist2 / (2.0 * $sigma * $sigma)); // 0..1

                $score = $grad * (1.0 + 0.3 * $centerBias);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestX = $x;
                    $bestY = $y;
                }
            }
        }

        // If image is flat (no edges), use center
        if ($bestScore <= 0) {
            $origX = max(0, min($this->width - 1, intdiv($this->width, 2)));
            $origY = max(0, min($this->height - 1, intdiv($this->height, 2)));

            return new Point($origX, $origY);
        }

        // Map coordinates back to original dimensions
        $scaleX = $this->width / $w;
        $scaleY = $this->height / $h;
        $origX = (int) round($bestX * $scaleX);
        $origY = (int) round($bestY * $scaleY);

        // Clamp to bounds
        $origX = max(0, min($this->width - 1, $origX));
        $origY = max(0, min($this->height - 1, $origY));

        return new Point($origX, $origY);
    }
}
