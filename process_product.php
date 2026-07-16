<?php
/**
 * process_product.php
 * Handles the submission of add_product.php
 * - Sanitizes and validates all inputs
 * - Uploads images to uploads/products/
 * - Inserts product, gallery images, and variations using prepared statements
 */

session_start();
require_once 'db.php'; // provides $pdo (PDO connection)
require_once 'includes/product_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add_product.php');
    exit;
}

function redirectWithError(string $message): void
{
    $_SESSION['product_error'] = $message;
    header('Location: add_product.php');
    exit;
}

// ---------------------------------------------------------
// 1. Sanitize / collect basic text inputs
// ---------------------------------------------------------
$name             = trim(filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW) ?? '');
$sku              = trim(filter_input(INPUT_POST, 'sku', FILTER_UNSAFE_RAW) ?? '');
$categoryId       = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null;
$subCategoryId    = filter_input(INPUT_POST, 'sub_category_id', FILTER_VALIDATE_INT) ?: null;
$childCategoryId  = filter_input(INPUT_POST, 'child_category_id', FILTER_VALIDATE_INT) ?: null;

$purchasePrice    = filter_input(INPUT_POST, 'purchase_price', FILTER_VALIDATE_FLOAT) ?: 0;
$sellingPrice     = filter_input(INPUT_POST, 'selling_price', FILTER_VALIDATE_FLOAT) ?: 0;
$originalPrice    = filter_input(INPUT_POST, 'original_price', FILTER_VALIDATE_FLOAT) ?: 0;
$additionalCost   = filter_input(INPUT_POST, 'additional_cost', FILTER_VALIDATE_FLOAT) ?: 0;
$stockQuantity    = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT) ?: 0;

$shortDescription = trim(filter_input(INPUT_POST, 'short_description', FILTER_UNSAFE_RAW) ?? '');
// Detailed description comes from CKEditor (contains HTML) -> sanitize with strip_tags allow-list
$detailedDescriptionRaw = $_POST['detailed_description'] ?? '';
$allowedTags = '<p><br><b><strong><i><em><u><ul><ol><li><h1><h2><h3><h4><a><img><span><table><thead><tbody><tr><td><th>';
$detailedDescription = strip_tags($detailedDescriptionRaw, $allowedTags);

$internalNote     = trim(filter_input(INPUT_POST, 'internal_note', FILTER_UNSAFE_RAW) ?? '');
$status           = (isset($_POST['status']) && $_POST['status'] === 'active') ? 'active' : 'inactive';
$paymentMethod    = in_array($_POST['payment_method'] ?? '', ['cod', 'online', 'both'], true) ? $_POST['payment_method'] : 'both';

$seoTitle         = trim(filter_input(INPUT_POST, 'seo_title', FILTER_UNSAFE_RAW) ?? '');
$seoDescription   = trim(filter_input(INPUT_POST, 'seo_description', FILTER_UNSAFE_RAW) ?? '');
$seoKeywords      = trim(filter_input(INPUT_POST, 'seo_keywords', FILTER_UNSAFE_RAW) ?? '');
$productBadge     = trim(filter_input(INPUT_POST, 'product_badge', FILTER_UNSAFE_RAW) ?? '');
$youtubeUrl       = filter_input(INPUT_POST, 'youtube_url', FILTER_VALIDATE_URL) ?: null;

// ---------------------------------------------------------
// 2. Basic validation
// ---------------------------------------------------------
if ($name === '' || $sku === '' || $sellingPrice <= 0) {
    redirectWithError('Please fill in Product Name, SKU, and a valid Selling Price.');
}

// SKU must be unique
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
$checkStmt->execute([$sku]);
if ((int) $checkStmt->fetchColumn() > 0) {
    redirectWithError('This SKU already exists. Please use a unique SKU.');
}

// ---------------------------------------------------------
// 3. Calculate profit + generate unique slug
// ---------------------------------------------------------
$profitPerUnit = calculateProfitPerUnit($purchasePrice, $additionalCost, $sellingPrice);
$slug = generateUniqueSlug($pdo, generateSlug($name));

