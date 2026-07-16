<?php
/**
 * includes/product_functions.php
 * Shared helper functions for the Product module.
 * Include this file wherever product logic is needed:
 *   require_once 'includes/product_functions.php';
 */

/**
 * Calculate profit per unit.
 * Formula: Selling Price - (Purchase Price + Additional Cost)
 */
function calculateProfitPerUnit($purchase_price, $additional_cost, $selling_price): float
{
    $purchase_price  = (float) $purchase_price;
    $additional_cost = (float) $additional_cost;
    $selling_price   = (float) $selling_price;

    return round($selling_price - ($purchase_price + $additional_cost), 2);
}

/**
 * Convert a product name into a URL-friendly slug.
 * e.g. "Men's Cotton T-Shirt!" -> "mens-cotton-t-shirt"
 */
function generateSlug(string $string): string
{
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'product-' . time();
}

/**
 * Make sure the slug is unique in the products table.
 * If "red-shoe" already exists, it becomes "red-shoe-1", "red-shoe-2", etc.
 */
function generateUniqueSlug(PDO $pdo, string $baseSlug, ?int $excludeId = null): string
{
    $slug = $baseSlug;
    $i = 1;

    $sql = "SELECT COUNT(*) FROM products WHERE slug = ?" . ($excludeId ? " AND id != ?" : "");
    $stmt = $pdo->prepare($sql);

    while (true) {
        $params = $excludeId ? [$slug, $excludeId] : [$slug];
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) {
            break;
        }
        $slug = $baseSlug . '-' . $i;
        $i++;
    }

    return $slug;
}

/**
 * Validate + move an uploaded image to the target folder.
 * Returns ['success' => bool, 'path' => string|null, 'error' => string|null]
 *
 * $path returned is a RELATIVE path (e.g. "uploads/products/xxx.jpg")
 * suitable for storing directly in the database.
 */
function uploadProductImage(array $file, string $uploadDir = 'uploads/products/'): array
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSizeBytes = 5 * 1024 * 1024; // 5MB

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'path' => null, 'error' => 'No file uploaded.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'error' => 'Upload error code: ' . $file['error']];
    }

    if ($file['size'] > $maxSizeBytes) {
        return ['success' => false, 'path' => null, 'error' => 'File is larger than 5MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes, true)) {
        return ['success' => false, 'path' => null, 'error' => 'Only JPG, PNG, WEBP, or GIF images are allowed.'];
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $ext = $extMap[$mime];
    $fileName = uniqid('prod_', true) . '.' . $ext;
    $targetPath = rtrim($uploadDir, '/') . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'path' => null, 'error' => 'Could not move uploaded file to target folder.'];
    }

    return ['success' => true, 'path' => $targetPath, 'error' => null];
}

/**
 * Handle a <input type="file" multiple> gallery upload.
 * Returns an array of relative paths for successfully uploaded images.
 */
function uploadGalleryImages(array $files, string $uploadDir = 'uploads/products/'): array
{
    $uploadedPaths = [];

    if (empty($files['name'][0])) {
        return $uploadedPaths;
    }

    $fileCount = count($files['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        $singleFile = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        $result = uploadProductImage($singleFile, $uploadDir);
        if ($result['success']) {
            $uploadedPaths[] = $result['path'];
        }
    }

    return $uploadedPaths;
}