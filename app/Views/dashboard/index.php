<?php
if (isset($data['role']) && $data['role'] === 'kasir') {
    require_once __DIR__ . '/kasir.php';
} else {
    require_once __DIR__ . '/admin.php';
}
