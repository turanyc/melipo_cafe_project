<?php
// admin/logout.php - Terminate session and redirect to login
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect
header("Location: login.php");
exit;
