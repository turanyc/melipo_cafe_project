<?php
// admin/index.php - Melipo Cafe Management Panel
session_start();

// Redirect if not logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/image_helper.php';

$successMsg = '';
$errorMsg = '';
$compressionStats = null;
$activeTab = 'products'; // default tab

// Process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADD CATEGORY ACTION
    if ($action === 'add_category') {
        $activeTab = 'categories';
        $catId = strtolower(preg_replace('/[^a-z0-9_]/', '', $_POST['cat_id'] ?? ''));
        $catTitle = trim($_POST['cat_title'] ?? '');
        $catNotice = trim($_POST['cat_notice'] ?? '');
        $catNotice = empty($catNotice) ? null : $catNotice;

        if (empty($catId) || empty($catTitle)) {
            $errorMsg = 'Lütfen kategori kodu ve başlığını doldurun.';
        } elseif (isset($_FILES['cat_image']) && $_FILES['cat_image']['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = 'Lütfen bir kategori görseli seçin.';
        } else {
            try {
                // Check if ID already exists
                $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ?");
                $check->execute([$catId]);
                if ($check->fetchColumn() > 0) {
                    $errorMsg = "Kategori Kodu ('{$catId}') zaten kullanımda. Lütfen başka bir kod seçin.";
                } else {
                    // Compress to memory
                    $uploadResult = compressImageToBinary($_FILES['cat_image'], 1200, 75);
                    if ($uploadResult['success']) {
                        $imgData = $uploadResult['data'];
                        $imgType = $uploadResult['type'];
                        $imageLink = 'get_image.php?type=category&id=' . $catId;

                        $stmt = $pdo->prepare("INSERT INTO categories (id, title, image, image_data, image_type, notice) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$catId, $catTitle, $imageLink, $imgData, $imgType, $catNotice]);

                        $compressionStats = [
                            'original_name' => $_FILES['cat_image']['name'],
                            'original_size_bytes' => $_FILES['cat_image']['size'],
                            'compressed_size_bytes' => strlen($imgData),
                            'original_size_formatted' => formatBytes($_FILES['cat_image']['size']),
                            'compressed_size_formatted' => formatBytes(strlen($imgData)),
                            'savings_percent' => $_FILES['cat_image']['size'] > 0 ? round((($_FILES['cat_image']['size'] - strlen($imgData)) / $_FILES['cat_image']['size']) * 100, 1) : 0,
                            'dimensions_original' => 'Bilinmiyor',
                            'dimensions_compressed' => 'Bilinmiyor'
                        ];

                        $successMsg = "Kategori başarıyla eklendi.";
                    } else {
                        $errorMsg = $uploadResult['msg'];
                    }
                }
            } catch (PDOException $e) {
                $errorMsg = 'Kategori eklenirken veritabanı hatası oluştu: ' . $e->getMessage();
            }
        }
    }

    // 2. ADD PRODUCT ACTION
    elseif ($action === 'add_product') {
        $activeTab = 'products';
        $prodName = trim($_POST['prod_name'] ?? '');
        $catId = $_POST['prod_category'] ?? '';
        $prodDesc = trim($_POST['prod_desc'] ?? '');
        $prodPrice = trim($_POST['prod_price'] ?? '');
        if (!empty($prodPrice)) {
            $prodPrice = preg_replace('/^(₺|TL|tl|Tl)\s*/u', '', $prodPrice);
            $prodPrice = '₺ ' . $prodPrice;
        }
        $prodFlavors = trim($_POST['prod_flavors'] ?? '');
        $prodFlavors = empty($prodFlavors) ? null : $prodFlavors;

        if (empty($prodName) || empty($catId) || empty($prodPrice)) {
            $errorMsg = 'Lütfen ürün adı, kategorisi ve fiyatını doldurun.';
        } elseif (isset($_FILES['prod_image']) && $_FILES['prod_image']['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = 'Lütfen bir ürün görseli seçin.';
        } else {
            try {
                // Compress to memory
                $uploadResult = compressImageToBinary($_FILES['prod_image'], 1200, 75);
                if ($uploadResult['success']) {
                    $imgData = $uploadResult['data'];
                    $imgType = $uploadResult['type'];

                    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, image, image_data, image_type, `desc`, price, flavors) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$catId, $prodName, '', $imgData, $imgType, $prodDesc, $prodPrice, $prodFlavors]);
                    $newId = $pdo->lastInsertId();

                    $imageLink = 'get_image.php?type=product&id=' . $newId;
                    $updateStmt = $pdo->prepare("UPDATE products SET image = ? WHERE id = ?");
                    $updateStmt->execute([$imageLink, $newId]);

                    $compressionStats = [
                        'original_name' => $_FILES['prod_image']['name'],
                        'original_size_bytes' => $_FILES['prod_image']['size'],
                        'compressed_size_bytes' => strlen($imgData),
                        'original_size_formatted' => formatBytes($_FILES['prod_image']['size']),
                        'compressed_size_formatted' => formatBytes(strlen($imgData)),
                        'savings_percent' => $_FILES['prod_image']['size'] > 0 ? round((($_FILES['prod_image']['size'] - strlen($imgData)) / $_FILES['prod_image']['size']) * 100, 1) : 0,
                        'dimensions_original' => 'Bilinmiyor',
                        'dimensions_compressed' => 'Bilinmiyor'
                    ];

                    $successMsg = "Ürün başarıyla eklendi.";
                } else {
                    $errorMsg = $uploadResult['msg'];
                }
            } catch (PDOException $e) {
                $errorMsg = 'Ürün eklenirken veritabanı hatası oluştu: ' . $e->getMessage();
            }
        }
    }

    // 3. DELETE PRODUCT ACTION
    elseif ($action === 'delete_product') {
        $activeTab = 'products';
        $prodId = $_POST['prod_id'] ?? '';
        if (!empty($prodId)) {
            try {
                // Get product image to delete from disk (if legacy)
                $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
                $stmt->execute([$prodId]);
                $img = $stmt->fetchColumn();

                $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $deleteStmt->execute([$prodId]);

                if ($deleteStmt->rowCount() > 0) {
                    if ($img && strpos($img, 'get_image.php') === false && file_exists(dirname(__DIR__) . '/' . $img)) {
                        @unlink(dirname(__DIR__) . '/' . $img);
                    }
                    $successMsg = "Ürün başarıyla silindi.";
                } else {
                    $errorMsg = "Silinecek ürün bulunamadı.";
                }
            } catch (PDOException $e) {
                $errorMsg = 'Ürün silinirken hata oluştu: ' . $e->getMessage();
            }
        }
    }

    // 5. EDIT PRODUCT ACTION
    elseif ($action === 'edit_product') {
        $activeTab = 'products';
        $prodId = $_POST['prod_id'] ?? '';
        $prodName = trim($_POST['prod_name'] ?? '');
        $catId = $_POST['prod_category'] ?? '';
        $prodDesc = trim($_POST['prod_desc'] ?? '');
        $prodPrice = trim($_POST['prod_price'] ?? '');
        if (!empty($prodPrice)) {
            $prodPrice = preg_replace('/^(₺|TL|tl|Tl)\s*/u', '', $prodPrice);
            $prodPrice = '₺ ' . $prodPrice;
        }
        $prodFlavors = trim($_POST['prod_flavors'] ?? '');
        $prodFlavors = empty($prodFlavors) ? null : $prodFlavors;

        if (!empty($prodId) && !empty($prodName) && !empty($catId) && !empty($prodPrice)) {
            try {
                // If a new image is uploaded, process it
                if (isset($_FILES['prod_image']) && $_FILES['prod_image']['error'] === UPLOAD_ERR_OK) {
                    // Compress to memory
                    $uploadResult = compressImageToBinary($_FILES['prod_image'], 1200, 75);
                    if ($uploadResult['success']) {
                        $imgData = $uploadResult['data'];
                        $imgType = $uploadResult['type'];
                        $imageLink = 'get_image.php?type=product&id=' . $prodId;

                        // Get old image to delete (if legacy)
                        $oldImgQuery = $pdo->prepare("SELECT image FROM products WHERE id = ?");
                        $oldImgQuery->execute([$prodId]);
                        $oldImg = $oldImgQuery->fetchColumn();

                        // Update product with new image data
                        $stmt = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, image = ?, image_data = ?, image_type = ?, `desc` = ?, price = ?, flavors = ? WHERE id = ?");
                        $stmt->execute([$catId, $prodName, $imageLink, $imgData, $imgType, $prodDesc, $prodPrice, $prodFlavors, $prodId]);

                        // Delete old image file if it's not a default asset and is legacy
                        if ($oldImg && strpos($oldImg, 'get_image.php') === false && file_exists(dirname(__DIR__) . '/' . $oldImg) && strpos($oldImg, 'assets/') === false) {
                            @unlink(dirname(__DIR__) . '/' . $oldImg);
                        }

                        $compressionStats = [
                            'original_name' => $_FILES['prod_image']['name'],
                            'original_size_bytes' => $_FILES['prod_image']['size'],
                            'compressed_size_bytes' => strlen($imgData),
                            'original_size_formatted' => formatBytes($_FILES['prod_image']['size']),
                            'compressed_size_formatted' => formatBytes(strlen($imgData)),
                            'savings_percent' => $_FILES['prod_image']['size'] > 0 ? round((($_FILES['prod_image']['size'] - strlen($imgData)) / $_FILES['prod_image']['size']) * 100, 1) : 0,
                            'dimensions_original' => 'Bilinmiyor',
                            'dimensions_compressed' => 'Bilinmiyor'
                        ];

                        $successMsg = "Ürün ve görseli başarıyla güncellendi.";
                    } else {
                        $errorMsg = $uploadResult['msg'];
                    }
                } else {
                    // Update product without changing image
                    $stmt = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, `desc` = ?, price = ?, flavors = ? WHERE id = ?");
                    $stmt->execute([$catId, $prodName, $prodDesc, $prodPrice, $prodFlavors, $prodId]);
                    $successMsg = "Ürün bilgileri başarıyla güncellendi.";
                }
            } catch (PDOException $e) {
                $errorMsg = 'Ürün güncellenirken veritabanı hatası oluştu: ' . $e->getMessage();
            }
        } else {
            $errorMsg = 'Lütfen tüm zorunlu alanları doldurun.';
        }
    }

    // 6. EDIT CATEGORY ACTION
    elseif ($action === 'edit_category') {
        $activeTab = 'categories';
        $catId = $_POST['cat_id'] ?? '';
        $catTitle = trim($_POST['cat_title'] ?? '');
        $catNotice = trim($_POST['cat_notice'] ?? '');
        $catNotice = empty($catNotice) ? null : $catNotice;

        if (!empty($catId) && !empty($catTitle)) {
            try {
                // If a new image is uploaded, process it
                if (isset($_FILES['cat_image']) && $_FILES['cat_image']['error'] === UPLOAD_ERR_OK) {
                    // Compress to memory
                    $uploadResult = compressImageToBinary($_FILES['cat_image'], 1200, 75);
                    if ($uploadResult['success']) {
                        $imgData = $uploadResult['data'];
                        $imgType = $uploadResult['type'];
                        $imageLink = 'get_image.php?type=category&id=' . $catId;

                        // Get old image to delete
                        $oldImgQuery = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
                        $oldImgQuery->execute([$catId]);
                        $oldImg = $oldImgQuery->fetchColumn();

                        // Update category with new image data
                        $stmt = $pdo->prepare("UPDATE categories SET title = ?, image = ?, image_data = ?, image_type = ?, notice = ? WHERE id = ?");
                        $stmt->execute([$catTitle, $imageLink, $imgData, $imgType, $catNotice, $catId]);

                        // Delete old image file if it's not a default asset and is legacy
                        if ($oldImg && strpos($oldImg, 'get_image.php') === false && file_exists(dirname(__DIR__) . '/' . $oldImg) && strpos($oldImg, 'assets/') === false) {
                            @unlink(dirname(__DIR__) . '/' . $oldImg);
                        }

                        $compressionStats = [
                            'original_name' => $_FILES['cat_image']['name'],
                            'original_size_bytes' => $_FILES['cat_image']['size'],
                            'compressed_size_bytes' => strlen($imgData),
                            'original_size_formatted' => formatBytes($_FILES['cat_image']['size']),
                            'compressed_size_formatted' => formatBytes(strlen($imgData)),
                            'savings_percent' => $_FILES['cat_image']['size'] > 0 ? round((($_FILES['cat_image']['size'] - strlen($imgData)) / $_FILES['cat_image']['size']) * 100, 1) : 0,
                            'dimensions_original' => 'Bilinmiyor',
                            'dimensions_compressed' => 'Bilinmiyor'
                        ];

                        $successMsg = "Kategori ve görseli başarıyla güncellendi.";
                    } else {
                        $errorMsg = $uploadResult['msg'];
                    }
                } else {
                    // Update category without changing image
                    $stmt = $pdo->prepare("UPDATE categories SET title = ?, notice = ? WHERE id = ?");
                    $stmt->execute([$catTitle, $catNotice, $catId]);
                    $successMsg = "Kategori bilgileri başarıyla güncellendi.";
                }
            } catch (PDOException $e) {
                $errorMsg = 'Kategori güncellenirken veritabanı hatası oluştu: ' . $e->getMessage();
            }
        } else {
            $errorMsg = 'Lütfen kategori başlığını doldurun.';
        }
    }

    // 4. DELETE CATEGORY ACTION
    elseif ($action === 'delete_category') {
        $activeTab = 'categories';
        $catId = $_POST['cat_id'] ?? '';
        if (!empty($catId)) {
            try {
                // Get category image and all products images to delete from disk (if legacy)
                $stmt = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
                $stmt->execute([$catId]);
                $catImg = $stmt->fetchColumn();

                $pStmt = $pdo->prepare("SELECT image FROM products WHERE category_id = ?");
                $pStmt->execute([$catId]);
                $prodImgs = $pStmt->fetchAll(PDO::FETCH_COLUMN);

                // Explicitly delete products belonging to the category first to prevent constraint violations
                $deleteProdsStmt = $pdo->prepare("DELETE FROM products WHERE category_id = ?");
                $deleteProdsStmt->execute([$catId]);

                // Now delete the category
                $deleteStmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $deleteStmt->execute([$catId]);

                // Clean up legacy files
                if ($catImg && strpos($catImg, 'get_image.php') === false && file_exists(dirname(__DIR__) . '/' . $catImg) && strpos($catImg, 'assets/') === false) {
                    @unlink(dirname(__DIR__) . '/' . $catImg);
                }
                foreach ($prodImgs as $pImg) {
                    if ($pImg && strpos($pImg, 'get_image.php') === false && file_exists(dirname(__DIR__) . '/' . $pImg) && strpos($pImg, 'assets/') === false) {
                        @unlink(dirname(__DIR__) . '/' . $pImg);
                    }
                }
                $successMsg = "Kategori ve kategoriye ait tüm ürünler başarıyla silindi.";
            } catch (PDOException $e) {
                $errorMsg = 'Kategori silinirken hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, title, image, notice FROM categories ORDER BY title ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMsg = 'Kategoriler yüklenirken hata oluştu: ' . $e->getMessage();
}

// Fetch all products
$products = [];
try {
    $stmt = $pdo->query("SELECT p.id, p.category_id, p.name, p.image, p.desc, p.price, p.flavors, c.title AS category_title FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMsg = 'Ürünler yüklenirken hata oluştu: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli - Melipo Cafe</title>
    <link class="icon" type="image/png" href="../assets/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="admin-header">
        <div class="header-container">
            <a href="../menu" target="_blank" class="brand">
                <span class="brand-text">Melipo Cafe</span>
                <span class="brand-badge">Yönetim</span>
            </a>
            
            <div class="user-nav">
                <span style="font-size: 0.9rem; color: var(--text-muted);">
                    <i class="fa-solid fa-user-gear" style="margin-right: 4px;"></i> 
                    <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                </span>
                <a href="../menu" target="_blank" class="btn-view-menu">
                    <i class="fa-solid fa-qrcode"></i> Menüyü Gör
                </a>
                <a href="logout.php" class="btn-logout">
                    Çıkış Yap <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="admin-main">
        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check" style="font-size: 1.1rem;"></i>
                <span><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-xmark" style="font-size: 1.1rem;"></i>
                <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($compressionStats): ?>
            <div class="compression-badge">
                <div class="compression-badge-title">
                    <i class="fa-solid fa-bolt-lightning"></i> Görsel Otomatik Sıkıştırıldı!
                </div>
                <div class="compression-grid">
                    <div>Orijinal Görsel Boyutu:</div>
                    <div class="compression-val"><?php echo $compressionStats['original_size_formatted']; ?></div>
                    <div>Orijinal Çözünürlük:</div>
                    <div class="compression-val"><?php echo $compressionStats['dimensions_original']; ?></div>
                    <div>Sıkıştırılmış Boyut (WebP):</div>
                    <div class="compression-val" style="color: #34d399;"><?php echo $compressionStats['compressed_size_formatted']; ?></div>
                    <div>Sıkıştırılmış Çözünürlük:</div>
                    <div class="compression-val"><?php echo $compressionStats['dimensions_compressed']; ?></div>
                    <div class="compression-savings">
                        <span>Elde Edilen Tasarruf Oranı:</span>
                        <span class="compression-savings-pct">-%<?php echo $compressionStats['savings_percent']; ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="tab-navigation">
            <button class="tab-btn <?php echo $activeTab === 'products' ? 'active' : ''; ?>" onclick="openTab('products')">
                <i class="fa-solid fa-cookie-bite"></i> Ürün Yönetimi
            </button>
            <button class="tab-btn <?php echo $activeTab === 'categories' ? 'active' : ''; ?>" onclick="openTab('categories')">
                <i class="fa-solid fa-list-ul"></i> Kategori Yönetimi
            </button>
        </div>

        <div id="tab-products" class="tab-content <?php echo $activeTab === 'products' ? 'active' : ''; ?>">
            <button type="button" class="btn mobile-toggle-btn" onclick="toggleAddForm('product')">
                <span><i class="fa-solid fa-circle-plus" style="color: var(--primary); margin-right: 6px;"></i> Yeni Ürün Ekle</span>
                <i class="fa-solid fa-chevron-down toggle-arrow"></i>
            </button>
            
            <div class="dashboard-grid">
                <div class="card" id="add-product-card">
                    <h2 class="card-title">
                        <i class="fa-solid fa-circle-plus"></i> Yeni Ürün Ekle
                    </h2>
                    
                    <form action="index.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_product">
                        
                        <div class="form-group">
                            <label class="form-label" for="prod_name">Ürün Adı *</label>
                            <input type="text" id="prod_name" name="prod_name" class="form-control" placeholder="Örn: FISTIKLI BAKLAVA" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="prod_category">Kategori *</label>
                            <select id="prod_category" name="prod_category" class="form-control" required style="background-color: #0b0f19;">
                                <option value="" disabled selected>Kategori Seçin</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['id']); ?>">
                                        <?php echo htmlspecialchars($cat['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="prod_desc">Açıklama</label>
                            <textarea id="prod_desc" name="prod_desc" class="form-control" placeholder="Ürün içeriğini açıklayın (Örn: Antep fıstıklı, çıtır baklava.)"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="prod_price">Fiyat *</label>
                            <input type="text" id="prod_price" name="prod_price" class="form-control" placeholder="Örn: ₺ 200.00" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="prod_flavors">Aroma Seçenekleri (İsteğe Bağlı)</label>
                            <input type="text" id="prod_flavors" name="prod_flavors" class="form-control" placeholder="Virgülle ayırın (Örn: Limon, Vişne, Karadut)">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Ürün Görseli *</label>
                            <div class="file-upload-wrapper">
                                <i class="fa-solid fa-cloud-arrow-up file-upload-icon"></i>
                                <div class="file-upload-text">Bir Görsel Sürükleyin veya Seçin</div>
                                <div class="file-upload-info">PNG, JPG, WEBP (Max 15MB - Otomatik Sıkıştırılacaktır)</div>
                                <input type="file" name="prod_image" accept="image/*" required onchange="displayFilename(this)">
                                <div class="file-chosen-name" style="display:none;"></div>
                            </div>
                        </div>

                        <button type="submit" class="btn" style="margin-top: 10px;">
                            Ürünü Ekle <i class="fa-solid fa-check"></i>
                        </button>
                    </form>
                </div>

                <div class="card">
                    <h2 class="card-title">
                        <i class="fa-solid fa-list"></i> Mevcut Ürünler (<?php echo count($products); ?>)
                    </h2>

                    <div class="filter-wrapper" style="display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap;">
                        <select id="product-category-filter" class="form-control" onchange="filterProducts()" style="background-color: #0b0f19; flex: 1; min-width: 150px;">
                            <option value="all">Tüm Kategoriler</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['id']); ?>">
                                    <?php echo htmlspecialchars($cat['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="product-search-filter" class="form-control" onkeyup="filterProducts()" placeholder="Ürün adı ara..." style="flex: 1.5; min-width: 200px; background-color: #0b0f19;">
                    </div>

                    <div class="item-list" id="products-list-container">
                        <?php if (empty($products)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                Henüz ürün eklenmemiş.
                            </div>
                        <?php else: ?>
                            <?php foreach ($products as $prod): ?>
                                <div class="list-item" data-category="<?php echo htmlspecialchars($prod['category_id']); ?>">
                                    <div class="item-left">
                                        <img src="../<?php echo htmlspecialchars($prod['image'] ?: 'assets/categories/category.jpg'); ?>" class="item-img" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                                        <div class="item-details">
                                            <div class="item-name"><?php echo htmlspecialchars($prod['name']); ?></div>
                                            <div style="margin: 4px 0 2px 0;">
                                                <span class="item-subtitle"><?php echo htmlspecialchars($prod['desc']); ?></span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span class="item-badge"><?php echo htmlspecialchars($prod['category_title']); ?></span>
                                                <span style="font-size: 0.8rem; font-weight: 700; color: var(--accent);"><?php echo htmlspecialchars($prod['price']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <button type="button" class="btn btn-secondary btn-sm" title="Düzenle" data-product="<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8'); ?>" onclick="openEditModal(JSON.parse(this.dataset.product))">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <form action="index.php" method="POST" style="margin: 0; display: inline-block;">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="prod_id" value="<?php echo htmlspecialchars($prod['id']); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Sil" onclick="showDeleteModal(event, this, 'Bu ürünü silmek istediğinize emin misiniz?');">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-categories" class="tab-content <?php echo $activeTab === 'categories' ? 'active' : ''; ?>">
            <button type="button" class="btn mobile-toggle-btn" onclick="toggleAddForm('category')">
                <span><i class="fa-solid fa-folder-plus" style="color: var(--primary); margin-right: 6px;"></i> Yeni Kategori Ekle</span>
                <i class="fa-solid fa-chevron-down toggle-arrow"></i>
            </button>
            
            <div class="dashboard-grid">
                <div class="card" id="add-category-card">
                    <h2 class="card-title">
                        <i class="fa-solid fa-folder-plus"></i> Yeni Kategori Ekle
                    </h2>
                    
                    <form action="index.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_category">
                        
                        <div class="form-group">
                            <label class="form-label" for="cat_id">Kategori Kodu (Küçük Harf, İngilizce karakter) *</label>
                            <input type="text" id="cat_id" name="cat_id" class="form-control" placeholder="Örn: pastalar (url/veri eşleşmesi için)" required pattern="[a-z0-9_]+" title="Sadece küçük harfler, sayılar ve alt çizgi. Boşluk kullanmayın.">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="cat_title">Kategori Başlığı (Görünen Başlık) *</label>
                            <input type="text" id="cat_title" name="cat_title" class="form-control" placeholder="Örn: PASTALAR" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="cat_notice">Duyuru / Bilgi Yazısı (İsteğe Bağlı)</label>
                            <input type="text" id="cat_notice" name="cat_notice" class="form-control" placeholder="Örn: Her gün taze hazırlanan doğal dondurmalar.">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kategori Görseli *</label>
                            <div class="file-upload-wrapper">
                                <i class="fa-solid fa-cloud-arrow-up file-upload-icon"></i>
                                <div class="file-upload-text">Bir Görsel Sürükleyin veya Seçin</div>
                                <div class="file-upload-info">PNG, JPG, WEBP (Max 15MB - Otomatik Sıkıştırılacaktır)</div>
                                <input type="file" name="cat_image" accept="image/*" required onchange="displayFilename(this)">
                                <div class="file-chosen-name" style="display:none;"></div>
                            </div>
                        </div>

                        <button type="submit" class="btn" style="margin-top: 10px;">
                            Kategoriyi Ekle <i class="fa-solid fa-check"></i>
                        </button>
                    </form>
                </div>

                <div class="card">
                    <h2 class="card-title">
                        <i class="fa-solid fa-list-ul"></i> Mevcut Kategoriler (<?php echo count($categories); ?>)
                    </h2>

                    <div class="item-list">
                        <?php if (empty($categories)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                Henüz kategori eklenmemiş.
                            </div>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <div class="list-item">
                                    <div class="item-left">
                                        <img src="../<?php echo htmlspecialchars($cat['image'] ?: 'assets/categories/category.jpg'); ?>" class="item-img" alt="<?php echo htmlspecialchars($cat['title']); ?>">
                                        <div class="item-details">
                                            <div class="item-name"><?php echo htmlspecialchars($cat['title']); ?></div>
                                            <div style="margin: 4px 0 2px 0;">
                                                <span style="font-family: monospace; font-size: 0.8rem; background: rgba(255,255,255,0.06); padding: 2px 5px; border-radius: 4px; color: var(--text-muted);">
                                                    Kod: <?php echo htmlspecialchars($cat['id']); ?>
                                                </span>
                                            </div>
                                            <?php if ($cat['notice']): ?>
                                                <span class="item-subtitle" style="font-style: italic;"><i class="fa-solid fa-circle-info"></i> <?php echo htmlspecialchars($cat['notice']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <button type="button" class="btn btn-secondary btn-sm" title="Düzenle" data-category="<?php echo htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8'); ?>" onclick="openEditCatModal(JSON.parse(this.dataset.category))">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <form action="index.php" method="POST" style="margin: 0; display: inline-block;">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="cat_id" value="<?php echo htmlspecialchars($cat['id']); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Sil" onclick="showDeleteModal(event, this, 'BU KATEGORİYİ SİLMEK, KATEGORİYE AİT TÜM ÜRÜNLERİ DE SİLECEKTİR! Devam etmek istediğinize emin misiniz?');">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="admin-footer">
        <p>&copy; 2026 Melipo Cafe Yönetim Paneli. Tüm Hakları Saklıdır.</p>
    </footer>

    <script>
        function openTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.remove('active');
            });
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });
            
            // Show selected tab content and button
            document.getElementById('tab-' + tabId).classList.add('active');
            // Find button and make active
            const btn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.textContent.includes(tabId === 'products' ? 'Ürün' : 'Kategori'));
            if (btn) btn.classList.add('active');
        }

        function displayFilename(input) {
            const wrapper = input.closest('.file-upload-wrapper');
            const label = wrapper.querySelector('.file-chosen-name');
            if (input.files && input.files.length > 0) {
                label.innerHTML = '<i class="fa-solid fa-image"></i> Seçilen Görsel: ' + input.files[0].name;
                label.style.display = 'block';
            } else {
                label.style.display = 'none';
            }
        }

        function filterProducts() {
            const categoryId = document.getElementById('product-category-filter').value;
            const searchVal = document.getElementById('product-search-filter').value.toLowerCase().trim();
            const items = document.querySelectorAll('#products-list-container .list-item');
            
            items.forEach(function(item) {
                const itemCat = item.getAttribute('data-category');
                const itemName = item.querySelector('.item-name').textContent.toLowerCase();
                
                const matchesCat = (categoryId === 'all' || itemCat === categoryId);
                const matchesSearch = (searchVal === '' || itemName.includes(searchVal));
                
                if (matchesCat && matchesSearch) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function openEditModal(product) {
            document.getElementById('edit-prod-id').value = product.id;
            document.getElementById('edit-prod-name').value = product.name;
            document.getElementById('edit-prod-category').value = product.category_id;
            document.getElementById('edit-prod-desc').value = product.desc || '';
            document.getElementById('edit-prod-price').value = product.price;
            document.getElementById('edit-prod-flavors').value = product.flavors || '';
            
            // Clear selected file input preview in modal
            const fileInput = document.querySelector('#edit-product-modal input[type="file"]');
            if (fileInput) {
                fileInput.value = '';
                const label = fileInput.closest('.file-upload-wrapper').querySelector('.file-chosen-name');
                label.style.display = 'none';
            }

            document.getElementById('edit-product-modal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('edit-product-modal').style.display = 'none';
        }

        function openEditCatModal(category) {
            document.getElementById('edit-cat-id').value = category.id;
            document.getElementById('edit-cat-title').value = category.title;
            document.getElementById('edit-cat-notice').value = category.notice || '';
            
            // Clear selected file input preview in modal
            const fileInput = document.querySelector('#edit-category-modal input[type="file"]');
            if (fileInput) {
                fileInput.value = '';
                const label = fileInput.closest('.file-upload-wrapper').querySelector('.file-chosen-name');
                label.style.display = 'none';
            }

            document.getElementById('edit-category-modal').style.display = 'flex';
        }

        function closeEditCatModal() {
            document.getElementById('edit-category-modal').style.display = 'none';
        }

        function toggleAddForm(type) {
            const card = document.getElementById('add-' + type + '-card');
            const btn = document.querySelector('button[onclick="toggleAddForm(\'' + type + '\')"]');
            const arrow = btn.querySelector('.toggle-arrow');
            
            if (card.classList.contains('show-mobile-form')) {
                card.classList.remove('show-mobile-form');
                arrow.classList.remove('fa-chevron-up');
                arrow.classList.add('fa-chevron-down');
                btn.style.borderColor = 'var(--border-color)';
            } else {
                card.classList.add('show-mobile-form');
                arrow.classList.remove('fa-chevron-down');
                arrow.classList.add('fa-chevron-up');
                btn.style.borderColor = 'var(--primary)';
            }
        }

        let formToSubmit = null;
        function showDeleteModal(event, button, message) {
            event.preventDefault();
            formToSubmit = button.closest('form');
            document.getElementById('custom-confirm-message').textContent = message;
            document.getElementById('custom-confirm-modal').style.display = 'flex';
        }
        function handleConfirm(isConfirmed) {
            document.getElementById('custom-confirm-modal').style.display = 'none';
            if (isConfirmed && formToSubmit) {
                formToSubmit.submit();
            }
            formToSubmit = null;
        }
    </script>

    <div id="edit-product-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Ürünü Düzenle</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="prod_id" id="edit-prod-id">
                
                <div class="form-group">
                    <label class="form-label" for="edit-prod-name">Ürün Adı *</label>
                    <input type="text" id="edit-prod-name" name="prod_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-prod-category">Kategori *</label>
                    <select id="edit-prod-category" name="prod_category" class="form-control" required style="background-color: #0b0f19;">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['id']); ?>">
                                <?php echo htmlspecialchars($cat['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-prod-desc">Açıklama</label>
                    <textarea id="edit-prod-desc" name="prod_desc" class="form-control" placeholder="Ürün içeriği"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-prod-price">Fiyat *</label>
                    <input type="text" id="edit-prod-price" name="prod_price" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-prod-flavors">Aroma Seçenekleri (İsteğe Bağlı)</label>
                    <input type="text" id="edit-prod-flavors" name="prod_flavors" class="form-control" placeholder="Örn: Limon, Vişne, Karadut">
                </div>

                <div class="form-group">
                    <label class="form-label">Ürün Görseli (Değiştirmek istemiyorsanız boş bırakın)</label>
                    <div class="file-upload-wrapper">
                        <i class="fa-solid fa-cloud-arrow-up file-upload-icon"></i>
                        <div class="file-upload-text">Yeni Görsel Seç</div>
                        <input type="file" name="prod_image" accept="image/*" onchange="displayFilename(this)">
                        <div class="file-chosen-name" style="display:none;"></div>
                    </div>
                </div>

                <button type="submit" class="btn">
                    Değişiklikleri Kaydet <i class="fa-solid fa-save"></i>
                </button>
            </form>
        </div>
    </div>

    <div id="edit-category-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Kategoriyi Düzenle</h2>
                <button class="modal-close" onclick="closeEditCatModal()">&times;</button>
            </div>
            <form action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="cat_id" id="edit-cat-id">
                
                <div class="form-group">
                    <label class="form-label" for="edit-cat-title">Kategori Başlığı *</label>
                    <input type="text" id="edit-cat-title" name="cat_title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-cat-notice">Duyuru / Bilgi Yazısı (İsteğe Bağlı)</label>
                    <input type="text" id="edit-cat-notice" name="cat_notice" class="form-control" placeholder="Örn: Her gün taze dondurmalar.">
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori Görseli (Değiştirmek istemiyorsanız boş bırakın)</label>
                    <div class="file-upload-wrapper">
                        <i class="fa-solid fa-cloud-arrow-up file-upload-icon"></i>
                        <div class="file-upload-text">Yeni Görsel Seç</div>
                        <input type="file" name="cat_image" accept="image/*" onchange="displayFilename(this)">
                        <div class="file-chosen-name" style="display:none;"></div>
                    </div>
                </div>

                <button type="submit" class="btn">
                    Değişiklikleri Kaydet <i class="fa-solid fa-save"></i>
                </button>
            </form>
        </div>
    </div>
    <!-- Custom Confirmation Modal -->
    <div id="custom-confirm-modal" class="modal" style="display: none; z-index: 10000;">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="justify-content: center; border-bottom: none; margin-bottom: 10px;">
                <h2 class="modal-title" style="color: var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Emin misiniz?</h2>
            </div>
            <p id="custom-confirm-message" style="margin-bottom: 24px; color: var(--text-main); font-size: 0.95rem;"></p>
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button type="button" class="btn btn-secondary" onclick="handleConfirm(false)" style="width: auto; padding: 10px 24px;">İptal</button>
                <button type="button" class="btn" style="width: auto; padding: 10px 24px; background-color: var(--danger);" onclick="handleConfirm(true)">Evet, Sil</button>
            </div>
        </div>
    </div>
</body>
</html>