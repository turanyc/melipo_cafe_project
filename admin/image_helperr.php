<?php
// admin/image_helper.php - Image upload and automatic compression helper

/**
 * Uploads and compresses an image, converting it to compressed WebP format (or keeping original if necessary).
 * Shrinks dimensions if they exceed a maximum limit.
 *
 * @param array $file The $_FILES['input_name'] array.
 * @param string $targetSubdir The target subfolder under root 'uploads/' (e.g., 'products' or 'categories').
 * @param int $maxSizePx Maximum width or height of the image.
 * @param int $quality Compression quality (0-100).
 * @return array Array containing 'success' (bool), 'path' (string), 'msg' (string), and 'stats' (array).
 */
function uploadAndCompressImage($file, $targetSubdir, $maxSizePx = 1200, $quality = 75) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'path' => null,
            'msg' => 'Görsel yüklenirken hata oluştu (Hata Kodu: ' . ($file['error'] ?? 'Yok') . ').',
            'stats' => null
        ];
    }

    $originalSize = $file['size']; // in bytes
    $originalName = $file['name'];
    $tmpPath = $file['tmp_name'];

    // Verify file is an image
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        return [
            'success' => false,
            'path' => null,
            'msg' => 'Yüklenen dosya geçerli bir görsel değil.',
            'stats' => null
        ];
    }

    $mimeType = $imageInfo['mime'];
    $srcWidth = $imageInfo[0];
    $srcHeight = $imageInfo[1];

    // Create source image resource based on mime type
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
        case 'image/pjpeg':
            $srcImage = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
        case 'image/x-png':
            $srcImage = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            $srcImage = @imagecreatefromwebp($tmpPath);
            break;
        case 'image/gif':
            $srcImage = @imagecreatefromgif($tmpPath);
            break;
        default:
            return [
                'success' => false,
                'path' => null,
                'msg' => 'Desteklenmeyen görsel formatı. Lütfen JPEG, PNG, WEBP veya GIF yükleyin.',
                'stats' => null
            ];
    }

    if (!$srcImage) {
        return [
            'success' => false,
            'path' => null,
            'msg' => 'Görsel işlenirken bir hata oluştu.',
            'stats' => null
        ];
    }

    // Determine target dimensions maintaining aspect ratio
    $targetWidth = $srcWidth;
    $targetHeight = $srcHeight;

    if ($srcWidth > $maxSizePx || $srcHeight > $maxSizePx) {
        if ($srcWidth > $srcHeight) {
            $targetWidth = $maxSizePx;
            $targetHeight = (int)round(($srcHeight * $maxSizePx) / $srcWidth);
        } else {
            $targetHeight = $maxSizePx;
            $targetWidth = (int)round(($srcWidth * $maxSizePx) / $srcHeight);
        }
    }

    // Create true color image for output
    $dstImage = imagecreatetruecolor($targetWidth, $targetHeight);

    // Maintain transparency for PNG and WebP
    if ($mimeType === 'image/png' || $mimeType === 'image/webp' || $mimeType === 'image/gif') {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparentColor = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
        imagefill($dstImage, 0, 0, $transparentColor);
    }

    // Resample original image onto destination canvas
    imagecopyresampled(
        $dstImage,
        $srcImage,
        0, 0, 0, 0,
        $targetWidth,
        $targetHeight,
        $srcWidth,
        $srcHeight
    );

    // Setup destination folder
    $uploadRootDir = dirname(__DIR__) . '/uploads';
    $targetDir = $uploadRootDir . '/' . $targetSubdir;
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // We will save as WebP for premium performance & compression
    $filename = uniqid($targetSubdir . '_', true) . '.webp';
    $outputPath = $targetDir . '/' . $filename;
    $webPath = 'uploads/' . $targetSubdir . '/' . $filename;

    // Save image as WebP
    $saveSuccess = false;
    if (function_exists('imagewebp')) {
        $saveSuccess = @imagewebp($dstImage, $outputPath, $quality);
    }

    // Fallback to JPEG if WebP saving is not supported or failed
    if (!$saveSuccess) {
        $filename = uniqid($targetSubdir . '_', true) . '.jpg';
        $outputPath = $targetDir . '/' . $filename;
        $webPath = 'uploads/' . $targetSubdir . '/' . $filename;
        $saveSuccess = @imagejpeg($dstImage, $outputPath, $quality);
    }

    // Free resources
    imagedestroy($srcImage);
    imagedestroy($dstImage);

    if (!$saveSuccess) {
        return [
            'success' => false,
            'path' => null,
            'msg' => 'Görsel kaydedilirken sunucu hatası oluştu.',
            'stats' => null
        ];
    }

    // Calculate compression stats
    $compressedSize = filesize($outputPath);
    $savedBytes = $originalSize - $compressedSize;
    $savingsPercent = $originalSize > 0 ? round(($savedBytes / $originalSize) * 100, 1) : 0;

    // Formatted sizes
    $origFormatted = formatBytes($originalSize);
    $compFormatted = formatBytes($compressedSize);

    return [
        'success' => true,
        'path' => $webPath,
        'msg' => 'Görsel başarıyla yüklenip sıkıştırıldı.',
        'stats' => [
            'original_name' => $originalName,
            'original_size_bytes' => $originalSize,
            'compressed_size_bytes' => $compressedSize,
            'original_size_formatted' => $origFormatted,
            'compressed_size_formatted' => $compFormatted,
            'savings_percent' => $savingsPercent,
            'dimensions_original' => "{$srcWidth}x{$srcHeight}",
            'dimensions_compressed' => "{$targetWidth}x{$targetHeight}"
        ]
    ];
}

