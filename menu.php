<?php
// menu.php - Melipo Cafe Dynamic Menu
require_once __DIR__ . '/db.php';

// Fetch Categories
try {
    $stmt = $pdo->query("SELECT id, title, image, notice FROM categories ORDER BY title ASC");
    $dbCategories = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbCategories = [];
}

// Fetch Products
try {
    $stmt = $pdo->query("SELECT id, category_id, name, image, `desc`, price, flavors FROM products ORDER BY name ASC");
    $dbProducts = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbProducts = [];
}

// Format menu data for JS consumption (maintaining original script.js structure)
$menuData = [];
foreach ($dbCategories as $cat) {
    $menuData[$cat['id']] = [
        'title' => $cat['title'],
        'image' => $cat['image'],
        'notice' => $cat['notice'],
        'products' => []
    ];
}

foreach ($dbProducts as $prod) {
    if (isset($menuData[$prod['category_id']])) {
        $prodItem = [
            'name' => $prod['name'],
            'image' => $prod['image'],
            'desc' => $prod['desc'],
            'price' => $prod['price']
        ];
        
        if (!empty($prod['flavors'])) {
            // Split comma-separated flavors into an array of strings
            $prodItem['flavors'] = array_map('trim', explode(',', $prod['flavors']));
        }
        
        $menuData[$prod['category_id']]['products'][] = $prodItem;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="only light">
    <title>Melipo Cafe Menü</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <!-- Modern Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=3">
    
    <!-- Pass dynamic PHP database data directly to client-side JS -->
    <script>
        window.menuData = <?php echo json_encode($menuData, JSON_UNESCAPED_UNICODE); ?>;
    </script>
</head>

<body>
    <div class="app-container">

        <!-- Loading Screen -->
        <div id="loading-screen" class="loading-screen">
            <div class="floating-images">
                <img src="assets/loading/coffee.jpg" class="mini-img coffee-img" alt="Coffee">
                <img src="assets/loading/baklava.jpg" class="mini-img baklava-img" alt="Baklava">
                <img src="assets/pasta/cilekli_tart.jpg" class="mini-img pasta-img" alt="Pasta">
                <img src="assets/dondurma/incir_ceviz.jpg" class="mini-img dondurma-img" alt="Dondurma">
                <img src="assets/icecek/cappucino.png" class="mini-img icecek-img" alt="İçecek">
            </div>
            <div class="loading-content">
                <h1 class="loading-title">Melipo</h1>
                <div class="loading-subtitle">Patisserie & Coffee</div>
                <div class="loading-bar-container">
                    <div class="loading-bar"></div>
                </div>
            </div>
        </div>

        <!-- Header -->
        <header class="app-header">
            <div id="headerLeft" class="header-left-area"></div>
            <div id="headerTitle" class="header-title">
            </div>
            <div style="width: 40px;"></div> <!-- Spacer -->
        </header>

        <div id="home-view">
            <img src="assets/menu.png" alt="Menu Banner" style="width: 100%; display: block; margin: 0; padding: 0;">
            <!-- Welcome Hero Section -->
            <div class="welcome-section">
                <div class="welcome-text-group">
                    <h2 class="welcome-title-text">Melipo</h2>
                    <span class="welcome-subtitle-text">Patisserie & Coffee</span>
                </div>
                <a href="https://www.instagram.com/melipo_kozan/" target="_blank" rel="noopener noreferrer" class="instagram-link" aria-label="Instagram">
                    <i class="fa-brands fa-instagram"></i>
                </a>
            </div>

            <!-- Main Content -->
            <main class="main-content" style="position: relative; z-index: 2;">
                <!-- Options Grid (Dynamically rendered from PHP Database) -->
                <div class="category-grid">
                    <?php if (empty($dbCategories)): ?>
                        <div style="grid-column: span 2; text-align: center; padding: 40px; color: var(--text-muted);">
                            Menü yükleniyor...
                        </div>
                    <?php else: ?>
                        <?php foreach ($dbCategories as $cat): ?>
                            <a href="#" class="category-card" data-category="<?php echo htmlspecialchars($cat['id']); ?>">
                                <div class="card-img-container">
                                    <img src="<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['title']); ?>">
                                </div>
                                <div class="card-overlay"></div>
                                <div class="card-content">
                                    <h3 class="card-label"><?php echo htmlspecialchars($cat['title']); ?></h3>
                                    <span class="card-action-btn"><i class="fa-solid fa-chevron-right"></i></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>

        </div>

        <!-- Products View -->
        <div id="products-view" style="display: none;">
            <div class="product-list" id="productList">
                <!-- Javascript will render products here dynamically -->
            </div>
        </div>

        <!-- Footer -->
        <footer class="app-footer">
            <div class="footer-content">
                <p>&copy; 2026 Melipo Cafe Tüm Hakları Saklıdır. | <a href="admin/index.php" style="color: inherit; text-decoration: none;"><i class="fa-solid fa-lock" style="font-size:0.75rem; opacity:0.6; margin-right:2px;"></i> Yönetim</a></p>
            </div>
        </footer>

    </div>

    <script src="script.js?v=23"></script>
</body>

</html>
