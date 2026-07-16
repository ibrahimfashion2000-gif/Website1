<?php
/**
 * ajax/get_subcategories.php
 * Called via fetch() from add_product.php when the Category dropdown changes.
 * Expects: GET ?category_id=123
 * Returns: JSON array of { id, name }
 */
require_once '../db.php';

header('Content-Type: application/json');

$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);

if (!$categoryId) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM sub_categories WHERE category_id = ? ORDER BY name ASC");
    $stmt->execute([$categoryId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode([]);
}