/**
 * Format bytes to human readable format (KB, MB etc).
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Compresses an image file, converting it to compressed WebP format in memory (no disk writes).
 * Returns the raw binary data and the target MIME type.
 *
 * @param array $file The $_FILES['input_name'] array.
 * @param int $maxSizePx Maximum width or height of the image.
 * @param int $quality Compression quality (0-100).
 * @return array Array containing 'success' (bool), 'data' (string/binary), 'type' (string), 'msg' (string).
 */
function compressImageToBinary($file, $maxSizePx = 1200, $quality = 75) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'data' => null,
            'type' => null,
            'msg' => 'Görsel yüklenirken hata oluştu (Hata Kodu: ' . ($file['error'] ?? 'Yok') . ').'
        ];
    }

    $tmpPath = $file['tmp_name'];

    // Verify file is an image
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        return [
            'success' => false,
            'data' => null,
            'type' => null,
            'msg' => 'Yüklenen dosya geçerli bir görsel değil.'
        ];
    }

    $mimeType = $imageInfo['mime'];
    $srcWidth = $imageInfo[0];
    $srcHeight = $imageInfo[1];

    // Fallback if GD library is not installed/enabled
    if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
        $rawData = file_get_contents($tmpPath);
        if ($rawData === false) {
            return [
                'success' => false,
                'data' => null,
                'type' => null,
                'msg' => 'Görsel dosyası okunamadı.'
            ];
        }
        return [
            'success' => true,
            'data' => $rawData,
            'type' => $mimeType,
            'msg' => 'Görsel (GD kütüphanesi aktif olmadığı için sıkıştırılmadan) başarıyla yüklendi.'
        ];
    }

    // Create source image resource based on mime type
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
        case 'image/pjpeg':
            $srcImage = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
        case 'image/x-png':
            $srcImage = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            $srcImage = @imagecreatefromwebp($tmpPath);
            break;
        case 'image/gif':
            $srcImage = @imagecreatefromgif($tmpPath);
            break;
        default:
            return [
                'success' => false,
                'data' => null,
                'type' => null,
                'msg' => 'Desteklenmeyen görsel formatı. Lütfen JPEG, PNG, WEBP veya GIF yükleyin.'
            ];
    }

    if (!$srcImage) {
        return [
            'success' => false,
            'data' => null,
            'type' => null,
            'msg' => 'Görsel işlenirken bir hata oluştu.'
        ];
    }

    // Determine target dimensions maintaining aspect ratio
    $targetWidth = $srcWidth;
    $targetHeight = $srcHeight;

    if ($srcWidth > $maxSizePx || $srcHeight > $maxSizePx) {
        if ($srcWidth > $srcHeight) {
            $targetWidth = $maxSizePx;
            $targetHeight = (int)round(($srcHeight * $maxSizePx) / $srcWidth);
        } else {
            $targetHeight = $maxSizePx;
            $targetWidth = (int)round(($srcWidth * $maxSizePx) / $srcHeight);
        }
    }

    // Create true color image for output
    $dstImage = imagecreatetruecolor($targetWidth, $targetHeight);

    // Maintain transparency for PNG and WebP
    if ($mimeType === 'image/png' || $mimeType === 'image/webp' || $mimeType === 'image/gif') {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparentColor = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
        imagefill($dstImage, 0, 0, $transparentColor);
    }

    // Resample original image onto destination canvas
    imagecopyresampled(
        $dstImage,
        $srcImage,
        0, 0, 0, 0,
        $targetWidth,
        $targetHeight,
        $srcWidth,
        $srcHeight
    );

    // Compress to WebP or JPEG in memory using output buffering
    ob_start();
    $saveSuccess = false;
    $outputType = 'image/jpeg';

    if (function_exists('imagewebp')) {
        $saveSuccess = @imagewebp($dstImage, null, $quality);
        $outputType = 'image/webp';
    }

    if (!$saveSuccess) {
        // Clear buffer and restart for JPEG fallback
        ob_clean();
        $saveSuccess = @imagejpeg($dstImage, null, $quality);
        $outputType = 'image/jpeg';
    }

    $binaryData = ob_get_clean();

    // Free resources
    imagedestroy($srcImage);
    imagedestroy($dstImage);

    if (!$saveSuccess || empty($binaryData)) {
        return [
            'success' => false,
            'data' => null,
            'type' => null,
            'msg' => 'Görsel sıkıştırılırken hata oluştu.'
        ];
    }

    return [
        'success' => true,
        'data' => $binaryData,
        'type' => $outputType,
        'msg' => 'Görsel başarıyla bellekte sıkıştırıldı.'
    ];
}

