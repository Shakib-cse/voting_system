<?php
require_once 'config/db.php';

// 1. Get Category filter (Default to '9-11' to match the active tab in the image)
$selected_category = $_GET['category'] ?? '9-11';
if (!in_array($selected_category, ['9-11', '12-14', '15-17'])) {
    $selected_category = '9-11';
}

// 2. Pagination variables
$limit = 15; // 15 cards per page as per requirement
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$table_name = "participants_" . str_replace('-', '_', $selected_category);

try {
    // 3. Count total participants in active category
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM $table_name");
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // 4. Fetch participants for the current page (ORDER BY views ASC, RAND() for Equal Chance + True Random Logic)
    $stmt = $pdo->prepare("SELECT * FROM $table_name ORDER BY views ASC, RAND() LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $participants = $stmt->fetchAll();

    // 5. Increment views for the displayed participants (+1 viewed every time)
    if (count($participants) > 0) {
        $ids = array_map(function($p) { return $p->username_id; }, $participants);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $update_stmt = $pdo->prepare("UPDATE $table_name SET views = views + 1 WHERE username_id IN ($placeholders)");
        $update_stmt->execute($ids);
    }

} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NK Strip Voting - Comic Book Drawing Competition</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <!-- HEADER -->
    <header>
        <a href="https://www.stripplaza.nl/" class="header-logo-link">
            <img src="assets/images/header/logo.png" alt="Logo" width="200" height="300" class="header-logo header-logo primary">
        </a>
                <!-- CATEGORIES AS COMIC SPEECH BUBBLES -->
    <div class="categories-container">
        <div onclick="location.href='index.php?category=9-11'" class="bubble-tab <?= $selected_category === '9-11' ? 'active' : '' ?>">
            <img src="assets/images/header/9-11.png" alt="9-11 Icon" width="100" height="100" class="tab-icon">
        </div>
        <div onclick="location.href='index.php?category=12-14'" class="bubble-tab <?= $selected_category === '12-14' ? 'active' : '' ?>">
            <img src="assets/images/header/12-14.png" alt="12-14 Icon" width="100" height="100" class="tab-icon">
        </div>
        <div onclick="location.href='index.php?category=15-17'" class="bubble-tab <?= $selected_category === '15-17' ? 'active' : '' ?>">
            <img src="assets/images/header/15-17.png" alt="15-17 Icon" width="100" height="100" class="tab-icon">
        </div>
    </div>
        <img src="assets/images/header/secondary-logo.png" alt="Secondary Logo" width="300" height="300" class="header-logo-secondary">
    </header>

    
    <div class="container">



        <!-- INTROTEXT -->
        <div class="intro-text-box">
            Hier komt een tekst over de verkiezingen. Kies hieronder je favoriete striptekening van de finalisten en breng direct je stem uit om jouw favoriet te steunen!
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 15px; margin-bottom: 25px;">
            <a href="leaderboard.php" class="btn btn-secondary" style="border-color:var(--accent-color); color:var(--accent-color); font-weight:700;">
                <!-- Trophy Icon -->
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path></svg>
                View Leaderboard
            </a>
            <a href="upload.php" class="btn btn-secondary" style="border-color:var(--text-main); font-weight:700;">
                <!-- Register / Upload Icon -->
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                Register &amp; Submit Comic
            </a>
        </div>

        <!-- PARTICIPANTS GRID -->
        <?php if (count($participants) > 0): ?>
            <div class="participants-grid">
                <?php foreach ($participants as $p): ?>
                    <?php 
                        // Determine pages available
                        $pages = array_filter([$p->page_1, $p->page_2, $p->page_3]);
                        $page_count = count($pages);
                        
                        // Card classes based on page counts
                        $stack_class = '';
                        if ($page_count == 2) {
                            $stack_class = 'has-2-pages';
                        } elseif ($page_count >= 3) {
                            $stack_class = 'has-3-pages';
                        }
                    ?>
                    <div class="participant-item">
                        <!-- Title Above Card -->
                        <h3 class="item-title"><?= htmlspecialchars($p->name) ?></h3>
                        
                        <!-- Card Container -->
                        <div class="participant-card">
                            <!-- Layered Stack Container -->
                            <div class="image-stack-container <?= $stack_class ?>" onclick="location.href='profile.php?id=<?= urlencode($p->username_id) ?>'">
                                <!-- Stack layers for multiple pages -->
                                <?php if ($page_count >= 3): ?>
                                    <div class="stack-layer stack-layer-2"></div>
                                <?php endif; ?>

                                <?php if ($page_count >= 2): ?>
                                    <div class="stack-layer stack-layer-1"></div>
                                <?php endif; ?>

                                <!-- Primary image -->
                                <div class="image-card">
                                    <img src="<?= htmlspecialchars($p->page_1) ?>" alt="Comic of <?= htmlspecialchars($p->name) ?>" loading="lazy">
                                </div>

                                <!-- Page count badge -->
                                <span class="page-count-badge">
                                    <?= $page_count ?> Page<?= $page_count > 1 ? 's' : '' ?>
                                </span>
                            </div>

                            <!-- Vote Button -->
                            <button onclick="location.href='profile.php?id=<?= urlencode($p->username_id) ?>'" class="btn btn-vote">
                                Dreng je stem uit!
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <span style="font-family:var(--font-heading); font-weight:700; color:var(--text-muted); margin-right:8px;">pages:</span>
                    
                    <?php if ($page > 1): ?>
                        <button onclick="location.href='index.php?category=<?= $selected_category ?>&page=<?= $page - 1 ?>'" class="pagination-item">&lt;</button>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <button onclick="location.href='index.php?category=<?= $selected_category ?>&page=<?= $i ?>'" class="pagination-item <?= $page === $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </button>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <button onclick="location.href='index.php?category=<?= $selected_category ?>&page=<?= $page + 1 ?>'" class="pagination-item">Next &gt;</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align:center; padding:60px 20px; background:#fff; border:3px dashed var(--border-color); border-radius:12px; margin:40px 0;">
                <!-- Sad emoji or search slash icon -->
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-muted); margin-bottom:15px;"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="15" x2="16" y2="15"></line><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                <h3 style="font-family:var(--font-heading); font-size:1.4rem; margin-bottom:8px;">No participants found!</h3>
                <p style="color:var(--text-muted);">Be the first one to submit a comic for this age category!</p>
                <a href="upload.php" class="btn btn-primary" style="margin-top:20px;">Upload Drawing Now</a>
            </div>
        <?php endif; ?>

        <!-- FOOTER -->
        <footer>
            <p>&copy; 2026 NK Strip Tekenwedstrijd. All rights reserved.</p>
            <div class="footer-links">
                <a href="index.php">Home</a>
                <a href="upload.php">Register Comic</a>
                <a href="admin/login.php">Admin Panel</a>
            </div>
        </footer>
    </div>

    <!-- VOTE EMAIL MODAL -->
    <div id="vote-modal" class="modal-overlay">
        <div class="modal-card">
            <button class="modal-close" onclick="closeVoteModal()">&times;</button>
            <h3 id="vote-modal-title" class="modal-title">Cast Vote</h3>
            
            <form id="vote-form">
                <div class="form-group">
                    <label class="form-label" for="voter-email">Your Email Address</label>
                    <input type="email" id="voter-email" class="form-input" placeholder="e.g. yourname@example.com" required>
                    <span style="font-size:0.8rem; color:var(--text-muted); margin-top:5px;">We strictly limit voting to 1 vote per email address to prevent duplicate votes.</span>
                </div>

                <div id="vote-feedback" class="form-group" style="display:none;"></div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:30px;">
                    <button type="button" class="btn btn-secondary" onclick="closeVoteModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                        Confirm Vote
                    </button>
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

    <script src="assets/script.js"></script>
</body>
</html>
