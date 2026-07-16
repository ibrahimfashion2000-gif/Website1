<?php
session_start();
require_once 'db.php'; // provides $pdo (PDO connection) — same as used in dashboard.php

// Load top-level categories for the first dropdown
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// If process_product.php redirected back with an error, show it
$errorMsg = $_SESSION['product_error'] ?? null;
$successMsg = $_SESSION['product_success'] ?? null;
unset($_SESSION['product_error'], $_SESSION['product_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Product | ERP</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons (optional, used for small UI touches) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<style>
    body { background-color: #f4f6f9; }
    .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eef0f3; font-weight: 600; border-radius: 12px 12px 0 0 !important; }
    .form-label { font-weight: 500; font-size: 0.9rem; }
    .variation-row { background: #f8f9fa; border-radius: 8px; padding: 10px; margin-bottom: 10px; }
    #mainImagePreview, .gallery-preview-item { width: 90px; height: 90px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6; }
    .profit-box { background: #eafaf1; border: 1px solid #b7ebc6; border-radius: 8px; padding: 10px 14px; font-weight: 600; }
</style>
</head>
<body>

<div class="container my-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="bi bi-box-seam"></i> Add New Product</h3>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Dashboard</a>
    </div>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>
    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>

    <form action="process_product.php" method="POST" enctype="multipart/form-data" id="productForm">

        <!-- ============ PRODUCT MEDIA ============ -->
        <div class="card mb-4">
            <div class="card-header">Product Media</div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label">Main Image</label>
                        <input type="file" name="main_image" id="main_image" accept="image/*" class="form-control" required>
                        <div class="mt-2">
                            <img id="mainImagePreview" src="" alt="" style="display:none;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gallery Images (multiple)</label>
                        <input type="file" name="gallery_images[]" id="gallery_images" accept="image/*" class="form-control" multiple>
                        <div class="d-flex flex-wrap gap-2 mt-2" id="galleryPreviewWrap"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">YouTube Video URL</label>
                        <input type="url" name="youtube_url" class="form-control" placeholder="https://youtube.com/watch?v=...">
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ BASIC INFO ============ -->
        <div class="card mb-4">
            <div class="card-header">Basic Info</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">SKU <span class="text-danger">*</span></label>
                        <input type="text" name="sku" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="category_id" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sub-category</label>
                        <select name="sub_category_id" id="sub_category_id" class="form-select" disabled>
                            <option value="">-- Select Sub-category --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Child Category</label>
                        <select name="child_category_id" id="child_category_id" class="form-select" disabled>
                            <option value="">-- Select Child Category --</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ PRICING & STOCK ============ -->
        <div class="card mb-4">
            <div class="card-header">Pricing & Stock</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Purchase Price</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" id="purchase_price" class="form-control price-input" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Additional Cost</label>
                        <input type="number" step="0.01" min="0" name="additional_cost" id="additional_cost" class="form-control price-input" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Selling Price <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="selling_price" id="selling_price" class="form-control price-input" required value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Original Price (before discount)</label>
                        <input type="number" step="0.01" min="0" name="original_price" id="original_price" class="form-control" value="0">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" min="0" name="stock_quantity" class="form-control" value="0">
                    </div>
                    <div class="col-md-9 d-flex align-items-end">
                        <div class="profit-box w-100">
                            Profit per Unit: <span id="profitDisplay">0.00</span>
                            <input type="hidden" name="profit_per_unit" id="profit_per_unit" value="0">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ DESCRIPTION ============ -->
        <div class="card mb-4">
            <div class="card-header">Description</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Short Description</label>
                    <textarea name="short_description" class="form-control" rows="3" maxlength="500"></textarea>
                </div>
                <div>
                    <label class="form-label">Detailed Description</label>
                    <textarea name="detailed_description" id="detailed_description"></textarea>
                </div>
                <div class="mt-3">
                    <label class="form-label">Internal Note <small class="text-muted">(not shown to customers)</small></label>
                    <textarea name="internal_note" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>

        <!-- ============ VARIATIONS ============ -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                Variations (Size / Color)
                <button type="button" class="btn btn-sm btn-primary" id="addVariationBtn"><i class="bi bi-plus-lg"></i> Add More</button>
            </div>
            <div class="card-body">
                <div id="variationWrap"></div>
                <small class="text-muted">Leave empty if this product has no size/color variations.</small>
            </div>
        </div>

        <!-- ============ SETTINGS & SEO ============ -->
        <div class="card mb-4">
            <div class="card-header">Settings & SEO</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label d-block">Status</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="status" id="status" value="active" checked>
                            <label class="form-check-label" for="status">Active</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="both" selected>Cash on Delivery + Online</option>
                            <option value="cod">Cash on Delivery only</option>
                            <option value="online">Online Payment only</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Product Badge</label>
                        <select name="product_badge" class="form-select">
                            <option value="">-- None --</option>
                            <option value="best_selling">Best Selling</option>
                            <option value="new_arrival">New Arrival</option>
                            <option value="hot">Hot</option>
                            <option value="limited">Limited Stock</option>
                        </select>
                    </div>
                </div>

                <hr>

                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">SEO Title</label>
                        <input type="text" name="seo_title" class="form-control" maxlength="255">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">SEO Description</label>
                        <textarea name="seo_description" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">SEO Keywords <small class="text-muted">(comma separated)</small></label>
                        <input type="text" name="seo_keywords" class="form-control" placeholder="shoes, sneakers, running shoes">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-5">
            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-success px-4"><i class="bi bi-check-lg"></i> Save Product</button>
        </div>

    </form>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- CKEditor 5 (Classic build) -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>

<script>
// ---------------------------------------------------------
// 1. CKEditor init
// ---------------------------------------------------------
ClassicEditor
    .create(document.querySelector('#detailed_description'))
    .catch(error => console.error(error));

// ---------------------------------------------------------
// 2. Real-time Profit per Unit calculation
// ---------------------------------------------------------
function recalcProfit() {
    const purchase = parseFloat(document.getElementById('purchase_price').value) || 0;
    const additional = parseFloat(document.getElementById('additional_cost').value) || 0;
    const selling = parseFloat(document.getElementById('selling_price').value) || 0;

    const profit = (selling - (purchase + additional));
    document.getElementById('profitDisplay').textContent = profit.toFixed(2);
    document.getElementById('profit_per_unit').value = profit.toFixed(2);
}
document.querySelectorAll('.price-input').forEach(el => {
    el.addEventListener('input', recalcProfit);
});

// ---------------------------------------------------------
// 3. Dynamic Category -> Sub-category -> Child-category
// ---------------------------------------------------------
const categorySelect = document.getElementById('category_id');
const subCategorySelect = document.getElementById('sub_category_id');
const childCategorySelect = document.getElementById('child_category_id');

categorySelect.addEventListener('change', function () {
    subCategorySelect.innerHTML = '<option value="">-- Select Sub-category --</option>';
    childCategorySelect.innerHTML = '<option value="">-- Select Child Category --</option>';
    childCategorySelect.disabled = true;

    if (!this.value) {
        subCategorySelect.disabled = true;
        return;
    }

    fetch('ajax/get_subcategories.php?category_id=' + encodeURIComponent(this.value))
        .then(res => res.json())
        .then(data => {
            data.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.name;
                subCategorySelect.appendChild(opt);
            });
            subCategorySelect.disabled = false;
        })
        .catch(err => console.error('Failed to load sub-categories', err));
});

subCategorySelect.addEventListener('change', function () {
    childCategorySelect.innerHTML = '<option value="">-- Select Child Category --</option>';

    if (!this.value) {
        childCategorySelect.disabled = true;
        return;
    }

    fetch('ajax/get_child_categories.php?sub_category_id=' + encodeURIComponent(this.value))
        .then(res => res.json())
        .then(data => {
            data.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.name;
                childCategorySelect.appendChild(opt);
            });
            childCategorySelect.disabled = false;
        })
        .catch(err => console.error('Failed to load child categories', err));
});

