<?php

namespace App\Http\Controllers;

use App\Services\RuneliteApiService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OgImageController extends Controller
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const FONT_BOLD = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';

    private const FONT_REGULAR = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';

    private const FONT_MONO = '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf';

    public function __construct(private RuneliteApiService $runeliteApi) {}

    private const CACHE_TTL = 86400; // 24 hours

    public function __invoke(Request $request, string $name): Response
    {
        $plugin = $this->runeliteApi->getPlugin($name, []);

        if ($plugin === null) {
            abort(404);
        }

        $cachePath = storage_path("app/og/{$name}.png");
        $exists = file_exists($cachePath);
        $isStale = $exists && (time() - filemtime($cachePath)) >= self::CACHE_TTL;

        if (! $exists) {
            // First request: generate synchronously
            $this->generateAndSave($name, $plugin, $cachePath);
        } elseif ($isStale) {
            // Stale: serve existing image now, regenerate after response is sent
            defer(function () use ($name, $plugin, $cachePath) {
                $this->generateAndSave($name, $plugin, $cachePath);
            });
        }

        return response(file_get_contents($cachePath), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age='.self::CACHE_TTL,
        ]);
    }

    /** @param array<mixed> $plugin */
    private function generateAndSave(string $name, array $plugin, string $cachePath): void
    {
        $history = $this->runeliteApi->getPluginHistory($name, []);
        $png = $this->render($plugin, $history);
        @mkdir(dirname($cachePath), 0755, true);
        file_put_contents($cachePath, $png);
    }

    /** @param array<mixed> $history */
    private function render(array $plugin, array $history): string
    {
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $stripY = 532;

        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        // ── Colours ───────────────────────────────────────────────────────
        $cOrange = imagecolorallocate($img, 255, 108, 33);
        $cOrangeDim = imagecolorallocate($img, 197, 71, 4);
        $cWhite = imagecolorallocate($img, 255, 255, 255);
        $cGray100 = imagecolorallocate($img, 240, 240, 240);
        $cGray300 = imagecolorallocate($img, 190, 190, 190);
        $cGray500 = imagecolorallocate($img, 107, 114, 128);

        // ── Gradient background — cool near-black at top, warm dark at bottom ──
        $gradBands = 60;
        for ($band = 0; $band < $gradBands; $band++) {
            $gy1 = (int) ($band * $h / $gradBands);
            $gy2 = (int) (($band + 1) * $h / $gradBands);
            $t = $band / ($gradBands - 1);
            imagefilledrectangle($img, 0, $gy1, $w, $gy2, imagecolorallocate($img,
                (int) (8 + (26 - 8) * $t),   // R: 8 → 26
                (int) (8 + (10 - 8) * $t),    // G: 8 → 10
                (int) (12 - (int) (7 * $t))   // B: 12 → 5
            ));
        }

        // ── History dots as background ────────────────────────────────────
        // Bucket-average to a fixed dot count so every plugin looks visually
        // consistent regardless of how long it has been published.
        $rawCounts = array_values(array_filter(array_map('intval', array_column($history, 'count'))));
        $n = count($rawCounts);

        if ($n > 2) {
            $targetDots = 48;
            $buckets = min($n, $targetDots);
            $sampled = [];
            for ($i = 0; $i < $buckets; $i++) {
                $start = (int) round($i * $n / $buckets);
                $end = (int) round(($i + 1) * $n / $buckets);
                $bucket = array_slice($rawCounts, $start, max(1, $end - $start));
                $sampled[] = (int) round(array_sum($bucket) / count($bucket));
            }

            $np = count($sampled);
            $minC = min($sampled);
            $maxC = max($sampled);
            $range = max($maxC - $minC, 1);

            $chartX1 = 0;
            $chartX2 = $w;
            $chartY1 = 28;
            $dotY2 = $h - 126;   // dots stay above the stats text
            $chartH = $dotY2 - $chartY1;

            // Pre-compute dot positions
            $pts = [];
            for ($i = 0; $i < $np; $i++) {
                $pts[] = [
                    'x' => (int) ($chartX1 + ($np > 1 ? $i / ($np - 1) : 0) * ($chartX2 - $chartX1)),
                    'y' => (int) ($dotY2 - (($sampled[$i] - $minC) / $range) * $chartH * 0.85),
                ];
            }

            // Alternating filled bands — each band spans 2 dot segments as one polygon
            // so there are no internal seam lines within a colour band
            $cFillLight = imagecolorallocatealpha($img, 255, 108, 33, 98);
            $cFillDark = imagecolorallocatealpha($img, 160, 55, 5, 98);
            $bandIdx = 0;
            for ($i = 0; $i < $np - 1; $i += 3) {
                $end = min($i + 3, $np - 1);
                // Polygon: bottom-left → follow curve → bottom-right
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

            // Dots — three concentric circles give a soft glow
            for ($i = 0; $i < $np; $i++) {
                $x = $pts[$i]['x'];
                $y = $pts[$i]['y'];
                imagefilledellipse($img, $x, $y, 30, 30, imagecolorallocatealpha($img, 197, 71, 4, 114));
                imagefilledellipse($img, $x, $y, 17, 17, imagecolorallocatealpha($img, 230, 85, 10, 86));
                imagefilledellipse($img, $x, $y, 8, 8, imagecolorallocatealpha($img, 255, 120, 40, 42));
            }
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

        // ── Plugin name — beside logo, top-left ───────────────────────────
        $pluginName = $plugin['display'] ?? $plugin['name'];
        $nameX = $logoX + $logoSize + 20;
        $nameMaxW = $w - $nameX - 30;
        $nameLines = array_slice($this->wrapText($pluginName, 56, self::FONT_BOLD, $nameMaxW), 0, 2);

        // Vertically centre first name line with logo — baseline = logo centre + half cap-height
        $nameY = $logoY + (int) ($logoSize / 2) + 22;
        foreach ($nameLines as $line) {
            imagettftext($img, 56, 0, $nameX, $nameY, $cWhite, self::FONT_BOLD, $line);
            $nameY += 66;
        }

        // ── Author — left-aligned below logo ──────────────────────────────
        $author = $plugin['author'] ?? '';
        $contentBottom = max($logoY + $logoSize, $nameY - 66 + 66); // bottom of logo/name block
        $authorY = $contentBottom + 22;
        if ($author) {
            imagettftext($img, 27, 0, 28, $authorY, $cOrange, self::FONT_REGULAR, 'by '.$author);
            $authorY += 48;
        }

        // ── Description ───────────────────────────────────────────────────
        $description = $plugin['description'] ?? '';
        $descY = $authorY + 14;
        if ($description) {
            $descLines = array_slice($this->wrapText($description, 22, self::FONT_REGULAR, $w - 56), 0, 3);
            foreach ($descLines as $line) {
                if ($descY > $stripY - 40) {
                    break;
                }
                imagettftext($img, 22, 0, 28, $descY, $cGray300, self::FONT_REGULAR, $line);
                $descY += 36;
            }
        }

        // ── Bottom strip: stat numbers ────────────────────────────────────
        $installs = number_format((int) ($plugin['current_installs'] ?? 0));
        $allTime = number_format((int) ($plugin['all_time_high'] ?? 0));

        $statPad = 44; // left padding for stat text
        $statLabelY = $h - 96;  // label baseline — 96px from bottom
        $statNumY = $h - 30;    // number baseline — 30px from bottom accent bar

        imagettftext($img, 18, 0, $statPad, $statLabelY, $cGray300, self::FONT_BOLD, 'ACTIVE INSTALLS');
        imagettftext($img, 60, 0, $statPad, $statNumY, $cGray100, self::FONT_BOLD, $installs);

        imagettftext($img, 18, 0, $statPad + 320, $statLabelY, $cGray300, self::FONT_BOLD, 'ALL-TIME HIGH');
        imagettftext($img, 60, 0, $statPad + 320, $statNumY, $cGray100, self::FONT_BOLD, $allTime);

        // Domain — right-aligned, baseline aligned with stat numbers, monospace font
        $domain = 'runelite.phyce.dev';
        $box = imagettfbbox(26, 0, self::FONT_MONO, $domain);
        $domainW = abs($box[2] - $box[0]);
        imagettftext($img, 26, 0, $w - $domainW - $statPad, $statNumY, $cOrangeDim, self::FONT_MONO, $domain);

        // ── Render ────────────────────────────────────────────────────────
        ob_start();
        imagepng($img, null, 8);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }

    /** @return string[] */
    private function wrapText(string $text, int $size, string $font, int $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text));
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $test = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($size, 0, $font, $test);
            $lineW = abs($box[2] - $box[0]);

            if ($lineW > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $test;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }
}
