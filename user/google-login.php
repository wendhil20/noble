<?php
session_name("nobleuser");

session_start();
include '../connection/connect.php'; 
require_once '../vendor/autoload.php';

$tables = ['users'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as total FROM $table");
    $row = $result->fetch_assoc();
    if ((int)$row['total'] === 0) {
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
}

$client = new Google_Client();
$client->setClientId('465138054143-qv9j0hfr0ft416r41qj1qsqvl1u726u0.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/noble/user/google-callback.php');
$client->addScope("email");
$client->addScope("profile");

header('Location: ' . $client->createAuthUrl());
exit;