// ---------------------------------------------------------
// 4. Main image preview
// ---------------------------------------------------------
document.getElementById('main_image').addEventListener('change', function (e) {
    const preview = document.getElementById('mainImagePreview');
    if (e.target.files && e.target.files[0]) {
        preview.src = URL.createObjectURL(e.target.files[0]);
        preview.style.display = 'block';
    }
});

// ---------------------------------------------------------
// 5. Gallery images preview
// ---------------------------------------------------------
document.getElementById('gallery_images').addEventListener('change', function (e) {
    const wrap = document.getElementById('galleryPreviewWrap');
    wrap.innerHTML = '';
    Array.from(e.target.files).forEach(file => {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.className = 'gallery-preview-item';
        wrap.appendChild(img);
    });
});

// ---------------------------------------------------------
// 6. Variations: "Add More" dynamic Size/Color rows
// ---------------------------------------------------------
let variationIndex = 0;
document.getElementById('addVariationBtn').addEventListener('click', function () {
    const wrap = document.getElementById('variationWrap');
    const row = document.createElement('div');
    row.className = 'row g-2 variation-row align-items-center';
    row.innerHTML = `
        <div class="col-md-3">
            <input type="text" name="variation_size[]" class="form-control form-control-sm" placeholder="Size (e.g. M, L, XL)">
        </div>
        <div class="col-md-3">
            <input type="text" name="variation_color[]" class="form-control form-control-sm" placeholder="Color (e.g. Red)">
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" name="variation_extra_price[]" class="form-control form-control-sm" placeholder="Extra Price">
        </div>
        <div class="col-md-2">
            <input type="number" name="variation_stock[]" class="form-control form-control-sm" placeholder="Stock">
        </div>
        <div class="col-md-2 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-variation"><i class="bi bi-trash"></i> Remove</button>
        </div>
    `;
    wrap.appendChild(row);
    variationIndex++;
});

document.getElementById('variationWrap').addEventListener('click', function (e) {
    if (e.target.closest('.remove-variation')) {
        e.target.closest('.variation-row').remove();
    }
});
</script>

</body>
</html>