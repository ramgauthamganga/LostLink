<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Demo Data Seeder Runner
|--------------------------------------------------------------------------
| Safely executes database/seed_demo_data.sql against the configured database.
| Demo-scoped: manages only @lostlink.test accounts and associated records.
| Leaves non-demo developer accounts completely untouched.
|
*/

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "============================================================\n";
echo "LOSTLINK — DEMO DATASET SEEDER\n";
echo "============================================================\n";

$sqlFile = __DIR__ . '/seed_demo_data.sql';
if (!file_exists($sqlFile)) {
    die("[ERROR] Seed file not found: {$sqlFile}\n");
}

$sqlContent = file_get_contents($sqlFile);
if ($sqlContent === false) {
    die("[ERROR] Unable to read seed file: {$sqlFile}\n");
}

try {
    echo "[INFO] Connected to database: {$dbname}\n";
    echo "[INFO] Executing demo seed script...\n";

    // Strip comments and cleanly split into executable statements
    $cleanSql = preg_replace('!/\*.*?\*/!s', '', $sqlContent);
    $lines = explode("\n", $cleanSql);
    $statement = '';
    $statements = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }
        $statement .= $line . "\n";
        if (str_ends_with($trimmed, ';')) {
            $statements[] = trim($statement);
            $statement = '';
        }
    }
    if (trim($statement) !== '') {
        $statements[] = trim($statement);
    }

    // Execute statements
    foreach ($statements as $stmtSql) {
        if ($stmtSql === '' || $stmtSql === ';') {
            continue;
        }
        $pdo->exec($stmtSql);
    }

    echo "[SUCCESS] Demo dataset successfully seeded!\n\n";

    // Summary of seeded demo records
    echo "------------------------------------------------------------\n";
    echo "DEMO DATASET SUMMARY\n";
    echo "------------------------------------------------------------\n";

    $demoUserCount = $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%@lostlink.test'")->fetchColumn();
    $demoItemCount = $pdo->query("SELECT COUNT(*) FROM items WHERE item_code LIKE 'ITM-DEMO%'")->fetchColumn();
    $demoImageCount = $pdo->query("SELECT COUNT(*) FROM item_images WHERE image_code LIKE 'IMG-DEMO%'")->fetchColumn();
    $demoClaimCount = $pdo->query("SELECT COUNT(*) FROM claims WHERE claim_code LIKE 'CLM-DEMO%'")->fetchColumn();
    $demoRequestCount = $pdo->query("SELECT COUNT(*) FROM contact_requests WHERE request_code LIKE 'REQ-DEMO%'")->fetchColumn();
    $demoConvCount = $pdo->query("SELECT COUNT(*) FROM conversations WHERE conversation_code LIKE 'CNV-DEMO%'")->fetchColumn();
    $demoMsgCount = $pdo->query("SELECT COUNT(*) FROM messages WHERE message_code LIKE 'MSG-DEMO%'")->fetchColumn();
    $demoNotifCount = $pdo->query("SELECT COUNT(*) FROM notifications WHERE notification_code LIKE 'NTF-DEMO%'")->fetchColumn();

    echo " - Demo Users:            {$demoUserCount} (2 Admins, 6 Students)\n";
    echo " - Demo Items:            {$demoItemCount}\n";
    echo " - Demo Item Images:      {$demoImageCount}\n";
    echo " - Demo Claims:           {$demoClaimCount} (1 Pending, 1 Approved, 1 Rejected)\n";
    echo " - Demo Contact Requests: {$demoRequestCount} (1 Accepted, 1 Pending, 1 Rejected)\n";
    echo " - Demo Conversations:    {$demoConvCount} (1 Active thread)\n";
    echo " - Demo Messages:         {$demoMsgCount} (Exchanged messages + unread state)\n";
    echo " - Demo Notifications:    {$demoNotifCount}\n";
    echo "------------------------------------------------------------\n";

} catch (Throwable $e) {
    echo "[ERROR] Database seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
