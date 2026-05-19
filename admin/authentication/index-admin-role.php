<?php
// index-admin-role.php

const ROLE_SUPERADMIN            = 'superadmin';
const ROLE_SALES                 = 'sales';
const ROLE_PRODUCTSPECIALIST     = 'productspecialist';
const ROLE_SUPPLIER              = 'supplier';
const ROLE_ACCOUNTANT            = 'accountant';
const ROLE_LOGISTIC              = 'logistic';
const ROLE_WAREHOUSE             = 'warehouse';
const ROLE_HR                    = 'Hr';
const POSITION_HEAD              = 'head';
const SUBROLE_WAREHOUSE_STAFF        = 'warehouse_staff';
const SUBROLE_WAREHOUSE_RECEIVER     = 'warehouse_receiver';
const SUBROLE_DOCUMENT_CONTROLLER    = 'document_controller';
const SUBROLE_DISPATCHER             = 'dispatcher';




function require_role(array $roles): void {

    // ✅ Step 1 — Session must be started with correct name
    if (session_status() === PHP_SESSION_NONE) {
        session_name("nobleadmin");
        session_start();
    }

    // ✅ Step 2 — User must be logged in
    if (!isset($_SESSION['noble_user'])) {
        header("Location: " . BASE_URL . "/main");
        exit();
    }

    // ✅ Step 3 — User must have correct role
    if (!isset($_SESSION['noble_lvl']) || !in_array(strtolower($_SESSION['noble_lvl']), array_map('strtolower', $roles))) {
        header("Location: " . BASE_URL . "/main");
        exit();
    }
}

function require_subrole(array $subroles): void {

    // Session check (should already be started by require_role)
    if (session_status() === PHP_SESSION_NONE) {
        session_name("nobleadmin");
        session_start();
    }

    // If empty string is in the allowed subroles array, it means
    // "no subrole required" is also acceptable — allow through
    if (in_array('', $subroles)) {
        return;
    }

    // User must have a subrole set
    if (!isset($_SESSION['noble_subrole'])) {
        header("Location: " . BASE_URL . "/main");
        exit();
    }

    // User's subrole must be in the allowed list
    if (!in_array(strtolower($_SESSION['noble_subrole']), array_map('strtolower', $subroles))) {
        header("Location: " . BASE_URL . "/main");
        exit();
    }
}