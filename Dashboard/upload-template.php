<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Not authenticated"]);
    exit;
}

// Ensure upload directory exists
$targetDir = "uploads/templates/";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['template_file'])) {
    $fileName = basename($_FILES['template_file']['name']);
    $targetFilePath = $targetDir . time() . "_" . $fileName;
    $templateName = $_POST['template_name'] ?? $fileName;

    if (move_uploaded_file($_FILES['template_file']['tmp_name'], $targetFilePath)) {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=LLogin;charset=utf8mb4", "root", "");
            $modeColumn = $pdo->query("SHOW COLUMNS FROM custom_templates LIKE 'render_mode'")->fetch(PDO::FETCH_ASSOC);
            if (!$modeColumn) {
                $pdo->exec("ALTER TABLE custom_templates ADD COLUMN render_mode VARCHAR(20) NOT NULL DEFAULT 'overlay'");
            }
            $renderMode = ($_POST['render_mode'] ?? '') === 'finished' ? 'finished' : 'overlay';
            $stmt = $pdo->prepare("INSERT INTO custom_templates (user_id, template_name, file_path, render_mode) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $templateName, $targetFilePath, $renderMode]);

            echo json_encode(["status" => "success", "message" => "Template uploaded successfully!", "id" => (int) $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Database connection failed: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to move uploaded file."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method or missing file."]);
}
?>