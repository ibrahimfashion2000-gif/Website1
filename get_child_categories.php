<?php
/**
 * ajax/get_child_categories.php
 * Called via fetch() from add_product.php when the Sub-category dropdown changes.
 * Expects: GET ?sub_category_id=123
 * Returns: JSON array of { id, name }
 */
require_once '../db.php';

header('Content-Type: application/json');

$subCategoryId = filter_input(INPUT_GET, 'sub_category_id', FILTER_VALIDATE_INT);

if (!$subCategoryId) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM child_categories WHERE sub_category_id = ? ORDER BY name ASC");
    $stmt->execute([$subCategoryId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode([]);
}