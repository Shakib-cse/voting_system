<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'voting_system';

try {
    // 1. Initial connection to local MySQL server (without selecting a DB)
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 2. Create the Database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 3. Connect directly to the created Database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    
    // 4. Check if the old 'participants' table exists and has data to migrate
    $old_table_exists = false;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$dbname' AND table_name = 'participants'");
        if ($stmt->fetchColumn() > 0) {
            $old_table_exists = true;
        }
    } catch (Exception $e) {}

    // 5. Create category-specific participant tables
    $categories = ['9_11', '12_14', '15_17'];
    foreach ($categories as $cat) {
        $tbl = "participants_$cat";
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$tbl` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username_id` VARCHAR(50) UNIQUE NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `page_1` VARCHAR(255) NOT NULL,
            `page_2` VARCHAR(255) DEFAULT NULL,
            `page_3` VARCHAR(255) DEFAULT NULL,
            `views` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    // 6. Check if new votes table is needed (or drop and migrate if it lacks is_confirmed)
    $migrate_votes = false;
    $old_votes_data = [];
    try {
        $check_votes = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$dbname' AND table_name = 'votes'")->fetchColumn();
        if ($check_votes > 0) {
            $cols = $pdo->query("SHOW COLUMNS FROM `votes` LIKE 'is_confirmed'")->fetchAll();
            if (empty($cols)) {
                // Old votes table exists and lacks confirmation columns, need migration
                $stmt = $pdo->query("SELECT * FROM `votes`");
                $old_votes_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $migrate_votes = true;
                $pdo->exec("DROP TABLE IF EXISTS `votes`");
            }
        }
    } catch (Exception $e) {}

    // 7. Create/Recreate 'votes' table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `votes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username_id` VARCHAR(50) NOT NULL,
        `age_category` VARCHAR(20) NOT NULL,
        `voter_name` VARCHAR(100) DEFAULT NULL,
        `voter_email` VARCHAR(100) NOT NULL,
        `voter_ip` VARCHAR(45) NOT NULL,
        `is_confirmed` TINYINT NOT NULL DEFAULT 0,
        `confirmation_token` VARCHAR(100) DEFAULT NULL,
        `voted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 8. Run Migration if old table exists
    if ($old_table_exists) {
        $stmt = $pdo->query("SELECT * FROM `participants`");
        $old_participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($old_participants)) {
            foreach ($old_participants as $op) {
                $cat = str_replace('-', '_', $op['age_category']);
                $tbl = "participants_$cat";
                
                $chk = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE username_id = ?");
                $chk->execute([$op['username_id']]);
                if ($chk->fetchColumn() == 0) {
                    $ins = $pdo->prepare("INSERT INTO `$tbl` (username_id, name, email, page_1, page_2, page_3, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([
                        $op['username_id'],
                        $op['name'],
                        $op['email'],
                        $op['page_1'],
                        $op['page_2'],
                        $op['page_3'],
                        $op['created_at']
                    ]);
                }
            }
        }
        
        // If old votes table wasn't dropped but we have old participants, let's fetch old votes to migrate too
        if (!$migrate_votes) {
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM `votes` LIKE 'age_category'")->fetchAll();
                if (empty($cols)) {
                    $stmt = $pdo->query("SELECT * FROM `votes`");
                    $old_votes_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $migrate_votes = true;
                    $pdo->exec("DROP TABLE IF EXISTS `votes`");
                    
                    // Recreate votes table
                    $pdo->exec("CREATE TABLE `votes` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `username_id` VARCHAR(50) NOT NULL,
                        `age_category` VARCHAR(20) NOT NULL,
                        `voter_name` VARCHAR(100) DEFAULT NULL,
                        `voter_email` VARCHAR(100) NOT NULL,
                        `voter_ip` VARCHAR(45) NOT NULL,
                        `is_confirmed` TINYINT NOT NULL DEFAULT 0,
                        `confirmation_token` VARCHAR(100) DEFAULT NULL,
                        `voted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
                }
            } catch (Exception $e) {}
        }

        // Drop the old participants table
        $pdo->exec("DROP TABLE IF EXISTS `participants`");
    }

    // 9. If we need to migrate votes, do it now
    if ($migrate_votes && !empty($old_votes_data)) {
        foreach ($old_votes_data as $ov) {
            $age_cat = '9-11'; // default fallback
            foreach (['9_11', '12_14', '15_17'] as $c) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM `participants_$c` WHERE username_id = ?");
                $chk->execute([$ov['username_id']]);
                if ($chk->fetchColumn() > 0) {
                    $age_cat = str_replace('_', '-', $c);
                    break;
                }
            }
            
            $voter_name = $ov['voter_name'] ?? 'Voter';
            $voter_ip = $ov['voter_ip'] ?? '127.0.0.1';
            $voted_at = $ov['voted_at'] ?? date('Y-m-d H:i:s');
            
            $ins = $pdo->prepare("INSERT INTO `votes` (username_id, age_category, voter_name, voter_email, voter_ip, is_confirmed, voted_at) VALUES (?, ?, ?, ?, ?, 1, ?)");
            $ins->execute([
                $ov['username_id'],
                $age_cat,
                $voter_name,
                $ov['voter_email'],
                $voter_ip,
                $voted_at
            ]);
        }
    }
    
} catch (PDOException $e) {
    die("<div style='padding: 20px; background: #fff1f0; border: 2px solid #ffa39e; color: #cf1322; border-radius: 8px; font-family: sans-serif; max-width: 600px; margin: 40px auto;'>
            <h3 style='margin-top:0;'>Database Connection Setup Failed!</h3>
            <p>Error details: <strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>
            <p>Please make sure that XAMPP/WampServer is running and MySQL/MariaDB server is online on localhost.</p>
         </div>");
}
?>
