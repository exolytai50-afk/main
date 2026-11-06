<?php
/**
 * Хелперы для сохранения пользовательских изображений.
 */

/**
 * Сохраняет data-URL (base64) в /uploads/scans/YYYY/MM/ и возвращает публичный URL.
 *
 * @param string $dataUrl  Строка вида "data:image/jpeg;base64,..."
 * @param string $prefix   Префикс имени файла (например, 'scan')
 * @return string|null     Публичный URL или null при ошибке
 */
function save_data_url_image(string $dataUrl, string $prefix = 'scan'): ?string
{
    if ($dataUrl === '' || !preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $dataUrl, $m)) {
        return null;
    }

    $mime = strtolower($m[1]);
    $ext  = $mime === 'jpeg' ? 'jpg' : $mime;

    $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
    $binary = base64_decode($base64, true);
    if ($binary === false || strlen($binary) < 100) {
        return null;
    }

    // Лимит: 5 МБ
    if (strlen($binary) > 5 * 1024 * 1024) {
        return null;
    }

    $relDir = '/uploads/scans/' . date('Y/m');
    $absDir = ROOT_DIR . $relDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
        return null;
    }

    $filename = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $absPath  = $absDir . '/' . $filename;

    if (file_put_contents($absPath, $binary) === false) {
        return null;
    }

    return $relDir . '/' . $filename;
    /**
 * Ресайз + конвертация в WebP.
 * @return bool
 */
if (!function_exists('resize_to_webp')) {
    function resize_to_webp(string $src, string $dst, int $maxWidth, int $quality = 82): bool
    {
        if (!function_exists('imagewebp')) return false; // нет GD

        $info = @getimagesize($src);
        if (!$info) return false;

        [$w, $h, $type] = $info;

        $img = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => imagecreatefrompng($src),
            IMAGETYPE_GIF  => imagecreatefromgif($src),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($src) : null,
            default        => null,
        };
        if (!$img) return false;

        // Пропорциональный ресайз
        if ($w > $maxWidth) {
            $newW = $maxWidth;
            $newH = (int)round($h * ($maxWidth / $w));
        } else {
            $newW = $w;
            $newH = $h;
        }

        $dstImg = imagecreatetruecolor($newW, $newH);

        // Прозрачность для PNG/GIF
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
        imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $transparent);

        imagecopyresampled($dstImg, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $ok = imagewebp($dstImg, $dst, $quality);

        imagedestroy($img);
        imagedestroy($dstImg);

        return $ok;
    }
}

/**
 * Вернуть URL варианта изображения.
 * Если thumb нет — возвращает оригинал.
 */
if (!function_exists('image_variant')) {
    function image_variant(?string $url, string $variant = 'medium'): string
    {
        if (!$url) return '/uploads/static/static.jpg';
        $ext  = pathinfo($url, PATHINFO_EXTENSION);
        $base = substr($url, 0, -(strlen($ext) + 1));
        $try  = $base . '-' . $variant . '.webp';
        if (file_exists(ROOT_DIR . $try)) return $try;
        return $url;
    }
}
}