// ---------------------------------------------------------
// 4. Handle Main Image upload (required)
// ---------------------------------------------------------
if (!isset($_FILES['main_image']) || $_FILES['main_image']['error'] === UPLOAD_ERR_NO_FILE) {
    redirectWithError('Main image is required.');
}

$mainImageResult = uploadProductImage($_FILES['main_image'], 'uploads/products/');
if (!$mainImageResult['success']) {
    redirectWithError('Main image upload failed: ' . $mainImageResult['error']);
}
$mainImagePath = $mainImageResult['path'];

// ---------------------------------------------------------
// 5. Insert product (prepared statement)
// ---------------------------------------------------------
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO products (
            name, sku, category_id, sub_category_id, child_category_id, slug,
            main_image, youtube_url,
            purchase_price, selling_price, original_price, additional_cost,
            stock_quantity, profit_per_unit,
            short_description, detailed_description, internal_note,
            status, payment_method,
            seo_title, seo_description, seo_keywords, product_badge
        ) VALUES (
            :name, :sku, :category_id, :sub_category_id, :child_category_id, :slug,
            :main_image, :youtube_url,
            :purchase_price, :selling_price, :original_price, :additional_cost,
            :stock_quantity, :profit_per_unit,
            :short_description, :detailed_description, :internal_note,
            :status, :payment_method,
            :seo_title, :seo_description, :seo_keywords, :product_badge
        )
    ");

    $stmt->execute([
        ':name'                 => $name,
        ':sku'                  => $sku,
        ':category_id'          => $categoryId,
        ':sub_category_id'      => $subCategoryId,
        ':child_category_id'    => $childCategoryId,
        ':slug'                 => $slug,
        ':main_image'           => $mainImagePath,
        ':youtube_url'          => $youtubeUrl,
        ':purchase_price'       => $purchasePrice,
        ':selling_price'        => $sellingPrice,
        ':original_price'       => $originalPrice,
        ':additional_cost'      => $additionalCost,
        ':stock_quantity'       => $stockQuantity,
        ':profit_per_unit'      => $profitPerUnit,
        ':short_description'    => $shortDescription,
        ':detailed_description' => $detailedDescription,
        ':internal_note'        => $internalNote,
        ':status'               => $status,
        ':payment_method'       => $paymentMethod,
        ':seo_title'            => $seoTitle,
        ':seo_description'      => $seoDescription,
        ':seo_keywords'         => $seoKeywords,
        ':product_badge'        => $productBadge,
    ]);

    $productId = (int) $pdo->lastInsertId();

    // -----------------------------------------------------
    // 6. Gallery images (multiple, optional)
    // -----------------------------------------------------
    if (!empty($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
        $galleryPaths = uploadGalleryImages($_FILES['gallery_images'], 'uploads/products/');
        if (!empty($galleryPaths)) {
            $galleryStmt = $pdo->prepare("INSERT INTO product_gallery_images (product_id, image_path) VALUES (?, ?)");
            foreach ($galleryPaths as $path) {
                $galleryStmt->execute([$productId, $path]);
            }
        }
    }

    // -----------------------------------------------------
    // 7. Variations (Size / Color rows, optional)
    // -----------------------------------------------------
    $sizes       = $_POST['variation_size'] ?? [];
    $colors      = $_POST['variation_color'] ?? [];
    $extraPrices = $_POST['variation_extra_price'] ?? [];
    $stocks      = $_POST['variation_stock'] ?? [];

    if (!empty($sizes)) {
        $variationStmt = $pdo->prepare("
            INSERT INTO product_variations (product_id, size, color, extra_price, stock)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($sizes as $i => $size) {
            $size  = trim(strip_tags($size));
            $color = trim(strip_tags($colors[$i] ?? ''));
            $extra = (float) ($extraPrices[$i] ?? 0);
            $stock = (int) ($stocks[$i] ?? 0);

            // Skip completely empty rows
            if ($size === '' && $color === '') {
                continue;
            }

            $variationStmt->execute([$productId, $size, $color, $extra, $stock]);
        }
    }

    $pdo->commit();

    $_SESSION['product_success'] = 'Product "' . $name . '" was added successfully!';
    header('Location: add_product.php');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    // Do not expose raw DB error to the end user in production
    redirectWithError('Database error while saving product. Please try again.');
}