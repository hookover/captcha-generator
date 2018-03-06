<?php

namespace App\Codes;

use \Exception;
use Illuminate\Filesystem\Filesystem;

class Captcha
{
    protected $files;

    protected $backgrounds       = [];
    protected $fonts             = [];
    protected $backgrounds_count = 0;

    protected $contents = null;

    protected $fingerprint    = [];
    protected $useFingerprint = false;

    protected $backgroundColor             = null;
    protected $allowedBackgroundImageTypes = ['image/png', 'image/jpeg', 'image/gif'];

    protected $length;
    protected $charset;
    protected $phrase;

    protected $height;
    protected $width;

    protected $max_angle  = 30;
    protected $max_offset = 5;

    protected $text_color = [];
    protected $coordinate = [];

    protected $max_behind_lines = null;
    protected $textColor        = null;

    protected $interpolation = true;
    protected $distortion    = true;
    protected $maxFrontLines = null;

    protected $color = null;


    public function __construct()
    {
        $this->phrase();

        $this->files = new Filesystem();

        $this->fonts = $this->files->files(__DIR__ . '/../Assets/fonts');

        $this->fonts = array_filter($this->fonts, function ($file) {
            if ($file->getExtension() == 'ttf') {
                return true;
            }

            return false;
        });

        $this->fonts = array_map(function ($file) {
            return $file->getPathName();
        }, $this->fonts);

        $this->fonts = array_values($this->fonts); //reset fonts array index

        $this->backgrounds = $this->files->files(__DIR__ . '/../Assets/backgrounds');
        $this->backgrounds = array_filter($this->backgrounds, function ($file) {
            if ($file->getExtension() == 'png' || $file->getExtension() == 'jpg' || $file->getExtension() == 'jpeg' || $file->getExtension() == 'bmp') {
                return true;
            }

            return false;
        });
        $this->backgrounds = array_map(function ($file) {
            return $file->getPathName();
        }, $this->backgrounds);

        $this->backgrounds = array_values($this->backgrounds); //reset fonts array index

        $this->backgrounds_count = count($this->backgrounds);


        dump("共" . count($this->backgrounds) . '张图片，' . count($this->fonts) . '个字体文件。');
    }

