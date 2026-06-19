<?php
// admin/login.php - Admin Login Interface
session_start();

// Include database connection
require_once __DIR__ . '/../db.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Lütfen kullanıcı adı ve şifrenizi girin.';
    } elseif (empty($turnstileToken)) {
        $error = 'Lütfen güvenlik doğrulamasını tamamlayın.';
    } else {
        // Verify with Cloudflare Turnstile API
        $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $secretKey = '1x0000000000000000000000000000000AA'; // Dummy key for localhost testing
        
        $postData = [
            'secret' => $secretKey,
            'response' => $turnstileToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $apiResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($apiResponse, true);
        
        if ($httpCode !== 200 || !$result || !isset($result['success']) || !$result['success']) {
            $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
        } else {
            try {
                // Find user in database
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Successful login
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $user['username'];
                    
                    header("Location: index.php");
                    exit;
                } else {
                    $error = 'Hatalı kullanıcı adı veya şifre!';
                }
            } catch (PDOException $e) {
                $error = 'Veritabanı hatası: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli Giriş - Melipo Cafe</title>
    <link rel="icon" type="image/png" href="../assets/logo.png">
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Cloudflare Turnstile API -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <!-- Admin Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <a href="../menu">
                <img src="../assets/logo.png" alt="Melipo" class="auth-logo">
            </a>
            <h1 class="auth-title">Yönetim Paneli</h1>
            <p class="auth-subtitle">Yönetici paneline erişmek için giriş yapın</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="username" class="form-label">Kullanıcı Adı</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="admin" required autofocus>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="password" class="form-label">Şifre</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <!-- Turnstile Widget -->
                <div class="form-group" style="display: flex; justify-content: center; margin-bottom: 24px;">
                    <div class="cf-turnstile" data-sitekey="1x00000000000000000000AA" data-theme="dark"></div>
                </div>

                <button type="submit" class="btn">
                    Giriş Yap <i class="fa-solid fa-arrow-right-to-bracket"></i>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
