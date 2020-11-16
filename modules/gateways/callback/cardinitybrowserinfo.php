<?php


//$_SESSION['cardinity_browser_info'] = $_POST['browser_info']['screen_width'];

$name = 'cardinity_browser_info';
$value = base64_encode(serialize($_POST['browser_info']));
$expire = time() + 60 * 5;
$path = ini_get('session.cookie_path');
if ($path == null) {
    $path = '';
}
$domain = ini_get('session.cookie_domain');
if ($domain == null) {
    $domain = '';
}
$httponly = true;
$secure = false;


if (PHP_VERSION_ID < 70300) {
    setcookie(
        $name,
        $value,
        $expire,
        $path,
        $domain,
        $secure,
        $httponly
    );
} else {

    setcookie($name, $value, [
        'expires' => $expire,
        'path' => $path,
        'domain' => $domain,
        'samesite' => $samesite,
        'secure' => $secure,
        'httponly' => $httponly,
    ]);
}

echo "<pre>";
print_r($_POST);
echo "</pre>";
