<?php
header('Content-Type: application/json');
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$username_id = trim($_POST['username_id'] ?? '');
$voter_email = trim($_POST['voter_email'] ?? '');

// 1. Basic validation
if (empty($username_id) || empty($voter_email)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please provide both your email and select a participant.'
    ]);
    exit;
}

if (!filter_var($voter_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please enter a valid email address.'
    ]);
    exit;
}

try {
    // 2. Verify participant exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM participants WHERE username_id = ?");
    $stmt->execute([$username_id]);
    if ($stmt->fetchColumn() == 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Selected participant does not exist.'
        ]);
        exit;
    }

    // 3. Verify voter has not already voted
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE voter_email = ?");
    $stmt->execute([$voter_email]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'You have already voted! Only one vote is allowed per email address.'
        ]);
        exit;
    }

    // 4. Capture Voter IP (securely check proxy and fallback to remote addr)
    $voter_ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // HTTP_X_FORWARDED_FOR can contain a list of IPs; take the first one
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $voter_ip = trim($ips[0]);
    }

    // 5. Insert vote into database
    $stmt = $pdo->prepare("INSERT INTO votes (username_id, voter_email, voter_ip) VALUES (?, ?, ?)");
    $stmt->execute([$username_id, $voter_email, $voter_ip]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Thank you! Your vote has been successfully recorded.'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'A database error occurred: ' . $e->getMessage()
    ]);
    exit;
}
?>
