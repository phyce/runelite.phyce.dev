<?php

namespace App\Http\Controllers;

class StaticOgImageController extends Controller
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const FONT_BOLD = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';

    private const FONT_REGULAR = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';

    private const FONT_MONO = '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf';

    public function render(): string
    {
        $w = self::WIDTH;
        $h = self::HEIGHT;

        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        // ── Colours ───────────────────────────────────────────────────────
        $cOrange = imagecolorallocate($img, 255, 108, 33);
        $cOrangeDim = imagecolorallocate($img, 197, 71, 4);
        $cWhite = imagecolorallocate($img, 255, 255, 255);

        // ── Gradient background ───────────────────────────────────────────
        $gradBands = 60;
        for ($band = 0; $band < $gradBands; $band++) {
            $gy1 = (int) ($band * $h / $gradBands);
            $gy2 = (int) (($band + 1) * $h / $gradBands);
            $t = $band / ($gradBands - 1);
            imagefilledrectangle($img, 0, $gy1, $w, $gy2, imagecolorallocate($img,
                (int) (8 + (26 - 8) * $t),
                (int) (8 + (10 - 8) * $t),
                (int) (12 - (int) (7 * $t))
            ));
        }

        // ── Decorative chart — quadratic growth curve ─────────────────────
        $np = 48;
        $data = [];
        for ($i = 0; $i < $np; $i++) {
            $t = $i / ($np - 1);
            $data[] = (int) round(100 + 3300 * ($t ** 2));
        }

        $minC = min($data);
        $maxC = max($data);
        $range = max($maxC - $minC, 1);

        $dotY2 = $h - 80;
        $chartH = $dotY2 - 28;

        $pts = [];
        for ($i = 0; $i < $np; $i++) {
            $pts[] = [
                'x' => (int) ($i / ($np - 1) * $w),
                'y' => (int) ($dotY2 - (($data[$i] - $minC) / $range) * $chartH * 0.85),
            ];
        }

        // Alternating bands — 3 dot segments per band
        $cFillLight = imagecolorallocatealpha($img, 255, 108, 33, 98);
        $cFillDark = imagecolorallocatealpha($img, 160, 55, 5, 98);
        $bandIdx = 0;
        for ($i = 0; $i < $np - 1; $i += 3) {
            $end = min($i + 3, $np - 1);
            $poly = [$pts[$i]['x'], $h];
            for ($j = $i; $j <= $end; $j++) {
                $poly[] = $pts[$j]['x'];
                $poly[] = $pts[$j]['y'];
            }
            $poly[] = $pts[$end]['x'];
            $poly[] = $h;
            imagefilledpolygon($img, $poly, ($bandIdx % 2 === 0) ? $cFillLight : $cFillDark);
            $bandIdx++;
        }

        // Dots
        for ($i = 0; $i < $np; $i++) {
            $x = $pts[$i]['x'];
            $y = $pts[$i]['y'];
            imagefilledellipse($img, $x, $y, 30, 30, imagecolorallocatealpha($img, 197, 71, 4, 114));
            imagefilledellipse($img, $x, $y, 17, 17, imagecolorallocatealpha($img, 230, 85, 10, 86));
            imagefilledellipse($img, $x, $y, 8, 8, imagecolorallocatealpha($img, 255, 120, 40, 42));
        }

        // ── Left orange accent bar ─────────────────────────────────────────
        imagefilledrectangle($img, 0, 0, 7, $h, $cOrange);

        // ── RuneLite logo — top-left ───────────────────────────────────────
        $logoSize = 80;
        $logoX = 32;
        $logoY = 44;
        $logoPath = public_path('img/runelite.png');
        if (file_exists($logoPath)) {
            $logo = imagecreatefrompng($logoPath);
            imagecopyresampled($img, $logo, $logoX, $logoY, 0, 0, $logoSize, $logoSize, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        }

        // ── Site title — beside logo ───────────────────────────────────────
        $titleX = $logoX + $logoSize + 20;
        $titleY = $logoY + (int) ($logoSize / 2) + 22;
        imagettftext($img, 56, 0, $titleX, $titleY, $cWhite, self::FONT_BOLD, 'RuneLite Plugin Stats');

        // ── Domain — bottom-right, monospace ──────────────────────────────
        $domain = 'runelite.phyce.dev';
        $box = imagettfbbox(26, 0, self::FONT_MONO, $domain);
        $domainW = abs($box[2] - $box[0]);
        imagettftext($img, 26, 0, $w - $domainW - 44, $h - 30, $cOrangeDim, self::FONT_MONO, $domain);

        // ── Render ────────────────────────────────────────────────────────
        ob_start();
        imagepng($img, null, 8);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png;
    }
}
