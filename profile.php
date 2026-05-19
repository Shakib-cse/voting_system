<?php
require_once 'config/db.php';

$username_id = $_GET['id'] ?? '';

if (empty($username_id)) {
    header("Location: index.php");
    exit;
}

try {
    // Fetch participant details by searching across category-specific tables
    $participant = null;
    $selected_category = '';
    foreach (['9-11', '12-14', '15-17'] as $cat) {
        $tbl = "participants_" . str_replace('-', '_', $cat);
        $stmt = $pdo->prepare("SELECT * FROM $tbl WHERE username_id = ?");
        $stmt->execute([$username_id]);
        $res = $stmt->fetch();
        if ($res) {
            $participant = $res;
            $selected_category = $cat;
            break;
        }
    }

    if (!$participant) {
        die("<h2>Participant not found!</h2><a href='index.php'>Go back</a>");
    }

    // Get all available pages/artworks
    $artworks = array_filter([$participant->page_1, $participant->page_2, $participant->page_3]);

} catch (PDOException $e) {
    die("Error fetching participant: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($participant->name) ?>'s Portfolio - NK Strip</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .portfolio-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: #fff;
            border: 3px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0px 8px 0px var(--border-color);
        }
        .portfolio-title {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-main);
            margin-bottom: 10px;
        }
        .portfolio-age {
            display: inline-block;
            background: var(--primary-color);
            color: #fff;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.1rem;
            border: 2px solid var(--border-color);
        }
        .artworks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        .artwork-card {
            background: #fff;
            padding: 15px;
            border: 3px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0px 6px 0px var(--border-color);
        }
        .artwork-card img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            border: 2px solid var(--border-color);
        }
        .vote-section {
            text-align: center;
            margin: 40px 0;
        }
        .vote-btn-large {
            font-size: 1.5rem;
            padding: 15px 40px;
            background-color: var(--accent-color);
            color: #fff;
        }
        .vote-btn-large:hover {
            background-color: #d9363e;
            transform: translateY(-3px);
            box-shadow: 0px 8px 0px var(--border-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header>
                        <a href="https://www.stripplaza.nl/">
            <img src="assets/images/header/logo.png" alt="Logo" width="150" height="120" class="header-logo header-logo primary">
        </a>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    &larr; Back to Gallery
                </a>
            </div>
        </header>

        <!-- PORTFOLIO HEADER -->
        <div class="portfolio-header">
            <h2 class="portfolio-title"><?= htmlspecialchars($participant->name) ?>'s Portfolio</h2>
            <div class="portfolio-age">Age Category: <?= htmlspecialchars($selected_category) ?> Years</div>
        </div>

        <!-- MULTIPLE PAGES CONDITIONAL BANNER -->
        <?php if (count($artworks) > 1): ?>
            <div class="multiple-pages-banner" style="background:#e6f7ff; border:3px solid var(--border-color); border-radius:12px; padding:20px; text-align:center; font-family:var(--font-heading); font-weight:700; color:var(--text-main); margin-bottom:35px; font-size:1.1rem; box-shadow: 0px 6px 0px var(--border-color); display:flex; align-items:center; justify-content:center; gap:10px;">
                <!-- Book icon SVG -->
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                This comic book entry has multiple pages! Read all entries below and cast your vote!
            </div>
        <?php endif; ?>

        <!-- ARTWORKS GALLERY -->
        <div class="artworks-grid">
            <?php foreach ($artworks as $index => $art): ?>
                <div class="artwork-card">
                    <h3 style="text-align: center; margin-bottom: 10px; font-family: var(--font-heading);">Artwork #<?= $index + 1 ?></h3>
                    <img src="<?= htmlspecialchars($art) ?>" alt="Artwork by <?= htmlspecialchars($participant->name) ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <!-- VOTE BUTTON -->
        <div class="vote-section">
            <button onclick="openVoteModal('<?= htmlspecialchars($participant->username_id) ?>', '<?= rawurlencode($participant->name) ?>')" class="btn btn-primary vote-btn-large">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 10px;"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                Vote for <?= htmlspecialchars($participant->name) ?>!
            </button>
            <p style="margin-top: 15px; color: var(--text-muted); font-weight: 500;">Show your support by casting a vote!</p>
        </div>
        
    </div>

    <!-- VOTE EMAIL MODAL -->
    <div id="vote-modal" class="modal-overlay">
        <div class="modal-card">
            <button class="modal-close" onclick="closeVoteModal()">&times;</button>
            <h3 id="vote-modal-title" class="modal-title">Cast Vote</h3>
            
            <form id="vote-form">
                <input type="hidden" id="vote-age-category" value="<?= htmlspecialchars($selected_category) ?>">
                
                <div class="form-group">
                    <label class="form-label" for="voter-name">Your Full Name</label>
                    <input type="text" id="voter-name" class="form-input" placeholder="e.g. John Doe" required>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label class="form-label" for="voter-email">Your Email Address</label>
                    <input type="email" id="voter-email" class="form-input" placeholder="e.g. yourname@example.com" required>
                    <span style="font-size:0.8rem; color:var(--text-muted); margin-top:5px;">We strictly limit voting to 1 vote per email address to prevent duplicate votes.</span>
                </div>

                <div id="vote-feedback" class="form-group" style="display:none;"></div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:30px;">
                    <button type="button" class="btn btn-secondary" onclick="closeVoteModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Vote</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/script.js"></script>
</body>
</html>
