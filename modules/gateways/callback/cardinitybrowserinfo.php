<?php

$name = 'cardinity_browser_info';
//$value = base64_encode(serialize($_POST['browser_info']));


//stringify the parameters
$browser_info_string = implode("",$_POST['browser_info']);
$signature = hash_hmac('sha256', $browser_info_string, $_SERVER['HTTP_USER_AGENT']);

//add signature to array
$securedCookieArray = $_POST['browser_info'];
$securedCookieArray['signature'] = $signature;

//convert to json
$securedCookieJson = base64_encode(json_encode($securedCookieArray));


$value = $securedCookieJson;


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
$samesite = 'None';


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
