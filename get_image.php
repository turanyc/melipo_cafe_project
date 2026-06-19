<?php
// get_image.php - Serves category and product images directly from MySQL BLOB storage
require_once 'db.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($type) || empty($id)) {
    header("HTTP/1.0 400 Bad Request");
    exit("Missing parameters");
}

try {
    if ($type === 'category') {
        $stmt = $pdo->prepare("SELECT image_data, image_type FROM categories WHERE id = ?");
        $stmt->execute([$id]);
    } else if ($type === 'product') {
        $stmt = $pdo->prepare("SELECT image_data, image_type FROM products WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        header("HTTP/1.0 400 Bad Request");
        exit("Invalid type");
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['image_data'])) {
        $mime = !empty($row['image_type']) ? $row['image_type'] : 'image/jpeg';
        header("Content-Type: " . $mime);
        header("Content-Length: " . strlen($row['image_data']));
        // Enable long-term caching since image URLs contain IDs and change rarely
        header("Cache-Control: public, max-age=31536000, immutable");
        echo $row['image_data'];
        exit;
    } else {
        header("HTTP/1.0 404 Not Found");
        exit("Image not found");
    }
} catch (Exception $e) {
    header("HTTP/1.0 500 Internal Server Error");
    exit("Server error: " . $e->getMessage());
}
