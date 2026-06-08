<?php
require __DIR__ . '/db.php';
if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'admin') {
    die('Access denied.');
}

$file = 'README.md';
if (file_exists($file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: text/markdown');
    header('Content-Disposition: attachment; filename="EARIST-Learn-Docs.md"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    readfile($file);
    exit;
}
echo "File not found. Please ensure README.md exists in the project root.";