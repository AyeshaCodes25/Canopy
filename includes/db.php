<?php
require_once __DIR__ . '/../config.php';

$mysqli = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$mysqli) {
    die('
        <div style="font-family:sans-serif; max-width:640px; margin:60px auto; padding:24px; border:1px solid #eee; border-radius:10px;">
            <h2 style="color:#B5654F;">Database connection failed</h2>
            <p>' . htmlspecialchars(mysqli_connect_error()) . '</p>
            <p>Checklist:</p>
            <ul>
                <li>Is MySQL running in the XAMPP control panel?</li>
                <li>Did you create the <code>secondbrain</code> database and import <code>schema.sql</code>?</li>
                <li>Do the credentials in <code>config.php</code> match your MySQL setup?</li>
            </ul>
        </div>
    ');
}

mysqli_set_charset($mysqli, 'utf8mb4');
