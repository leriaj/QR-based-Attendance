<?php
session_start();

const DB_HOST = 'localhost';
const DB_NAME = 'qr_attendance';
const DB_USER = 'root';
const DB_PASS = '';

const MAIL_HOST = 'smtp.gmail.com';
const MAIL_PORT = 587;
const MAIL_SMTP_SECURE = 'tls';
const MAIL_SMTP_AUTH = true;
const MAIL_USERNAME = '';
const MAIL_PASSWORD = '';
const MAIL_FROM = '';
const MAIL_FROM_NAME = 'QR Attendance';

function db() {
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            )
        );
    }

    return $pdo;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
