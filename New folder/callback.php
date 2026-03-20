<?php
require_once 'config.php';

if (!isset($_GET['code'])) {
    die("No authorization code received.");
}

$code = $_GET['code'];

$postData = [
    'client_id' => $clientId,
    'scope' => $scope,
    'code' => $code,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code',
    'client_secret' => $clientSecret
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['access_token'])) {

    $_SESSION['access_token'] = $result['access_token'];

    // ดึงข้อมูล user
    $ch = curl_init("https://graph.microsoft.com/v1.0/me");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $result['access_token']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $userResponse = curl_exec($ch);
    curl_close($ch);

    $_SESSION['user'] = json_decode($userResponse, true);

    header("Location: dashboard.php");
    exit;
} else {
    echo "Token Error:";
    print_r($result);
}