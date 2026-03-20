<?php
session_start();

$tenantId = "8fcd076a-5127-4b29-bc89-a06149b49b7d";
$clientId = "16255bc6-8828-4a48-be7f-40891b3b8336";
$clientSecret = "Me58Q~651Yz9sj6HUn6UpTPSx-2rHo_HIPCXFaL0";
$redirectUri = "https://service.lolane.com/test.php";

$authorizeUrl = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/authorize";
$tokenUrl = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";
$scope = "openid profile email https://graph.microsoft.com/.default";
?>