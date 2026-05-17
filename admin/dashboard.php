<?php
session_start();
require_once '../config/db.php';

// Enforce session authentication
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// --------------------------------------------------------
// POST ACTIONS: ADD, EDIT, DELETE FINALISTS (CRUD)
// --------------------------------------------------------

$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: ADD FINALIST
    if ($_POST['action'] === 'add_finalist') {
        $name = trim($_POST['name'] ?? '');
        $username_id = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($_POST['username_id'] ?? '')));
        $age_category = trim($_POST['age_category'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($name) || empty($username_id) || empty($age_category) || empty($email) || empty($_FILES['page_1']['name'])) {
            $_SESSION['error_msg'] = 'Name, Username ID, Email, Category, and Page 1 image are required!';
        } else {
            // Check Username ID uniqueness across all category tables
            $is_taken = false;
            foreach (['9_11', '12_14', '15_17'] as $cat_tbl) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM participants_$cat_tbl WHERE username_id = ?");
                $stmt->execute([$username_id]);
                if ($stmt->fetchColumn() > 0) {
                    $is_taken = true;
                    break;
                }
            }
            
            if ($is_taken) {
                $_SESSION['error_msg'] = "The Username ID '<strong>$username_id</strong>' is already taken. Please choose another one.";
            } else {
                $upload_dir = '../uploads/images/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $uploaded_files = ['page_1' => '', 'page_2' => null, 'page_3' => null];
                $upload_ok = true;
                
                foreach (['page_1', 'page_2', 'page_3'] as $page_key) {
                    if (empty($_FILES[$page_key]['name'])) continue;
                    
                    $file_name = $_FILES[$page_key]['name'];
                    $file_tmp = $_FILES[$page_key]['tmp_name'];
                    $file_size = $_FILES[$page_key]['size'];
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    if (!in_array($ext, $allowed_extensions) || $file_size > $max_size) {
                        $upload_ok = false;
                        $_SESSION['error_msg'] = "Failed to upload image $page_key. Ensure it is JPG/PNG/GIF and under 5MB.";
                        break;
                    }
                    
                    $new_filename = $username_id . '_' . $page_key . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $target_filepath = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $target_filepath)) {
                        $uploaded_files[$page_key] = 'uploads/images/' . $new_filename;
                    } else {
                        $upload_ok = false;
                        $_SESSION['error_msg'] = "Failed saving uploaded image file $page_key.";
                        break;
                    }
                }
                
                if ($upload_ok) {
                    $tbl = "participants_" . str_replace('-', '_', $age_category);
                    $stmt = $pdo->prepare("INSERT INTO $tbl (username_id, name, email, page_1, page_2, page_3) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $username_id,
                        $name,
                        $email,
                        $uploaded_files['page_1'],
                        $uploaded_files['page_2'],
                        $uploaded_files['page_3']
                    ]);
                    $_SESSION['success_msg'] = "Finalist <strong>$name</strong> added successfully!";
                }
            }
        }
        header('Location: dashboard.php');
        exit;
    }
    
    // ACTION: EDIT FINALIST (MANAGE)
    if ($_POST['action'] === 'edit_finalist') {
        $original_username_id = trim($_POST['original_username_id'] ?? '');
        $age_category = trim($_POST['age_category'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($original_username_id) || empty($age_category) || empty($name) || empty($email)) {
            $_SESSION['error_msg'] = 'Name, Email and Category are required!';
        } else {
            $tbl = "participants_" . str_replace('-', '_', $age_category);
            
            // Check if finalist exists
            $stmt = $pdo->prepare("SELECT * FROM $tbl WHERE username_id = ?");
            $stmt->execute([$original_username_id]);
            $finalist = $stmt->fetch();
            
            if (!$finalist) {
                $_SESSION['error_msg'] = 'Finalist not found!';
            } else {
                $upload_dir = '../uploads/images/';
                $uploaded_files = ['page_1' => $finalist->page_1, 'page_2' => $finalist->page_2, 'page_3' => $finalist->page_3];
                $upload_ok = true;
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $max_size = 5 * 1024 * 1024;
                
                foreach (['page_1', 'page_2', 'page_3'] as $page_key) {
                    if (empty($_FILES[$page_key]['name'])) continue;
                    
                    $file_name = $_FILES[$page_key]['name'];
                    $file_tmp = $_FILES[$page_key]['tmp_name'];
                    $file_size = $_FILES[$page_key]['size'];
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    if (!in_array($ext, $allowed_extensions) || $file_size > $max_size) {
                        $upload_ok = false;
                        $_SESSION['error_msg'] = "Invalid upload for $page_key. Ensure JPG/PNG/GIF and under 5MB.";
                        break;
                    }
                    
                    // Delete old file
                    if ($finalist->$page_key && file_exists('../' . $finalist->$page_key)) {
                        @unlink('../' . $finalist->$page_key);
                    }
                    
                    $new_filename = $original_username_id . '_' . $page_key . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $target_filepath = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $target_filepath)) {
                        $uploaded_files[$page_key] = 'uploads/images/' . $new_filename;
                    } else {
                        $upload_ok = false;
                        $_SESSION['error_msg'] = "Failed saving replaced image $page_key.";
                        break;
                    }
                }
                
                if ($upload_ok) {
                    $stmt = $pdo->prepare("UPDATE $tbl SET name = ?, email = ?, page_1 = ?, page_2 = ?, page_3 = ? WHERE username_id = ?");
                    $stmt->execute([
                        $name,
                        $email,
                        $uploaded_files['page_1'],
                        $uploaded_files['page_2'],
                        $uploaded_files['page_3'],
                        $original_username_id
                    ]);
                    $_SESSION['success_msg'] = "Finalist <strong>$name</strong> updated successfully!";
                }
            }
        }
        header('Location: dashboard.php');
        exit;
    }
    
    // ACTION: DELETE FINALIST
    if ($_POST['action'] === 'delete_finalist') {
        $username_id = trim($_POST['username_id'] ?? '');
        $age_category = trim($_POST['age_category'] ?? '');
        
        if (empty($username_id) || empty($age_category)) {
            $_SESSION['error_msg'] = 'Finalist identifiers are missing!';
        } else {
            $tbl = "participants_" . str_replace('-', '_', $age_category);
            
            $stmt = $pdo->prepare("SELECT * FROM $tbl WHERE username_id = ?");
            $stmt->execute([$username_id]);
            $finalist = $stmt->fetch();
            
            if ($finalist) {
                // Delete image files
                foreach (['page_1', 'page_2', 'page_3'] as $page_key) {
                    if ($finalist->$page_key && file_exists('../' . $finalist->$page_key)) {
                        @unlink('../' . $finalist->$page_key);
                    }
                }
                
                // Delete from DB
                $stmt = $pdo->prepare("DELETE FROM $tbl WHERE username_id = ?");
                $stmt->execute([$username_id]);
                
                // Delete related votes
                $stmt = $pdo->prepare("DELETE FROM votes WHERE username_id = ?");
                $stmt->execute([$username_id]);
                
                $_SESSION['success_msg'] = "Finalist <strong>" . htmlspecialchars($finalist->name) . "</strong> and all their files & votes deleted successfully!";
            } else {
                $_SESSION['error_msg'] = 'Finalist not found!';
            }
        }
        header('Location: dashboard.php');
        exit;
    }
}

