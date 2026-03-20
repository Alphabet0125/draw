<?php

$secret = "mysecret123";

$headers = getallheaders();

if (!isset($headers['X-Hub-Signature-256'])) {
    http_response_code(403);
    exit("No signature");
}

$payload = file_get_contents("php://input");

file_put_contents("deploy.log", date("Y-m-d H:i:s")." deploy\n", FILE_APPEND);

exec("C:\\inetpub\\Service\\deploy.bat");

echo "Deploy OK";

?>