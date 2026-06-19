<?php
// db.php - Database connection & initialization

$host = 'localhost';
$dbName = 'melipodb';
$username = 'root';
$password = '';

function compressImageBinary($filePath, $maxSizePx = 800, $quality = 70) {
    if (!file_exists($filePath)) {
        return [null, null];
    }

    $imageInfo = @getimagesize($filePath);
    if ($imageInfo === false) {
        return [file_get_contents($filePath), mime_content_type($filePath)];
    }

    $mimeType = $imageInfo['mime'];
    $srcWidth = $imageInfo[0];
    $srcHeight = $imageInfo[1];

    if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
        return [file_get_contents($filePath), $mimeType];
    }

    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
        case 'image/pjpeg':
            $srcImage = @imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
        case 'image/x-png':
            $srcImage = @imagecreatefrompng($filePath);
            break;
        case 'image/webp':
            $srcImage = @imagecreatefromwebp($filePath);
            break;
        case 'image/gif':
            $srcImage = @imagecreatefromgif($filePath);
            break;
        default:
            return [file_get_contents($filePath), $mimeType];
    }

    if (!$srcImage) {
        return [file_get_contents($filePath), $mimeType];
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

    // Compress to WebP (fallback to JPEG) in memory using output buffering
    ob_start();
    $saveSuccess = false;
    $outputType = 'image/jpeg';

    if (function_exists('imagewebp')) {
        $saveSuccess = @imagewebp($dstImage, null, $quality);
        $outputType = 'image/webp';
    }

    if (!$saveSuccess) {
        ob_clean();
        $saveSuccess = @imagejpeg($dstImage, null, $quality);
        $outputType = 'image/jpeg';
    }

    $binaryData = ob_get_clean();

    // Free resources
    imagedestroy($srcImage);
    imagedestroy($dstImage);

    if ($saveSuccess && !empty($binaryData)) {
        return [$binaryData, $outputType];
    }

    return [file_get_contents($filePath), $mimeType];
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create Categories Table (With BLOB column)
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        image VARCHAR(255) NOT NULL,
        image_data LONGBLOB DEFAULT NULL,
        image_type VARCHAR(50) DEFAULT NULL,
        notice TEXT DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create Products Table (With BLOB column)
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT NOT NULL,
        category_id VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        image VARCHAR(255) DEFAULT NULL,
        image_data LONGBLOB DEFAULT NULL,
        image_type VARCHAR(50) DEFAULT NULL,
        `desc` TEXT DEFAULT NULL,
        price VARCHAR(255) NOT NULL,
        flavors VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create Users Table (for Admin access)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT NOT NULL,
        username VARCHAR(50) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Check and dynamically add columns to existing categories table
    try {
        $pdo->query("SELECT image_data FROM categories LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE categories ADD COLUMN image_data LONGBLOB DEFAULT NULL");
        $pdo->exec("ALTER TABLE categories ADD COLUMN image_type VARCHAR(50) DEFAULT NULL");
    }

    // Check and dynamically add columns to existing products table
    try {
        $pdo->query("SELECT image_data FROM products LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE products ADD COLUMN image_data LONGBLOB DEFAULT NULL");
        $pdo->exec("ALTER TABLE products ADD COLUMN image_type VARCHAR(50) DEFAULT NULL");
    }

    // Seed/Update Admin User
    $defaultUser = 'melipo';
    $defaultPass = 'semihmelipo';
    $defaultPassHash = password_hash($defaultPass, PASSWORD_DEFAULT);

    $userCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $userCheck->execute([$defaultUser]);
    $userRow = $userCheck->fetch();

    if (!$userRow) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$defaultUser, $defaultPassHash]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
        $stmt->execute([$defaultPassHash, $defaultUser]);
    }

    // Initialize categories if empty
    $check = $pdo->query("SELECT COUNT(*) FROM categories");
    $catCount = $check->fetchColumn();

    if ($catCount == 0) {
        // Seed Default Categories
        $categories = [
            ['id' => 'baklava', 'title' => 'BAKLAVA ÇEŞİTLERİ', 'file' => 'assets/categories/category.jpg', 'notice' => null],
            ['id' => 'kunefe', 'title' => 'KÜNEFE ÇEŞİTLERİ', 'file' => 'assets/categories/kunefe.jpg', 'notice' => null],
            ['id' => 'ice_cream', 'title' => 'DONDURMA ÇEŞİTLERİ', 'file' => 'assets/categories/dondurma.png', 'notice' => '🍨 Dondurma çeşitlerimiz; doğal malzemeler kullanılarak her gün taze hazırlanan geleneksel el yapımı lezzetlerdir. (İtalyan karameli hariç)'],
            ['id' => 'cakes', 'title' => 'PASTA ÇEŞİTLERİ', 'file' => 'assets/categories/pasta.jpg', 'notice' => null],
            ['id' => 'cookies', 'title' => 'KURABİYELER', 'file' => 'assets/categories/kurabiye.jpg', 'notice' => null],
            ['id' => 'drinks', 'title' => 'SICAK & SOĞUK İÇECEK', 'file' => 'assets/categories/icecek.jpg', 'notice' => null]
        ];

        $catStmt = $pdo->prepare("INSERT INTO categories (id, title, image, image_data, image_type, notice) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($categories as $cat) {
            $file = dirname(__DIR__) . '/melipo cafe/' . $cat['file'];
            list($data, $type) = compressImageBinary($file, 800, 70);
            $imageLink = 'get_image.php?type=category&id=' . $cat['id'];
            
            $catStmt->execute([
                $cat['id'],
                $cat['title'],
                $imageLink,
                $data,
                $type,
                $cat['notice']
            ]);
        }

        // Seed Default Products
        $products = [
            ['baklava', 'FISTIKLI BAKLAVA', 'assets/baklava/fistikli.jpg', 'Antep fıstıklı, kare dilim çıtır baklava.', '₺ 200.00', null],
            ['baklava', 'CEVİZLİ BAKLAVA', 'assets/baklava/cevizli.jpg', 'Bol cevizli, küçük dilim geleneksel lezzet.', '₺ 160.00', null],
            ['baklava', 'HAVUÇ DİLİM BAKLAVA', 'assets/baklava/havuc_dilim.jpg', 'Bol fıstıklı, büyük dilim baklava keyfi.', '₺ 170.00', null],
            ['baklava', 'SOĞUK BAKLAVA', 'assets/baklava/soguk.jpg', 'Sütlü ve kakao kaplamalı hafif baklava.', '₺ 165.00', null],
            ['baklava', 'KADAYIF', 'assets/baklava/kadayif.jpg', 'Geleneksel çıtır tel kadayıf.', '₺ 150.00', null],
            ['baklava', 'ŞÖBİYET', 'assets/baklava/sobiyet.jpg', 'Kaymaklı ve fıstıklı enfes şöbiyet.', '₺ 60.00', null],
            ['kunefe', 'KLASİK KÜNEFE', 'assets/kunefe/klasik.png', 'Özel peyniri ve sıcak şerbetiyle taze pişmiş geleneksel lezzet.', '₺ 150.00', null],
            ['kunefe', 'PAŞA KÜNEFE', 'assets/kunefe/klasik.png', 'Bol fıstıklı ve özel sunumuyla saraylara layık.', '₺ 300.00', null],
            ['kunefe', 'FISTIKZADE', 'assets/kunefe/fistikzade.jpg', 'Tamamen Antep fıstığı kaplı, yoğun lezzet patlaması.', '₺ 300.00 + 50.00', null],
            ['kunefe', 'BİLLURİYE', 'assets/kunefe/billuriye.jpg', 'İnce tel kadayıf ve bol fıstığın eşsiz uyumu.', '₺ 300.00 + 50.00', null],
            ['ice_cream', 'SADE', 'assets/dondurma/sade.jpg', 'Klasik sade vanilyalı dondurma.', '₺ 30.00', null],
            ['ice_cream', 'ÇİKOLATA', 'assets/dondurma/cikolata.jpg', 'Yoğun çikolata lezzeti.', '₺ 30.00', null],
            ['ice_cream', 'BAL BADEM', 'assets/dondurma/balbadem.jpg', 'Kavrulmuş badem ve balın uyumu.', '₺ 30.00', null],
            ['ice_cream', 'ANTEP FISTIĞI', 'assets/dondurma/antepfistigi.jpg', 'Boz iç fıstıklı spesiyal.', '₺ 40.00', null],
            ['ice_cream', 'LİMON', 'assets/dondurma/limon.jpg', 'Ferahlatıcı limon sorbe.', '₺ 30.00', null],
            ['ice_cream', 'BÖĞÜRTLEN', 'assets/dondurma/bogurtlen.jpg', 'Taze böğürtlen parçacıklı.', '₺ 30.00', null],
            ['ice_cream', 'ÇİLEK', 'assets/dondurma/cilek.jpg', 'Doğal çilek aromalı.', '₺ 30.00', null],
            ['ice_cream', 'KARAMEL', 'assets/dondurma/karamel.jpg', 'Karamel tutkunlarına özel.', '₺ 30.00', null],
            ['ice_cream', 'VİŞNE', 'assets/dondurma/visne.jpg', 'Mayhoş ve serinletici vişne.', '₺ 30.00', null],
            ['ice_cream', 'KARADUT', 'assets/dondurma/karadut.jpg', 'Yoğun karadut lezzeti.', '₺ 30.00', null],
            ['ice_cream', 'TİRAMİSU', 'assets/dondurma/tiramisu.jpg', 'İtalyan klasiği tiramisu dondurması.', '₺ 30.00', null],
            ['ice_cream', 'BİTTER', 'assets/dondurma/bitter.jpg', 'Ekstra yoğun bitter çikolata.', '₺ 30.00', null],
            ['ice_cream', 'MANGO', 'assets/dondurma/mango.jpg', 'Tropik mango rüzgarı.', '₺ 30.00', null],
            ['ice_cream', 'SNICKERS', 'assets/dondurma/snikers.jpg', 'Yer fıstığı, karamel ve çikolata.', '₺ 30.00', null],
            ['ice_cream', 'İTALYAN KARAMELİ', 'assets/dondurma/italyan_karameli.png', 'Özel yapım zengin italyan karameli.', '₺ 30.00', null],
            ['ice_cream', 'İNCİR CEVİZ', 'assets/dondurma/incir_ceviz.jpg', 'Kuru incir ve cevizin muhteşem uyumu.', '₺ 30.00', null],
            ['ice_cream', 'MENENGİÇ', 'assets/dondurma/menengic.jpg', 'Geleneksel menengiç kahvesi tadında.', '₺ 30.00', null],
            ['cakes', 'ÇİLEKLİ MOİS', 'assets/pasta/cilekli_mois.png', 'Taze çilekler ve yumuşacık kekin enfes buluşması.', '₺ 150.00', null],
            ['cakes', 'MALAGA', 'assets/pasta/malaga.jpg', 'Muz ve fıstık eşliğinde çikolata kaplı özel pasta.', '₺ 150.00', null],
            ['cakes', 'PROFİTEROL', 'assets/pasta/profiterol.jpg', 'Özel kreması ve çikolata sosuyla klasik lezzet.', '₺ 150.00', null],
            ['cakes', 'SAN SEBASTIAN', 'assets/pasta/san_sebastian.jpg', 'İçi yumuşacık, üstü yanık meşhur Bask keki.', '₺ 200.00', null],
            ['cakes', 'MAGNOLYA', 'assets/pasta/magnolya.jpg', 'Taze çile, bebe bisküvisi ve ipeksi kremanın uyumu.', '₺ 150.00', null],
            ['cakes', 'LOTUSLU MAGNOLYA', 'assets/pasta/lotuslu_magnolya.jpg', 'Lotus Biscoff bisküvisi ve krema eşliğinde Magnolia keyfi.', '₺ 150.00', null],
            ['cakes', 'ORMAN MEYVELİ MAGNOLYA', 'assets/pasta/orman_magnolya.jpg', 'Yaban mersini ve frambuazlı taze Magnolia.', '₺ 150.00', null],
            ['cakes', 'EKLER', 'assets/pasta/ekler.png', 'İçi krema dolgulu, dışı çikolata kaplı lezzet.', '₺ 20.00 / ₺ 35.00', null],
            ['cakes', 'ÇİLEKLİ TART', 'assets/pasta/cilekli_tart.jpg', 'Kıtır tart hamuru ve taze çileklerin uyumu.', '₺ 15.00', null],
            ['cakes', 'FISTIK RÜYASI', 'assets/pasta/fistik_ruyasi.jpg', 'Antep fıstığının yoğun lezzetiyle dolu hayali bir tat.', '₺ 150.00', null],
            ['cookies', 'TUZLU KURABİYE', 'assets/kurabiye/tuzlu.jpg', 'Susamlı ve çörek otlu kıyır kıyır lezzet.', '₺ 15.00 (Adet)', null],
            ['cookies', 'TATLI KURABİYE', 'assets/kurabiye/tatli.jpg', 'Ağızda dağılan, çeşitli aromalarda tatlı kurabiyeler.', '₺ 15.00 (Adet)', null],
            ['cookies', 'ELMALI KURABİYE', 'assets/kurabiye/elmali.jpg', 'Tarçınlı elma harcı ile hazırlanan geleneksel tat.', '₺ 15.00 (Adet)', null],
            ['drinks', 'SU', 'assets/icecek/su.png', 'Ferahlatıcı soğuk su.', '₺ 20.00', null],
            ['drinks', 'ÇAY', 'assets/icecek/cay.png', 'Taze demlenmiş bardak çay.', '₺ 30.00', null],
            ['drinks', 'KUTU KOLA', 'assets/icecek/kola.png', 'Soğuk asitli içecek.', '₺ 60.00', null],
            ['drinks', 'SADE SODA', 'assets/icecek/soda.jpg', 'Doğal mineralli maden suyu.', '₺ 40.00', null],
            ['drinks', 'MEYVELİ SODA', 'assets/icecek/meyveli_soda.jpg', 'Meyve aromalı asitli içecek.', '₺ 40.00', null],
            ['drinks', 'FUSE TEA', 'assets/icecek/ice-tea.png', 'Soğuk buzlu çay keyfi.', '₺ 60.00', null],
            ['drinks', 'TÜRK KAHVESİ', 'assets/icecek/turk_kahvesi.png', 'Bol köpüklü, geleneksel Türk kahvesi.', '₺ 80.00', null],
            ['drinks', 'FİLTRE KAHVE', 'assets/icecek/filtre_kahve.png', 'Taze çekilmiş çekirdeklerden filtre kahve.', '₺ 55.00', null],
            ['drinks', 'ESPRESSO', 'assets/icecek/ekspresso.png', 'Yoğun espresso lezzeti.', '₺ 120.00', null],
            ['drinks', 'CAPPUCCINO', 'assets/icecek/cappucino.png', 'Süt köpüğü ve espressonun uyumu.', '₺ 120.00', null],
            ['drinks', 'AMERICANO', 'assets/icecek/americano.png', 'Yumuşak içimli sıcak americano.', '₺ 120.00', null],
            ['drinks', 'LATTE', 'assets/icecek/latte.png', 'Bol sütlü ve yumuşak içimli kahve.', '₺ 120.00', null],
            ['drinks', 'ICE AMERICANO', 'assets/icecek/ice_americono.png', 'Bol buzlu serinletici americano.', '₺ 120.00', null],
            ['drinks', 'ICE LATTE', 'assets/icecek/ice_latte.png', 'Soğuk süt ve espressonun ferahlatıcı birleşimi.', '₺ 120.00', null]
        ];

        $prodInsert = $pdo->prepare("INSERT INTO products (category_id, name, image, `desc`, price, flavors) VALUES (?, ?, ?, ?, ?, ?)");
        $prodUpdate = $pdo->prepare("UPDATE products SET image = ?, image_data = ?, image_type = ? WHERE id = ?");

        foreach ($products as $prod) {
            $prodInsert->execute([$prod[0], $prod[1], '', $prod[3], $prod[4], $prod[5]]);
            $newId = $pdo->lastInsertId();

            $file = dirname(__DIR__) . '/melipo cafe/' . $prod[2];
            list($data, $type) = compressImageBinary($file, 800, 70);
            $imageLink = 'get_image.php?type=product&id=' . $newId;

            $prodUpdate->execute([$imageLink, $data, $type, $newId]);
        }
    }

    // --- Automatic Active Seeding / Migration Loop ---
    // If there are existing rows pointing to disk assets, read files and upload them to MySQL
    $stmt = $pdo->query("SELECT id, image FROM categories");
    while ($row = $stmt->fetch()) {
        $img = $row['image'];
        if (strpos($img, 'get_image.php') === false && !empty($img)) {
            $file = dirname(__DIR__) . '/melipo cafe/' . $img;
            if (file_exists($file)) {
                list($data, $type) = compressImageBinary($file, 800, 70);
                $imageLink = 'get_image.php?type=category&id=' . $row['id'];

                $update = $pdo->prepare("UPDATE categories SET image = ?, image_data = ?, image_type = ? WHERE id = ?");
                $update->execute([$imageLink, $data, $type, $row['id']]);
            }
        }
    }

    $stmt = $pdo->query("SELECT id, image FROM products");
    while ($row = $stmt->fetch()) {
        $img = $row['image'];
        if (strpos($img, 'get_image.php') === false && !empty($img)) {
            $file = dirname(__DIR__) . '/melipo cafe/' . $img;
            if (file_exists($file)) {
                list($data, $type) = compressImageBinary($file, 800, 70);
                $imageLink = 'get_image.php?type=product&id=' . $row['id'];

                $update = $pdo->prepare("UPDATE products SET image = ?, image_data = ?, image_type = ? WHERE id = ?");
                $update->execute([$imageLink, $data, $type, $row['id']]);
            }
        }
    }

} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
