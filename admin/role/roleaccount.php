<?php
session_start();

if (!isset($_SESSION['noble_user']) || !isset($_SESSION['noble_lvl'])) {
    $_SESSION['access_denied'] = "You must login first.";
    header("Location: ../../loginpage/index.php"); // redirect or exit to prevent continuing
    exit;
}

function require_role($allowed_roles = []) {
    if (!in_array($_SESSION['noble_lvl'], $allowed_roles)) {
        $_SESSION['access_denied'] = "You don't have permission to access this section.";
        exit;
    }
    return true;
}