    public function phrase($length = 4, $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789')
    {
        if ($length !== null) {
            $this->length = $length;
        }
        if ($charset !== null) {
            $this->charset = $charset;
        }

        $phrase = '';
        $chars  = str_split($this->charset);

        for ($i = 0; $i < $this->length; $i++) {
            $phrase .= $chars[ array_rand($chars) ];
        }

        $this->phrase = $phrase;

        return $this;
    }

    public function getFonts()
    {
        return $this->fonts;
    }

    public function rand($min, $max)
    {
        if (!is_array($this->fingerprint)) {
            $this->fingerprint = [];
        }

        if ($this->useFingerprint) {
            $value = current($this->fingerprint);
            next($this->fingerprint);
        } else {
            $value               = mt_rand($min, $max);
            $this->fingerprint[] = $value;
        }

        return $value;
    }

    public function save($filename, $quality = 90)
    {
        $path = dirname($filename);
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        imagejpeg($this->contents, $filename, $quality);
    }

    public function getCoordinate()
    {
        return $this->coordinate;
    }

    protected function font()
    {
        return $this->fonts[ rand(0, count($this->fonts) - 1) ];
    }

    protected function validateBackgroundImage($backgroundImage)
    {
        // check if file exists
        if (!file_exists($backgroundImage)) {
            $backgroundImageExploded = explode('/', $backgroundImage);
            $imageFileName           = count($backgroundImageExploded) > 1 ? $backgroundImageExploded[ count($backgroundImageExploded) - 1 ] : $backgroundImage;

            throw new Exception('Invalid background image: ' . $imageFileName);
        }

        // check image type
        $finfo     = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
        $imageType = finfo_file($finfo, $backgroundImage);
        finfo_close($finfo);

        if (!in_array($imageType, $this->allowedBackgroundImageTypes)) {
            throw new Exception('Invalid background image type! Allowed types are: ' . join(', ', $this->allowedBackgroundImageTypes));
        }

        return $imageType;
    }

    protected function createBackgroundImageFromType($backgroundImage, $imageType)
    {
        switch ($imageType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($backgroundImage);
                break;
            case 'image/png':
                $image = imagecreatefrompng($backgroundImage);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($backgroundImage);
                break;

            default:
                throw new Exception('Not supported file type for background image!');
                break;
        }

        $finfo           = getimagesize($backgroundImage);
        $finfo['width']  = $finfo[0];
        $finfo['height'] = $finfo[1];

        $dist_img = imagecreatetruecolor($this->width, $this->height);


        if ($finfo['width'] < $this->width && $finfo['height'] < $this->height) {
            imagecopyresampled($dist_img, $image, 0, 0, 0, 0, $this->width, $this->height, $finfo['width'], $finfo['height']);
        } elseif ($finfo['width'] < $this->width) {
            imagecopyresampled($dist_img, $image, 0, 0, 0, 0, $this->width, $this->height, $finfo['width'], $this->height);
        } elseif ($finfo['height'] < $this->height) {
            imagecopyresampled($dist_img, $image, 0, 0, 0, 0, $this->width, $this->height, $this->width, $finfo['height']);
        } else {
            //随机图片的随机位置
            $max_x = $finfo['width'] - $this->width;
            $max_y = $finfo['height'] - $this->height;

            $x = $this->rand(0, $max_x);
            $y = $this->rand(0, $max_y);

            imagecopyresampled($dist_img, $image, 0, 0, $x, $y, $this->width, $this->height, $this->width, $this->height);
        }

        return $dist_img;
    }


    public function build($width = 200, $height = 80, $font = null, $length = 4, $fingerprint = null)
    {
        if (null !== $fingerprint) {
            $this->fingerprint    = $fingerprint;
            $this->useFingerprint = true;
        } else {
            $this->fingerprint    = [];
            $this->useFingerprint = false;
        }

        $this->length = $length;

        $this->coordinate = [];
        $this->phrase($length);

        $this->width  = $width;
        $this->height = $height;

        if (!$font) {
            $font = $this->font();
        }

        if (empty($this->backgrounds) || $this->rand(0, $this->backgrounds_count) == 0) {
            // if background images list is not set, use a color fill as a background
            $image = imagecreatetruecolor($width, $height);
            if ($this->backgroundColor == null) {
                $bg = imagecolorallocate($image, $this->rand(0, 254), $this->rand(0, 254), $this->rand(0, 254));
            } else {
                $color = $this->backgroundColor;
                $bg    = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            }
            imagefill($image, 0, 0, $bg);
        } else {
            // use a random background image

            $randomBackgroundImage = $this->backgrounds[ rand(0, count($this->backgrounds) - 1) ];

            $imageType = $this->validateBackgroundImage($randomBackgroundImage);

            $image = $this->createBackgroundImageFromType($randomBackgroundImage, $imageType);
        }


        if ($this->rand(0, 10) == 0) {
            $this->lines($image);
        }

        if ($this->rand(0, 10) == 0) {
            $this->writeNoise($image);
        }

        if ($this->rand(0, 10) == 0) {
            $this->createDot($image);
        }

        $this->postEffectBefore($image);

        $color = $this->writePhrase($image, $this->phrase, $font);

        $this->postEffect($image);

        if ($this->rand(0, 10) == 0) {
            $this->lines($image, $color);
        }

        if ($this->rand(0, 10) == 0) {
            $this->writeCurve($image);
        }


        // Distort the image
        if ($this->distortion && $this->rand(0, 10) == 0) {
            $image = $this->distort($image, $width, $height, null);
        }

//        $this->line($image, $color);

        $this->contents = $image;

        return $this;
    }

    public function buildTestFont($width = 200, $height = 80, $font = null, $phrase = null, $length = 4)
    {
        $this->length     = $length;
        $this->coordinate = [];
        $this->width      = $width;
        $this->height     = $height;

        if ($phrase) {
            $this->phrase = $phrase;
            $this->length = strlen($phrase);
        } else {
            $this->phrase($length);
        }

        if (!$font) {
            $font = $this->font();
        }

        $image = imagecreatetruecolor($width, $height);
        $bg    = imagecolorallocate($image, 250, 250, 250);
        imagefill($image, 0, 0, $bg);

        $this->writePhrase($image, $this->phrase, $font);

        $this->contents = $image;

        return $this;
    }

    protected function lines($image, $color = null)
    {
        $square  = $this->width * $this->height;
        $effects = $this->rand($square / 3000, $square / 2000);

        // set the maximum number of lines to draw in front of the text
        if ($this->maxFrontLines != null && $this->maxFrontLines > 0) {
            $effects = min($this->maxFrontLines, $effects);
        }

        if ($this->maxFrontLines !== 0) {
            for ($e = 0; $e < $effects; $e++) {
                $this->drawLine($image, $this->width, $this->height, $color);
            }
        }
    }

    protected function createDot($image)
    {
        for ($i = 0; $i < 6; $i++) {
            $color = imagecolorallocate($image, mt_rand(0, 254), mt_rand(0, 254), mt_rand(0, 254));
            imageline($image, mt_rand(0, $this->width), mt_rand(0, $this->height), mt_rand(0, $this->width), mt_rand(0, $this->height), $color);
        }
        for ($i = 0; $i < 100; $i++) {
            $color = imagecolorallocate($image, mt_rand(0, 254), mt_rand(0, 254), mt_rand(0, 254));
            imagestring($image, mt_rand(1, 5), mt_rand(0, $this->width), mt_rand(0, $this->height), '*', $color);
        }
    }

    protected function writeCurve($image)
    {
        $A = mt_rand(1, $this->height / 2);                  // 振幅
        $b = mt_rand(-$this->height / 4, $this->height / 4);   // Y轴方向偏移量
        $f = mt_rand(-$this->height / 4, $this->height / 4);   // X轴方向偏移量
        $T = mt_rand($this->height * 1.5, $this->width * 2);  // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0;  //曲线横坐标起始位置
        $px2 = mt_rand($this->width / 2, $this->width * 0.667);  // 曲线横坐标结束位置
        for ($px = $px1; $px <= $px2; $px = $px + 0.9) {
            if ($w != 0) {
                $py = $A * sin($w * $px + $f) + $b + $this->height / 2;  // y = Asin(ωx+φ) + b
//                $i = (int) (($this->width / $this->length - 6)/4);
                $i = (int)abs((($this->height / 3 - 8) / 4));
                while ($i > 0) {
                    imagesetpixel($image, $px + $i, $py + $i, $this->color);
                    //这里画像素点比imagettftext和imagestring性能要好很多
                    $i--;
                }
            }
        }

        $A   = mt_rand(1, $this->height / 2);                  // 振幅
        $f   = mt_rand(-$this->height / 4, $this->height / 4);   // X轴方向偏移量
        $T   = mt_rand($this->height * 1.5, $this->width * 2);  // 周期
        $w   = (2 * M_PI) / $T;
        $b   = $py - $A * sin($w * $px + $f) - $this->height / 2;
        $px1 = $px2;
        $px2 = $this->width;
        for ($px = $px1; $px <= $px2; $px = $px + 0.9) {
            if ($w != 0) {
                $py = $A * sin($w * $px + $f) + $b + $this->height / 2;  // y = Asin(ωx+φ) + b
                $i  = (int)abs((($this->height / 3 - 8) / 4));
                while ($i > 0) {
                    imagesetpixel($image, $px + $i, $py + $i, $this->color);
                    //这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出
                    //的（不用while循环）性能要好很多
                    $i--;
                }
            }
        }
    }

    protected function writeNoise($image)
    {
        for ($i = 0; $i < 10; $i++) {
            //杂点颜色
            $noiseColor = imagecolorallocate(
                $image,
                mt_rand(150, 225),
                mt_rand(150, 225),
                mt_rand(150, 225)
            );
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
//                imagestring(
//                    $image,
//                    5,
//                    mt_rand(-10, $this->width),
//                    mt_rand(-10, $this->height),
//                    $this->charset[ mt_rand(0, 28) ], // 杂点文本为随机的字母或数字
//                    $noiseColor
//                );

                \imagettftext(
                    $image,
                    $this->height / 15,
                    mt_rand(-40, 40),
                    mt_rand(-10, $this->width),
                    mt_rand(-10, $this->height),
                    $noiseColor,
                    $this->font(),
                    $this->charset[ mt_rand(0, strlen($this->charset) - 1) ]
                );
            }
        }
    }

