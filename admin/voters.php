<?php
session_start();
require_once '../config/db.php';

// Enforce session authentication
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['category']) || empty($_GET['category'])) {
    die("Participant ID or Category not provided.");
}

$username_id = $_GET['id'];
$category = $_GET['category'];

$valid_categories = ['9-11', '12-14', '15-17'];
if (!in_array($category, $valid_categories)) {
    die("Invalid category.");
}

$table_name = "participants_" . str_replace('-', '_', $category);

try {
    // 1. Fetch participant details
    $stmt = $pdo->prepare("SELECT * FROM `$table_name` WHERE username_id = ?");
    $stmt->execute([$username_id]);
    $participant = $stmt->fetch();

    if (!$participant) {
        die("Participant not found in category $category.");
    }

    // 2. Fetch voters for this participant
    $voters_stmt = $pdo->prepare("
        SELECT * FROM votes 
        WHERE username_id = ? 
        ORDER BY voted_at DESC
    ");
    $voters_stmt->execute([$username_id]);
    $voters = $voters_stmt->fetchAll();

    // 3. Find duplicate IPs for this specific participant to flag them
    $duplicate_ips = [];
    $ip_counts = array_count_values(array_column($voters, 'voter_ip'));
    foreach ($ip_counts as $ip => $count) {
        if ($count > 1) {
            $duplicate_ips[] = $ip;
        }
    }

} catch (PDOException $e) {
    die("Database query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voters List - <?= htmlspecialchars($participant->name) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .section-title {
            font-family: var(--font-heading);
            font-weight: 900;
            font-size: 1.5rem;
            text-transform: uppercase;
            margin: 40px 0 20px 0;
            border-bottom: 3px solid var(--border-color);
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .highlight-duplicate {
            background-color: #fff1f0;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1000px;">
        <!-- ADMIN NAVIGATION -->
        <div class="admin-nav">
            <div>
                <span class="admin-nav-title"><a href="dashboard.php" style="color: inherit; text-decoration: none;">&larr; Back to Dashboard</a></span>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
                <span style="font-weight:600; font-size:0.9rem;">Logged in as: <u><?= htmlspecialchars($_SESSION['admin_user']) ?></u></span>
                <a href="logout.php" class="btn btn-secondary" style="padding: 6px 16px; font-size: 0.85rem; border-width: 2px; box-shadow: 0 2px 0 var(--border-color);">
                    Logout
                </a>
            </div>
        </div>

        <h2 class="section-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            Voters for <?= htmlspecialchars($participant->name) ?> (@<?= htmlspecialchars($participant->username_id) ?>)
        </h2>
        
        <div style="margin-bottom: 20px; font-size: 1.1rem;">
            Total Votes: <strong><?= count($voters) ?></strong>
        </div>

        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Voter Email</th>
                        <th>Voter Name</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($voters) > 0): ?>
                        <?php 
                        $counter = 1;
                        foreach ($voters as $v): 
                            $is_duplicate = in_array($v->voter_ip, $duplicate_ips);
                        ?>
                            <tr class="<?= $is_duplicate ? 'highlight-duplicate' : '' ?>">
                                <td><?= $counter++ ?></td>
                                <td style="font-weight: 600;"><?= htmlspecialchars($v->voter_email) ?></td>
                                <td><?= htmlspecialchars($v->voter_name ?? 'N/A') ?></td>
                                <td style="font-family: monospace; <?= $is_duplicate ? 'color: #cf1322; font-weight: bold;' : '' ?>">
                                    <?= htmlspecialchars($v->voter_ip) ?>
                                </td>
                                <td><?= htmlspecialchars($v->voted_at) ?></td>
                                <td>
                                    <?php if ($v->is_confirmed == 0): ?>
                                        <span class="admin-badge" style="background:#faad14; color:#fff;">Pending</span>
                                    <?php elseif ($is_duplicate): ?>
                                        <span class="admin-badge" style="background:#cf1322; color:#fff;">Duplicate IP Warning</span>
                                    <?php else: ?>
                                        <span class="admin-badge" style="background:#52c41a; color:#fff;">Valid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:var(--text-muted); padding:30px;">No votes recorded for this participant yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