// --------------------------------------------------------
// RETRIEVE DATA & STATS
// --------------------------------------------------------

try {
    // 1. Get overall finalist count
    $total_participants = $pdo->query("
        SELECT (SELECT COUNT(*) FROM participants_9_11) + 
               (SELECT COUNT(*) FROM participants_12_14) + 
               (SELECT COUNT(*) FROM participants_15_17) AS total
    ")->fetch()->total;

    // 2. Get vote breakdown
    $total_votes = $pdo->query("SELECT COUNT(*) FROM votes WHERE is_confirmed = 1")->fetchColumn();
    $pending_votes = $pdo->query("SELECT COUNT(*) FROM votes WHERE is_confirmed = 0")->fetchColumn();

    // 3. Get leaderboard winner (Overall lead among confirmed votes)
    $leader_stmt = $pdo->query("
        SELECT p.name, p.age_category, COUNT(v.id) AS vote_count 
        FROM (
            SELECT '9-11' AS age_category, username_id, name FROM participants_9_11
            UNION ALL
            SELECT '12-14' AS age_category, username_id, name FROM participants_12_14
            UNION ALL
            SELECT '15-17' AS age_category, username_id, name FROM participants_15_17
        ) p 
        LEFT JOIN votes v ON p.username_id = v.username_id AND v.is_confirmed = 1
        GROUP BY p.username_id 
        ORDER BY vote_count DESC 
        LIMIT 1
    ");
    $leader = $leader_stmt->fetch();
    $winning_artist = ($leader && $leader->vote_count > 0) ? "{$leader->name} ({$leader->vote_count} confirmed votes)" : "No confirmed votes cast yet";

    // 4. Fetch finalists ranking list (View order in voted items)
    $participants_stmt = $pdo->query("
        SELECT p.*, COUNT(v.id) AS vote_count 
        FROM (
            SELECT '9-11' AS age_category, id, username_id, name, email, page_1, page_2, page_3, views, created_at FROM participants_9_11
            UNION ALL
            SELECT '12-14' AS age_category, id, username_id, name, email, page_1, page_2, page_3, views, created_at FROM participants_12_14
            UNION ALL
            SELECT '15-17' AS age_category, id, username_id, name, email, page_1, page_2, page_3, views, created_at FROM participants_15_17
        ) p 
        LEFT JOIN votes v ON p.username_id = v.username_id AND v.is_confirmed = 1
        GROUP BY p.username_id 
        ORDER BY vote_count DESC, p.id DESC
    ");
    $participants = $participants_stmt->fetchAll();

    // 5. Search & filter parameters for votes log
    $search_query = trim($_GET['search'] ?? '');
    $filter_category = trim($_GET['filter_category'] ?? '');
    $filter_status = trim($_GET['filter_status'] ?? '');

    // Construct dynamically filtered query for votes
    $v_sql = "SELECT v.*, 
           (SELECT name FROM (
               SELECT username_id, name FROM participants_9_11
               UNION ALL
               SELECT username_id, name FROM participants_12_14
               UNION ALL
               SELECT username_id, name FROM participants_15_17
           ) AS all_p WHERE all_p.username_id = v.username_id LIMIT 1) AS candidate_name 
           FROM votes v WHERE 1=1";
    
    $v_params = [];
    
    if (!empty($search_query)) {
        $v_sql .= " AND (v.voter_email LIKE ? OR v.voter_name LIKE ? OR v.voter_ip LIKE ? OR v.username_id LIKE ?)";
        $search_term = "%$search_query%";
        $v_params[] = $search_term;
        $v_params[] = $search_term;
        $v_params[] = $search_term;
        $v_params[] = $search_term;
    }
    
    if (!empty($filter_category)) {
        $v_sql .= " AND v.age_category = ?";
        $v_params[] = $filter_category;
    }
    
    if ($filter_status !== '') {
        $v_sql .= " AND v.is_confirmed = ?";
        $v_params[] = (int)$filter_status;
    }
    
    $v_sql .= " ORDER BY v.voted_at DESC LIMIT 100";
    
    $votes_stmt = $pdo->prepare($v_sql);
    $votes_stmt->execute($v_params);
    $votes_log = $votes_stmt->fetchAll();

    // 6. Suspicious IP addresses (with > 1 confirmed vote)
    $suspicious_ip_stmt = $pdo->query("
        SELECT voter_ip, COUNT(*) AS vote_count 
        FROM votes 
        WHERE is_confirmed = 1
        GROUP BY voter_ip 
        HAVING COUNT(*) > 1 
        ORDER BY vote_count DESC
    ");
    $suspicious_ips = $suspicious_ip_stmt->fetchAll();

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
    <title>Admin CRUD Portal - NK Strip</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .section-title {
            font-family: var(--font-heading);
            font-weight: 900;
            font-size: 1.6rem;
            text-transform: uppercase;
            margin: 40px 0 20px 0;
            border-bottom: 3px solid var(--border-color);
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .filter-form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            background: #fff;
            padding: 15px;
            border: 3px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0px 4px 0px var(--border-color);
        }

        .filter-input {
            height: 42px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            padding: 0 10px;
            font-family: inherit;
            font-size: 0.9rem;
            background: #fff;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-width: 2px;
            box-shadow: 0 2px 0 var(--border-color);
        }

        .manage-actions-cell {
            display: flex;
            gap: 6px;
        }

        /* Modal Overlay & Styling */
        .admin-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .admin-modal.active {
            display: flex;
        }

        .admin-modal-card {
            background: #fff;
            border: 3px solid var(--border-color);
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            padding: 30px;
            box-shadow: 0px 8px 0px var(--border-color);
            position: relative;
        }

        .admin-modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 2rem;
            font-weight: bold;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--text-muted);
        }
        .admin-modal-close:hover {
            color: var(--text-main);
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1300px;">
        <!-- ADMIN NAVIGATION -->
        <div class="admin-nav">
            <div>
                <span class="admin-nav-title"><a href="../index.php" style="color:inherit;text-decoration:none;">NK Strip</a></span>
                <span class="logo-sub" style="margin-left: 10px; font-size: 0.8rem; vertical-align: middle; background:var(--accent-color); color:#fff; padding:2px 8px; border-radius:10px; border:1px solid #1a1a1a;">CRUD Admin Portal</span>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
                <span style="font-weight:600; font-size:0.9rem;">Logged in as: <u><?= htmlspecialchars($_SESSION['admin_user']) ?></u></span>
                <a href="logout.php" class="btn btn-secondary btn-sm">
                    <!-- Logout icon -->
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Logout
                </a>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success" style="margin: 20px 0;"><?= $success_msg ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger" style="margin: 20px 0;"><?= $error_msg ?></div>
        <?php endif; ?>

        <!-- STATISTICS GRID -->
        <div class="admin-stats-grid" style="margin-top:20px;">
            <div class="admin-stat-card">
                <div class="admin-stat-value"><?= $total_participants ?></div>
                <div class="admin-stat-label">Total Finalists</div>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-value" style="color:#52c41a;"><?= $total_votes ?></div>
                <div class="admin-stat-label">Confirmed Votes (Active)</div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-value" style="color:#faad14;"><?= $pending_votes ?></div>
                <div class="admin-stat-label">Pending Confirmation (Mailed)</div>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-value" style="font-size: 1.35rem; line-height: 1.8rem; color: var(--accent-color);">
                    <?= htmlspecialchars($winning_artist) ?>
                </div>
                <div class="admin-stat-label">Leaderboard Winner (Overall)</div>
            </div>
        </div>

        <!-- PARTICIPANTS CRUD SECTION -->
        <div class="section-title">
            <span>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:5px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Finalist Rankings &amp; Management
            </span>
            <button class="btn btn-primary btn-sm" onclick="openAddModal()" style="background-color:#13c2c2;">
                + Add New Finalist
            </button>
        </div>
        
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Participant / Comic Title</th>
                        <th>Username ID</th>
                        <th>Age Group</th>
                        <th>Email</th>
                        <th>Views</th>
                        <th>Confirmed Votes</th>
                        <th style="width: 150px;">Actions</th>
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
                                            <div style="font-size:0.75rem; color:var(--text-muted);"><?= $page_count ?> Page<?= $page_count > 1 ? 's' : '' ?></div>
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
                                <td style="font-weight:bold;"><?= $p->views ?></td>
                                <td>
                                    <span class="admin-badge admin-badge-votes"><?= htmlspecialchars($p->vote_count) ?> confirmed</span>
                                </td>
                                <td>
                                    <div class="manage-actions-cell">
                                        <button class="btn btn-secondary btn-sm" onclick="openEditModal('<?= htmlspecialchars($p->username_id) ?>', '<?= htmlspecialchars($p->name) ?>', '<?= htmlspecialchars($p->email) ?>', '<?= htmlspecialchars($p->age_category) ?>')" style="border-color:#1890ff; color:#1890ff; padding:4px 8px; font-size:0.8rem;">Edit</button>
                                        
                                        <form action="dashboard.php" method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete Finalist \'<?= htmlspecialchars($p->name) ?>\'? All their image files and votes will be permanently deleted!');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_finalist">
                                            <input type="hidden" name="username_id" value="<?= htmlspecialchars($p->username_id) ?>">
                                            <input type="hidden" name="age_category" value="<?= htmlspecialchars($p->age_category) ?>">
                                            <button type="submit" class="btn btn-primary btn-sm" style="background:#f5222d; padding:4px 8px; font-size:0.8rem;">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">No registered finalists found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start; margin-top:30px;">
            <!-- VOTES LOG SECTION WITH SEARCH FILTER -->
            <div>
                <div class="section-title" style="margin: 0 0 20px 0;">
                    <span>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        Voter Activity &amp; Filter Logs
                    </span>
                </div>

                <!-- SELECT / SEARCH FORM -->
                <form action="dashboard.php" method="GET" class="filter-form-grid">
                    <input type="text" name="search" class="filter-input" placeholder="Search Email, IP, Voter Name or ID..." value="<?= htmlspecialchars($search_query) ?>">
                    
                    <select name="filter_category" class="filter-input">
                        <option value="">All Categories</option>
                        <option value="9-11" <?= $filter_category === '9-11' ? 'selected' : '' ?>>9-11 Years</option>
                        <option value="12-14" <?= $filter_category === '12-14' ? 'selected' : '' ?>>12-14 Years</option>
                        <option value="15-17" <?= $filter_category === '15-17' ? 'selected' : '' ?>>15-17 Years</option>
                    </select>

                    <select name="filter_status" class="filter-input">
                        <option value="">All Statuses</option>
                        <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Pending</option>
                    </select>

                    <div style="display:flex; gap:10px;">
                        <button type="submit" class="btn btn-primary btn-sm" style="background:#722ed1; height:42px;">Filter</button>
                        <a href="dashboard.php" class="btn btn-secondary btn-sm" style="height:42px; display:flex; align-items:center; justify-content:center;">Reset</a>
                    </div>
                </form>

                <div class="admin-table-container">
                    <table class="admin-table" style="font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th>Voter Name</th>
                                <th>Voter Email</th>
                                <th>IP Address</th>
                                <th>Voted For</th>
                                <th>Age Group</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($votes_log) > 0): ?>
                                <?php foreach ($votes_log as $v): ?>
                                    <tr style="<?= $v->is_confirmed == 0 ? 'background-color:#fffbe6;' : '' ?>">
                                        <td style="font-weight: 700;"><?= htmlspecialchars($v->voter_name ?? 'N/A') ?></td>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($v->voter_email) ?></td>
                                        <td style="font-family: monospace;"><?= htmlspecialchars($v->voter_ip) ?></td>
                                        <td style="font-weight:700; color:var(--primary-color);"><?= htmlspecialchars($v->candidate_name ?? 'Deleted finalist') ?></td>
                                        <td><span class="admin-badge admin-badge-age"><?= htmlspecialchars($v->age_category) ?></span></td>
                                        <td>
                                            <?php if ($v->is_confirmed == 1): ?>
                                                <span class="admin-badge" style="background:#f6ffed; border-color:#b7eb8f; color:#389e0d;">Confirmed</span>
                                            <?php else: ?>
                                                <span class="admin-badge" style="background:#fffbe6; border-color:#ffe58f; color:#d46b08;">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:var(--text-muted); font-size:0.8rem;"><?= htmlspecialchars($v->voted_at) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; color:var(--text-muted); padding:20px;">No votes found matching filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECURITY AUDIT / DUPLICATE IPS -->
            <div>
                <h2 class="section-title" style="margin:0 0 20px 0; color: var(--accent-color); border-bottom-color: var(--accent-color);">
                    <span>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:5px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        Security Audit (IP Check)
                    </span>
                </h2>

                <div class="admin-table-container">
                    <table class="admin-table" style="font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th>Voter IP Address</th>
                                <th>Confirmed Votes</th>
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

    <!-- -------------------------------------------------------- -->
    <!-- CRUD MODAL: ADD FINALIST -->
    <!-- -------------------------------------------------------- -->
    <div id="add-finalist-modal" class="admin-modal">
        <div class="admin-modal-card">
            <button class="admin-modal-close" onclick="closeAddModal()">&times;</button>
            <h3 class="modal-title" style="margin-bottom:20px; border-bottom:3px solid var(--border-color); padding-bottom:8px;">Add New Finalist</h3>
            
            <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_finalist">
                
                <div class="form-group">
                    <label class="form-label" for="add-artist-name">Full Name</label>
                    <input type="text" id="add-artist-name" name="name" class="form-input" placeholder="e.g. Charlie Brown" required>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label class="form-label" for="add-username-id">Username ID (Unique URL identifier)</label>
                    <input type="text" id="add-username-id" name="username_id" class="form-input" placeholder="e.g. charlie_brown" required>
                    <span style="font-size:0.8rem; color:var(--text-muted);">Lowercase letters, numbers, underscores or hyphens only. Automatically filled.</span>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label class="form-label" for="add-artist-email">Email Address</label>
                    <input type="email" id="add-artist-email" name="email" class="form-input" placeholder="e.g. charlie@peanuts.com" required>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label class="form-label" for="add-age-category">Age Category</label>
                    <select id="add-age-category" name="age_category" class="form-input" required style="background:#fff; height:50px;">
                        <option value="" disabled selected>Select age category...</option>
                        <option value="9-11">9-11 Years</option>
                        <option value="12-14">12-14 Years</option>
                        <option value="15-17">15-17 Years</option>
                    </select>
                </div>

                <div class="form-group" style="margin-top:20px;">
                    <label class="form-label">Upload Comic Pages (Page 1 is required, others optional)</label>
                    <div class="file-upload-grid" style="grid-template-columns: repeat(3, 1fr);">
                        <div class="file-upload-box" style="padding:10px;">
                            <span class="file-upload-label" style="font-size:0.8rem;">Page 1 (Req)</span>
                            <input type="file" name="page_1" accept="image/*" required>
                        </div>
                        <div class="file-upload-box" style="padding:10px;">
                            <span class="file-upload-label" style="font-size:0.8rem;">Page 2 (Opt)</span>
                            <input type="file" name="page_2" accept="image/*">
                        </div>
                        <div class="file-upload-box" style="padding:10px;">
                            <span class="file-upload-label" style="font-size:0.8rem;">Page 3 (Opt)</span>
                            <input type="file" name="page_3" accept="image/*">
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:25px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:#13c2c2;">Save Finalist</button>
                </div>
            </form>
        </div>
    </div>

    <!-- -------------------------------------------------------- -->
    <!-- CRUD MODAL: EDIT FINALIST -->
    <!-- -------------------------------------------------------- -->
    <div id="edit-finalist-modal" class="admin-modal">
        <div class="admin-modal-card">
            <button class="admin-modal-close" onclick="closeEditModal()">&times;</button>
            <h3 class="modal-title" style="margin-bottom:20px; border-bottom:3px solid var(--border-color); padding-bottom:8px;">Edit Finalist</h3>
            
            <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_finalist">
                <input type="hidden" id="edit-original-username-id" name="original_username_id">
                <input type="hidden" id="edit-age-category" name="age_category">
                
                <div class="form-group">
                    <label class="form-label" for="edit-artist-name">Full Name</label>
                    <input type="text" id="edit-artist-name" name="name" class="form-input" required>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label class="form-label" for="edit-artist-email">Email Address</label>
                    <input type="email" id="edit-artist-email" name="email" class="form-input" required>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label class="form-label">Update Comic Pages (Optional - leave empty to keep existing images)</label>
                    <div class="file-upload-grid" style="grid-template-columns: repeat(3, 1fr);">
                        <div class="file-upload-box" style="padding:10px;">
                            <span class="file-upload-label" style="font-size:0.8rem;">Page 1</span>
                            <input type="file" name="page_1" accept="image/*">
                        </div>
                        <div class="file-upload-box" style="padding:10px;">
                            <span class="file-upload-label" style="font-size:0.8rem;">Page 2</span>
                            <input type="file" name="page_2" accept="image/*">
                        </div>
                        <div class="file-upload-box" style="padding:10px;">
                            <span class="file-upload-label" style="font-size:0.8rem;">Page 3</span>
                            <input type="file" name="page_3" accept="image/*">
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:25px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:#1890ff;">Update Finalist</button>
                </div>
            </form>
        </div>
    </div>

    <!-- COMIC VIEWER MODAL -->
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
    <script>
        // Modal management
        const addModal = document.getElementById('add-finalist-modal');
        const editModal = document.getElementById('edit-finalist-modal');
        
        window.openAddModal = function() {
            addModal.classList.add('active');
            document.getElementById('add-artist-name').focus();
        };
        window.closeAddModal = function() {
            addModal.classList.remove('active');
        };

        window.openEditModal = function(usernameId, name, email, ageCategory) {
            document.getElementById('edit-original-username-id').value = usernameId;
            document.getElementById('edit-age-category').value = ageCategory;
            document.getElementById('edit-artist-name').value = name;
            document.getElementById('edit-artist-email').value = email;
            editModal.classList.add('active');
            document.getElementById('edit-artist-name').focus();
        };
        window.closeEditModal = function() {
            editModal.classList.remove('active');
        };

        // Auto fill Username ID in Add form
        document.getElementById('add-artist-name').addEventListener('input', function() {
            const usernameInput = document.getElementById('add-username-id');
            const slug = this.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9\s_\-]/g, '')
                .replace(/\s+/g, '_');
            usernameInput.value = slug;
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });
    </script>
</body>
</html>
