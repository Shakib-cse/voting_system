<?php
session_start();
require_once '../config/db.php';

// Enforce session authentication
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

try {
    // 1. Get overall counts using UNION ALL
    $total_participants_stmt = $pdo->query("
        SELECT SUM(cnt) FROM (
            SELECT COUNT(*) as cnt FROM participants_9_11
            UNION ALL
            SELECT COUNT(*) as cnt FROM participants_12_14
            UNION ALL
            SELECT COUNT(*) as cnt FROM participants_15_17
        ) as total
    ");
    $total_participants = $total_participants_stmt->fetchColumn() ?: 0;
    
    $total_votes = $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn() ?: 0;

    // Create a view or CTE in query to combine participants
    $participants_cte = "
        SELECT id, username_id, name, email, page_1, page_2, page_3, views, created_at, '9-11' as age_category FROM participants_9_11
        UNION ALL
        SELECT id, username_id, name, email, page_1, page_2, page_3, views, created_at, '12-14' as age_category FROM participants_12_14
        UNION ALL
        SELECT id, username_id, name, email, page_1, page_2, page_3, views, created_at, '15-17' as age_category FROM participants_15_17
    ";

    // 2. Get leader / winning artist details
    $leader_stmt = $pdo->query("
        SELECT p.name, COUNT(v.id) AS vote_count 
        FROM ($participants_cte) p 
        LEFT JOIN votes v ON p.username_id = v.username_id AND v.is_confirmed = 1
        GROUP BY p.username_id 
        ORDER BY vote_count DESC 
        LIMIT 1
    ");
    $leader = $leader_stmt->fetch();
    $winning_artist = ($leader && $leader->vote_count > 0) ? "{$leader->name} ({$leader->vote_count} votes)" : "No votes cast yet";

    // 3. Fetch participants list with counts
    $participants_stmt = $pdo->query("
        SELECT p.*, COUNT(v.id) AS vote_count 
        FROM ($participants_cte) p 
        LEFT JOIN votes v ON p.username_id = v.username_id AND v.is_confirmed = 1
        GROUP BY p.username_id 
        ORDER BY vote_count DESC, p.created_at DESC
    ");
    $participants = $participants_stmt->fetchAll();

    // 4. Fetch recent 50 votes log
    $votes_stmt = $pdo->query("
        SELECT v.*, p.name AS candidate_name 
        FROM votes v 
        JOIN ($participants_cte) p ON v.username_id = p.username_id 
        ORDER BY v.voted_at DESC 
        LIMIT 50
    ");
    $votes_log = $votes_stmt->fetchAll();

    // 5. Audit logs: suspicious IP addresses with more than 1 vote
    $suspicious_ip_stmt = $pdo->query("
        SELECT voter_ip, COUNT(*) AS vote_count 
        FROM votes 
        GROUP BY voter_ip 
        HAVING COUNT(*) > 1 
        ORDER BY vote_count DESC
    ");
    $suspicious_ips = $suspicious_ip_stmt->fetchAll();

    // 6. Handle Delete Action
    if (isset($_POST['delete_item']) && isset($_POST['delete_id']) && isset($_POST['delete_category'])) {
        $del_id = $_POST['delete_id'];
        $del_cat = $_POST['delete_category'];
        $valid_cats = ['9-11', '12-14', '15-17'];
        
        if (in_array($del_cat, $valid_cats)) {
            $del_table = "participants_" . str_replace('-', '_', $del_cat);
            // Start transaction
            $pdo->beginTransaction();
            try {
                // Delete votes for this participant
                $del_votes_stmt = $pdo->prepare("DELETE FROM votes WHERE username_id = ?");
                $del_votes_stmt->execute([$del_id]);
                
                // Delete participant
                $del_part_stmt = $pdo->prepare("DELETE FROM `$del_table` WHERE username_id = ?");
                $del_part_stmt->execute([$del_id]);
                
                $pdo->commit();
                
                // Redirect to refresh page without form resubmission
                header("Location: dashboard.php?msg=deleted");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Failed to delete item: " . $e->getMessage();
            }
        }
    }

} catch (PDOException $e) {
    die("Database stats fetch failed: " . $e->getMessage());
}

// Helper function to resolve paths relative to admin folder
function getAdminImagePath($path) {
    if (empty($path)) return '';
    return '../' . $path;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NK Strip</title>
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
    </style>
</head>
<body>
    <div class="container" style="max-width: 1300px;">
        <!-- ADMIN NAVIGATION -->
        <div class="admin-nav">
            <div>
                <span class="admin-nav-title">NK Strip</span>
                <span class="logo-sub" style="margin-left: 10px; font-size: 0.8rem; vertical-align: middle;">Dashboard</span>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
                <span style="font-weight:600; font-size:0.9rem;">Logged in as: <u><?= htmlspecialchars($_SESSION['admin_user']) ?></u></span>
                <a href="logout.php" class="btn btn-secondary" style="padding: 6px 16px; font-size: 0.85rem; border-width: 2px; box-shadow: 0 2px 0 var(--border-color);">
                    <!-- Logout icon -->
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Logout
                </a>
            </div>
        </div>

        <!-- STATISTICS GRID -->
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $total_participants ?></div>
                <div class="admin-stat-label">Total Participants</div>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $total_votes ?></div>
                <div class="admin-stat-label">Total Votes Cast</div>
            </div>

            <div class="admin-stat-card" style="grid-column: span 2;">
                <div class="admin-stat-value" style="font-size: 1.6rem; line-height: 2.2rem; color: var(--accent-color);">
                    <?= htmlspecialchars($winning_artist) ?>
                </div>
                <div class="admin-stat-label">Current Winner / Leader</div>
            </div>
        </div>

        <!-- PARTICIPANTS LIST SECTION -->
        <h2 class="section-title">
            <!-- Artist icon -->
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path><path d="M12 6v6l4 2"></path></svg>
            Finalist Rankings &amp; Participants
        </h2>
        
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Participant / Title</th>
                        <th>Username ID</th>
                        <th>Age Group</th>
                        <th>Email (Private)</th>
                        <th>Pages</th>
                        <th>Votes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($participants) > 0): ?>
                        <?php 
                        $rank = 1;
                        foreach ($participants as $p): 
                            $pages_available = array_filter([$p->page_1, $p->page_2, $p->page_3]);
                            $page_count = count($pages_available);
                        ?>
                            <tr>
                                <td style="font-family:var(--font-heading); font-weight:900; font-size:1.15rem; color:<?= $rank == 1 ? 'var(--accent-color)' : 'var(--text-main)' ?>;">
                                    #<?= $rank++ ?>
                                </td>
                                <td>
                                    <div class="admin-participant-info">
                                        <div class="admin-participant-thumb" onclick="openComicViewer('<?= rawurlencode(json_encode(array_values(array_map('getAdminImagePath', $pages_available)))) ?>')" style="cursor:pointer;" title="View Pages">
                                            <img src="<?= htmlspecialchars(getAdminImagePath($p->page_1)) ?>" alt="Thumb">
                                        </div>
                                        <div>
                                            <div style="font-weight:700;"><?= htmlspecialchars($p->name) ?></div>
                                            <div style="font-size:0.75rem; color:var(--text-muted);">ID: <?= htmlspecialchars($p->username_id) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-family:monospace;"><?= htmlspecialchars($p->username_id) ?></td>
                                <td>
                                    <span class="admin-badge admin-badge-age"><?= htmlspecialchars($p->age_category) ?></span>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($p->email) ?>" style="color:#096dd9; font-weight:500;"><?= htmlspecialchars($p->email) ?></a>
                                </td>
                                <td style="font-weight:600;"><?= $page_count ?> Page<?= $page_count > 1 ? 's' : '' ?></td>
                                <td>
                                    <span class="admin-badge admin-badge-votes"><?= htmlspecialchars($p->vote_count) ?> votes</span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="voters.php?id=<?= urlencode($p->username_id) ?>&category=<?= urlencode($p->age_category) ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.8rem;">View Voters</a>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this participant and all their votes?');" style="margin: 0;">
                                            <input type="hidden" name="delete_id" value="<?= htmlspecialchars($p->username_id) ?>">
                                            <input type="hidden" name="delete_category" value="<?= htmlspecialchars($p->age_category) ?>">
                                            <button type="submit" name="delete_item" class="btn" style="padding: 4px 8px; font-size: 0.8rem; background-color: #cf1322; color: white; border: none; border-radius: 4px; cursor: pointer;">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">No registered participants yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start;">
            <!-- RECENT VOTES LOG -->
            <div>
                <h2 class="section-title">
                    <!-- Check icon -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Recent Votes (Latest 50)
                </h2>

                <div class="admin-table-container">
                    <table class="admin-table" style="font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th>Voter Email</th>
                                <th>IP Address</th>
                                <th>Voted For</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($votes_log) > 0): ?>
                                <?php foreach ($votes_log as $v): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($v->voter_email) ?></td>
                                        <td style="font-family: monospace;"><?= htmlspecialchars($v->voter_ip) ?></td>
                                        <td style="font-weight:700; color:var(--primary-color);"><?= htmlspecialchars($v->candidate_name) ?></td>
                                        <td style="color:var(--text-muted); font-size:0.8rem;"><?= htmlspecialchars($v->voted_at) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; color:var(--text-muted); padding:20px;">No votes recorded yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECURITY AUDIT / DUPLICATE IPS -->
            <div>
                <h2 class="section-title" style="color: var(--accent-color); border-bottom-color: var(--accent-color);">
                    <!-- Shield Alert icon -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Suspicious IPs (Multiple Votes)
                </h2>

                <div class="admin-table-container">
                    <table class="admin-table" style="font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th>Voter IP Address</th>
                                <th>Vote Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($suspicious_ips) > 0): ?>
                                <?php foreach ($suspicious_ips as $sip): ?>
                                    <tr style="background-color: #fff1f0;">
                                        <td style="font-family: monospace; font-weight: 700; color: var(--accent-color);">
                                            <?= htmlspecialchars($sip->voter_ip) ?>
                                        </td>
                                        <td style="font-weight: 800;">
                                            <span class="admin-badge" style="background:#cf1322; color:#fff; border:1px solid #1a1a1a;">
                                                <?= htmlspecialchars($sip->vote_count) ?> votes
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align:center; color:#389e0d; font-weight:600; padding:20px; background-color:#f6ffed;">
                                        No duplicate IP voters detected. Clean activity logs!
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- COMIC VIEWER MODAL (For viewing full sizes from thumbnails) -->
    <div id="comic-modal" class="modal-overlay">
        <div class="modal-card" style="max-width:650px; padding:25px;">
            <button class="modal-close" onclick="closeComicViewer()">&times;</button>
            <h3 class="modal-title" style="margin-bottom:15px; border:none; padding:0;">Comic Viewer</h3>
            
            <div class="comic-viewer-container">
                <div class="comic-viewer-image-wrapper">
                    <img id="comic-viewer-img" src="" alt="Comic page">
                </div>

                <div class="comic-viewer-controls">
                    <button id="comic-prev" class="btn btn-secondary" onclick="prevComicPage()">
                        &larr; Previous Page
                    </button>
                    <span id="page-indicator" class="page-indicator">Page 1 of 1</span>
                    <button id="comic-next" class="btn btn-secondary" onclick="nextComicPage()">
                        Next Page &rarr;
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/script.js"></script>
</body>
</html>
