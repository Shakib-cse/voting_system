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
    
    // 4. Create 'participants' table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `participants` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username_id` VARCHAR(50) UNIQUE NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `age_category` VARCHAR(20) NOT NULL,
        `email` VARCHAR(100) NOT NULL,
        `page_1` VARCHAR(255) NOT NULL,
        `page_2` VARCHAR(255) DEFAULT NULL,
        `page_3` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // 5. Create 'votes' table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `votes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username_id` VARCHAR(50) NOT NULL,
        `voter_email` VARCHAR(100) NOT NULL,
        `voter_ip` VARCHAR(45) NOT NULL,
        `voted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`username_id`) REFERENCES `participants`(`username_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
} catch (PDOException $e) {
    die("<div style='padding: 20px; background: #fff1f0; border: 2px solid #ffa39e; color: #cf1322; border-radius: 8px; font-family: sans-serif; max-width: 600px; margin: 40px auto;'>
            <h3 style='margin-top:0;'>Database Connection Setup Failed!</h3>
            <p>Error details: <strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>
            <p>Please make sure that XAMPP/WampServer is running and MySQL/MariaDB server is online on localhost.</p>
         </div>");
}
?>