    public function distort($image, $width, $height, $bg)
    {
        $contents = imagecreatetruecolor($width, $height);
        $X        = $this->rand(0, $width);
        $Y        = $this->rand(0, $height);
        $phase    = $this->rand(0, 10);
        $scale    = 1.1 + $this->rand(0, 10000) / 30000;
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $Vx = $x - $X;
                $Vy = $y - $Y;
                $Vn = sqrt($Vx * $Vx + $Vy * $Vy);

                if ($Vn != 0) {
                    $Vn2 = $Vn + 4 * sin($Vn / 30);
                    $nX  = $X + ($Vx * $Vn2 / $Vn);
                    $nY  = $Y + ($Vy * $Vn2 / $Vn);
                } else {
                    $nX = $X;
                    $nY = $Y;
                }
                $nY = $nY + $scale * sin($phase + $nX * 0.2);

                if ($this->interpolation) {
                    $p = $this->interpolate(
                        $nX - floor($nX),
                        $nY - floor($nY),
                        $this->getCol($image, floor($nX), floor($nY), $bg),
                        $this->getCol($image, ceil($nX), floor($nY), $bg),
                        $this->getCol($image, floor($nX), ceil($nY), $bg),
                        $this->getCol($image, ceil($nX), ceil($nY), $bg)
                    );
                } else {
                    $p = $this->getCol($image, round($nX), round($nY), $bg);
                }

                if ($p == 0) {
                    $p = $bg;
                }

                imagesetpixel($contents, $x, $y, $p);
            }
        }

        return $contents;
    }

    protected function interpolate($x, $y, $nw, $ne, $sw, $se)
    {
        list($r0, $g0, $b0) = $this->getRGB($nw);
        list($r1, $g1, $b1) = $this->getRGB($ne);
        list($r2, $g2, $b2) = $this->getRGB($sw);
        list($r3, $g3, $b3) = $this->getRGB($se);

        $cx = 1.0 - $x;
        $cy = 1.0 - $y;

        $m0 = $cx * $r0 + $x * $r1;
        $m1 = $cx * $r2 + $x * $r3;
        $r  = (int)($cy * $m0 + $y * $m1);

        $m0 = $cx * $g0 + $x * $g1;
        $m1 = $cx * $g2 + $x * $g3;
        $g  = (int)($cy * $m0 + $y * $m1);

        $m0 = $cx * $b0 + $x * $b1;
        $m1 = $cx * $b2 + $x * $b3;
        $b  = (int)($cy * $m0 + $y * $m1);

        return ($r << 16) | ($g << 8) | $b;
    }

    protected function getCol($image, $x, $y, $background)
    {
        $L = imagesx($image);
        $H = imagesy($image);
        if ($x < 0 || $x >= $L || $y < 0 || $y >= $H) {
            return $background;
        }

        return imagecolorat($image, $x, $y);
    }

    /**
     * @param $col
     *
     * @return array
     */
    protected function getRGB($col)
    {
        return [
            (int)($col >> 16) & 0xff,
            (int)($col >> 8) & 0xff,
            (int)($col) & 0xff,
        ];
    }

    protected function postEffectBefore($image)
    {
        if ($this->rand(0, 100) == 0) {
            for ($i = 0; $i < $this->rand(5, 15); ++$i) {
                imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }
    }

    protected function postEffect($image)
    {
        if (!function_exists('imagefilter')) {
            return;
        }

        if ($this->backgroundColor != null || $this->textColor != null) {
            return;
        }

        // Negate ?
        if ($this->rand(0, 1) == 5) {
            imagefilter($image, IMG_FILTER_NEGATE);
        }

        // Edge ?
        if ($this->rand(0, 10) == 5) {
            imagefilter($image, IMG_FILTER_EDGEDETECT);
        }

        // Contrast
        imagefilter($image, IMG_FILTER_CONTRAST, $this->rand(-50, 10));

        // Colorize
        if ($this->rand(0, 5) == 5) {
            imagefilter($image, IMG_FILTER_COLORIZE, $this->rand(-80, 50), $this->rand(-80, 50), $this->rand(-80, 50));
        }

    }

    protected function drawLine($image, $width, $height, $tcol = null)
    {
        if ($tcol === null) {
            $tcol = imagecolorallocate($image, $this->rand(100, 254), $this->rand(100, 254), $this->rand(100, 254));
        }

        if ($this->rand(0, 1)) { // Horizontal
            $Xa = $this->rand(0, $width / 2);
            $Ya = $this->rand(0, $height);
            $Xb = $this->rand($width / 2, $width);
            $Yb = $this->rand(0, $height);
        } else { // Vertical
            $Xa = $this->rand(0, $width);
            $Ya = $this->rand(0, $height / 2);
            $Xb = $this->rand(0, $width);
            $Yb = $this->rand($height / 2, $height);
        }
        imagesetthickness($image, $this->rand(1, 3));
        imageline($image, $Xa, $Ya, $Xb, $Yb, $tcol);
    }

    protected function calculateTextBox($fontSize, $fontFile, $text, $fontAngle)
    {
        /************
         * simple function that calculates the *exact* bounding box (single pixel precision).
         * The function returns an associative array with these keys:
         * left, top:  coordinates you will pass to imagettftext
         * width, height: dimension of the image you have to create
         *************/
        $rect = imagettfbbox($fontSize, $fontAngle, $fontFile, $text);
        $minX = min([$rect[0], $rect[2], $rect[4], $rect[6]]);
        $maxX = max([$rect[0], $rect[2], $rect[4], $rect[6]]);
        $minY = min([$rect[1], $rect[3], $rect[5], $rect[7]]);
        $maxY = max([$rect[1], $rect[3], $rect[5], $rect[7]]);

        return [
            "left"   => abs($minX) - 1,
            "top"    => abs($minY) - 1,
            "width"  => $maxX - $minX,
            "height" => $maxY - $minY,
            "box"    => $rect,
        ];
    }

    protected function fontSize($fontFile, $text)
    {
        $first_size = $this->width / $this->length - $this->rand(1, 4);

        if ($first_size > $this->height) {
            $first_size = $this->rand($this->height / 2, $this->height);
        }

        $first_box = $this->calculateTextBox($first_size, $fontFile, $text, 0);

        $rate = 1;

        if (($first_box['width'] - 10) > $this->width) {
            $rate       = ($this->width - $first_size) / $first_box['width'];
            $first_size = $rate * $first_size;
        }

        if (($first_box['height'] - 5) > $this->height) {
            $rate       = ($this->height - 5) / $first_box['height'];
            $first_size = $rate * $first_size;
        }

        $data = ['size' => $first_size, 'rate' => $rate];


        return $data;
    }

    protected function getImageColor($image)
    {
        $r     = 0;
        $g     = 0;
        $b     = 0;
        $total = 0;
        for ($x = 0; $x < imagesx($image); $x++) {
            for ($y = 0; $y < imagesy($image); $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $_r  = ($rgb >> 16) & 0xFF;
                $_g  = ($rgb >> 8) & 0xFF;
                $_b  = $rgb & 0xFF;
                $r   += $_r;
                $g   += $_g;
                $b   += $_b;


                $total++;
            }
        }
        $r_av = round($r / $total);
        $g_av = round($g / $total);
        $b_av = round($b / $total);

        return [
            'r' => $r_av,
            'g' => $g_av,
            'b' => $b_av,
        ];
    }

    protected function buildColor($background_rgb_colors)
    {
        $r = $background_rgb_colors['r'];
        $g = $background_rgb_colors['g'];
        $b = $background_rgb_colors['b'];


        $color = [
            $this->rand(($r > 127 ? 0 : 128), ($r > 127 ? 128 : 254)),
            $this->rand(($g > 127 ? 0 : 128), ($r > 127 ? 128 : 254)),
            $this->rand(($b > 127 ? 0 : 128), ($r > 127 ? 128 : 254)),
        ];

        return $color;
    }

    protected function writePhrase($image, $phrase, $font)
    {
        $length = strlen($phrase);
        if ($length === 0) {
            return \imagecolorallocate($image, 0, 0, 0);
        }

        $font_info = $this->fontSize($font, $phrase);
        $size      = $font_info['size'];

        $box = $this->calculateTextBox($size, $font, $phrase, 0);
        $x   = $box['left'];
        $y   = $box['top'] + ($this->height - $box['top']) / 2;

        $bk_rgb = $this->getImageColor($image);

        $font_name = basename($font, ".ttf");

        //不支持数字,若有数字，则改掉
        if (preg_match('/^\+(\_)?(\_)?font_\d+/', $font_name)) {
            for ($i = 0; $i < mb_strlen($phrase); ++$i) {
                if (is_numeric($phrase[ $i ])) {
                    $phrase[ $i ] = $this->charset[ $this->rand(0, 51) ];
                }
            }
        }

        //不支持小写
        if (preg_match('/^(\+)?\_font_\d+/', $font_name)) {
            $phrase = strtoupper($phrase);
        }
        //不支持大写
        if (preg_match('/^(\+)?\_\_font_\d+/', $font_name)) {
            $phrase = strtolower($phrase);
        }

        for ($i = 0; $i < $length; $i++) {
            if (!count($this->text_color)) {
                $textColor = $this->buildColor($bk_rgb);
            } else {
                $textColor = $this->text_color;
            }
            $col = \imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);

            $angle  = $this->rand(-$this->max_angle, $this->max_angle);
            $offset = $this->rand(-$this->max_offset, $this->max_offset);
            $_y     = $y;
            $_x     = $x + 10;
            if ($i != 0) {
                $_y = $_y + $offset;
                $_x = $_x + $offset;
            }

            $box  = \imagettfbbox($size, $angle, $font, $phrase[ $i ]);
            $minY = min([$box[1], $box[3], $box[5], $box[7]]);
            $maxY = max([$box[1], $box[3], $box[5], $box[7]]);
            $xw   = $box[2] - $box[0];
            $xh   = $maxY - $minY;

            $re = \imagettftext($image, $size, $angle, $_x, $_y, $col, $font, $phrase[ $i ]);
            $w  = $size / $font_info['rate'];

            $this->coordinate[] = [
                'word'      => $phrase[ $i ],
                'x'         => $re[0],
                'y'         => $re[1],
                'angle'     => $angle,
                'width'     => $xw,
                'height'    => abs($xh),
                'font_size' => $size,
                'font_name' => $font_name,
            ];

            $x += $w;

        }

        $this->color = $col;

        return $col;
    }
}
