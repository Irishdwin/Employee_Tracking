<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
header('Content-Type: application/json');

function json_response($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function ensure_industry_lookup_table(mysqli $conn): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS industry_lookup (
            IndustryID INT(11) NOT NULL AUTO_INCREMENT,
            Name VARCHAR(100) NOT NULL,
            CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (IndustryID),
            UNIQUE KEY uniq_industry_name (Name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    if (!$conn->query($sql)) {
        json_response(['success' => false, 'message' => 'Failed to initialize industry lookup table.'], 500);
    }

    // Seed from existing industries so dropdown is immediately useful.
    $res = $conn->query("SELECT DISTINCT TRIM(Industry) AS IndustryName FROM clientcompany WHERE Industry IS NOT NULL AND TRIM(Industry) <> ''");
    if ($res) {
        $stmt = $conn->prepare("INSERT IGNORE INTO industry_lookup (Name) VALUES (?)");
        if ($stmt) {
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)$row['IndustryName']);
                if ($name === '') continue;
                $stmt->bind_param("s", $name);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
}
ensure_industry_lookup_table($conn);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $res = $conn->query("SELECT IndustryID, Name FROM industry_lookup ORDER BY Name ASC");
    $items = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[] = ['id' => (int)$row['IndustryID'], 'name' => $row['Name']];
        }
    }
    json_response(['success' => true, 'industries' => $items]);
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action !== 'create') {
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        json_response(['success' => false, 'message' => 'Industry name is required.'], 400);
    }

    $stmt = $conn->prepare("INSERT INTO industry_lookup (Name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $newId = $conn->insert_id;
    $stmt->close();

    if ($ok) {
        json_response(['success' => true, 'message' => 'Industry added.', 'industry' => ['id' => (int)$newId, 'name' => $name]]);
    }

    // Handle duplicate gracefully (unique constraint)
    if (stripos($err, 'Duplicate') !== false) {
        json_response(['success' => true, 'message' => 'Industry already exists.', 'industry' => ['id' => 0, 'name' => $name]]);
    }

    json_response(['success' => false, 'message' => 'Database error: ' . $err], 500);
}

json_response(['success' => false, 'message' => 'Invalid request method.'], 405);

