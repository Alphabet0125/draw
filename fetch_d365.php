<?php
/**
 * ดึงข้อมูลจาก Dynamics 365 ของ Lolane
 * ใช้ Client Credentials flow สำหรับ server-to-server
 * หรือ On-Behalf-Of flow สำหรับ user context
 */
require_once 'config.php';
requireLogin();

/**
 * ขอ Access Token สำหรับ D365
 * ใช้ On-Behalf-Of (OBO) flow เพื่อแลก Graph token เป็น D365 token
 */
function getD365Token(): ?string
{
    global $tokenUrl, $clientId, $clientSecret, $d365Scope;

    // ใช้ Client Credentials flow (server-to-server)
    $postData = [
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'scope'         => $d365Scope,
        'grant_type'    => 'client_credentials',
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("D365 token failed: {$response}");
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * เรียก D365 Web API
 */
function callD365Api(string $endpoint, string $token, array $params = []): ?array
{
    global $d365BaseUrl;

    $url = $d365BaseUrl . '/api/data/v9.2/' . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            'OData-MaxVersion: 4.0',
            'OData-Version: 4.0',
            'Content-Type: application/json',
            'Accept: application/json',
            'Prefer: odata.include-annotations="*"',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("D365 API Error (HTTP {$httpCode}): {$response}");
        return null;
    }

    return json_decode($response, true);
}

// ===== ตัวอย่างการดึงข้อมูลจาก D365 =====

$d365Token = getD365Token();

if ($d365Token) {
    // ดึงข้อมูล Contacts
    $contacts = callD365Api('contacts', $d365Token, [
        '$select' => 'fullname,emailaddress1,telephone1,jobtitle,department',
        '$top'    => 50,
        '$orderby'=> 'fullname asc',
    ]);

    // ดึงข้อมูล Accounts (บริษัท/ลูกค้า)
    $accounts = callD365Api('accounts', $d365Token, [
        '$select' => 'name,telephone1,emailaddress1,address1_city,revenue',
        '$top'    => 50,
    ]);

    // ดึงข้อมูล Products
    $products = callD365Api('products', $d365Token, [
        '$select' => 'name,productnumber,price,description',
        '$top'    => 100,
    ]);

    // ดึง Sales Orders
    $salesOrders = callD365Api('salesorders', $d365Token, [
        '$select' => 'name,ordernumber,totalamount,submitdate',
        '$top'    => 50,
        '$orderby'=> 'submitdate desc',
    ]);
}

// Return as JSON API
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'  => $d365Token !== null,
    'user'     => $_SESSION['display_name'],
    'data'     => [
        'contacts'     => $contacts['value']    ?? [],
        'accounts'     => $accounts['value']    ?? [],
        'products'     => $products['value']    ?? [],
        'sales_orders' => $salesOrders['value'] ?? [],
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);