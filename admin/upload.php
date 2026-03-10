<?php
require 'auth.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image_file'], $_POST['image_key'])) {

    $key = $_POST['image_key'];
    $allowed_keys = ['about_img_1', 'about_img_2'];

    if (!in_array($key, $allowed_keys)) {
        die("Invalid image key.");
    }

    $file = $_FILES['image_file'];

    // Validación completa: error PHP, tamaño, extensión y MIME real con fileinfo
    $ext = validarImagenSubida($file);

    // Generate unique name (ext ya validada por validarImagenSubida)
    $filename = $key . '_' . time() . '.' . $ext;
    $targetDir = '../imghome/uploads/';
    $targetFile = $targetDir . $filename;

    // Relative path for frontend usage (from index.html perspective)
    $relativePath = 'imghome/uploads/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        // Update JSON config
        $configFile = '../data/homepage_config.json';
        $config = json_decode(file_get_contents($configFile), true);

        // Remove old file if it exists and is in uploads folder (optional cleanup)
        // ... (Skipping cleanup for simplicity now, but mindful of disk space)

        $config[$key] = $relativePath;

        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

        header("Location: dashboard.php?success=Image updated successfully!");
        exit;
    } else {
        header("Location: dashboard.php?error=Failed to move uploaded file.");
        exit;
    }

} else {
    header("Location: dashboard.php");
    exit;
}
?>