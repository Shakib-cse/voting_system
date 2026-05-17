<?php
require_once 'config/db.php';

try {
    // Fetch participants from all three tables and join with confirmed votes
    $stmt = $pdo->query("
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
    $leaderboard = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching leaderboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Leaderboard - NK Strip Voting</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .leaderboard-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .leaderboard-title {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-main);
            text-transform: uppercase;
        }
        .leaderboard-list {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 3px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0px 8px 25px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 2px solid var(--border-color);
            transition: background 0.2s ease;
        }
        .leaderboard-item:last-child {
            border-bottom: none;
        }
        .leaderboard-item:hover {
            background-color: #f9f9f9;
        }
        .rank-badge {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 900;
            width: 50px;
            height: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            border: 3px solid var(--border-color);
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        /* Top 3 Styling */
        .rank-1 .rank-badge { background-color: #ffd700; color: #fff; box-shadow: 0 4px 0 #d4af37; }
        .rank-2 .rank-badge { background-color: #c0c0c0; color: #fff; box-shadow: 0 4px 0 #a9a9a9; }
        .rank-3 .rank-badge { background-color: #cd7f32; color: #fff; box-shadow: 0 4px 0 #8b4513; }
        
        /* Others */
        .rank-other .rank-badge { background-color: #f0f0f0; color: var(--text-main); }

        .kid-info {
            flex-grow: 1;
        }
        .kid-name {
            font-family: var(--font-heading);
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--text-main);
        }
        .kid-age {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .vote-count {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--primary-color);
            background: #f6ffed;
            padding: 5px 15px;
            border-radius: 8px;
            border: 2px solid #b7eb8f;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header>
            <div class="logo-container">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" style="transform: rotate(-5deg); filter: drop-shadow(2px 3px 0px #1a1a1a);">
                    <circle cx="32" cy="32" r="30" fill="#ffeb3b" stroke="#1a1a1a" stroke-width="3"/>
                    <path d="M12 18L14 14L18 12L14 10L12 6L10 10L6 12L10 14L12 18Z" fill="#ff5722"/>
                    <path d="M48 45L49 42L52 41L49 40L48 37L47 40L44 41L47 42L48 45Z" fill="#e91e63"/>
                </svg>
                <div>
                    <h1 class="logo-text"><a href="index.php">NK Strip</a></h1>
                    <span class="logo-sub">Live Leaderboard</span>
                </div>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    &larr; Back to Gallery
                </a>
            </div>
        </header>

        <div class="leaderboard-header">
            <h2 class="leaderboard-title">Top Voted Artists</h2>
            <p style="color: var(--text-muted); font-size: 1.1rem; margin-top: 10px;">Check out who is currently leading the competition!</p>
        </div>

        <?php if (count($leaderboard) > 0): ?>
            <div class="leaderboard-list">
                <?php 
                $rank = 1;
                foreach ($leaderboard as $kid): 
                    $rank_class = ($rank <= 3) ? "rank-{$rank}" : "rank-other";
                ?>
                    <div class="leaderboard-item <?= $rank_class ?>">
                        <div class="rank-badge">#<?= $rank ?></div>
                        
                        <div class="kid-info">
                            <a href="profile.php?id=<?= urlencode($kid->username_id) ?>" class="kid-name">
                                <?= htmlspecialchars($kid->name) ?>
                            </a>
                            <div class="kid-age">Category: <?= htmlspecialchars($kid->age_category) ?> Years</div>
                        </div>

                        <div class="vote-count">
                            <?= $kid->vote_count ?> <span style="font-size:1rem;">Votes</span>
                        </div>
                    </div>
                <?php 
                    $rank++;
                endforeach; 
                ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:60px 20px; background:#fff; border:3px dashed var(--border-color); border-radius:12px; margin:40px 0;">
                <h3 style="font-family:var(--font-heading); font-size:1.4rem;">No votes recorded yet!</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
