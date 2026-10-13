<?php
require __DIR__ . '/config.php';

$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;
$token = strtolower(trim(isset($_GET['token']) ? $_GET['token'] : ''));

if (!$id || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    exit('Pass not found.');
}

$stmt = db()->prepare('SELECT qr_token FROM profiles WHERE id = ? LIMIT 1');
$stmt->execute(array($id));
$profile = $stmt->fetch();

if (!$profile || !hash_equals($profile['qr_token'], $token)) {
    http_response_code(404);
    exit('Pass not found.');
}

$path = __DIR__ . '/uploads/qr/profile_' . (int) $id . '_pass.pdf';

if (!is_file($path)) {
    http_response_code(404);
    exit('Pass not found.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="attendance-qr.pdf"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
readfile($path);