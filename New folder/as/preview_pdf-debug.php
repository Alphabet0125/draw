<?php
// เปิด error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log ข้อมูล
error_log("PDF Preview Request - ID: " . ($_GET['id'] ?? 'not set'));