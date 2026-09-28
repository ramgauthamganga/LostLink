<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * LOSTLINK REPORT ITEM CONTROLLER
 * ==============================================================================
 * This is the main controller for submitting a new Lost/Found item report.
 * It coordinates authentication, data validation, secure image upload, and
 * safe database insertion within a transaction.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/upload.php';

header('Content-Type: application/json; charset=utf-8');

// ==========================================================================
// 1. AUTHENTICATION & REQUEST METHOD
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['server' => 'Invalid request method.']]);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'errors' => ['auth' => 'You must be logged in to submit a report.']]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ==========================================================================
// 2. VALIDATION LAYER
// ==========================================================================
$validationResult = validateReportData($_POST);

if (!$validationResult['valid']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $validationResult['errors']]);
    exit;
}

$validatedData = $validationResult['data'];

// ==========================================================================
// 3. IMAGE UPLOAD LAYER
// ==========================================================================
// The frontend <input type="file" id="file-input" multiple> currently lacks a name.
// We expect the frontend JS to append files using the key 'images' or 'file-input'.
// If JS is strictly using FormData from the DOM, it might use 'file-input' if we add it,
// but for standard multipart arrays, we'll check common keys.
$fileArray = $_FILES['images'] ?? $_FILES['file-input'] ?? [];

// Only process if files were actually submitted (error array exists and isn't empty)
$uploadResult = ['success' => true, 'paths' => []];
if (!empty($fileArray) && isset($fileArray['error']) && is_array($fileArray['error']) && !empty($fileArray['name'][0])) {
    $uploadResult = uploadReportImages($fileArray);
    
    if (!$uploadResult['success']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $uploadResult['errors']]);
        exit;
    }
}

$imagePaths = $uploadResult['paths'];

// ==========================================================================
// 4. EDIT MODE BRANCH (UPDATE existing item)
// ==========================================================================
if (!empty($_POST['edit_item_code'])) {
    $editItemCode = trim((string)$_POST['edit_item_code']);
    
    // Fetch the existing item
    $stmtCheck = $pdo->prepare("SELECT * FROM items WHERE item_code = :code LIMIT 1");
    $stmtCheck->execute(['code' => $editItemCode]);
    $existingItem = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$existingItem) {
        http_response_code(404);
        echo json_encode(['success' => false, 'errors' => ['item' => 'The item to edit could not be found.']]);
        exit;
    }

    // Authorize: Current authenticated user must own the item (or be admin)
    $isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $isOwner = ((int)$existingItem['user_id'] === $userId);

    if (!$isOwner && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'errors' => ['auth' => 'Unauthorized. You can only edit your own reports.']]);
        exit;
    }

    // Enforce Image Limit (Remaining existing + newly uploaded <= 5)
    $stmtCount = $pdo->prepare("SELECT id, image FROM item_images WHERE item_id = :item_id");
    $stmtCount->execute(['item_id' => $existingItem['id']]);
    $currentImages = $stmtCount->fetchAll(PDO::FETCH_ASSOC);
    $currentImageMap = [];
    foreach ($currentImages as $ci) {
        $currentImageMap[(int)$ci['id']] = $ci['image'];
    }

    $removedIdsRaw = $_POST['removed_image_ids'] ?? '[]';
    $removedIds = is_array($removedIdsRaw) ? $removedIdsRaw : json_decode((string)$removedIdsRaw, true);
    if (!is_array($removedIds)) {
        $removedIds = [];
    }
    $validRemovedIds = array_filter(array_map('intval', $removedIds), fn($id) => isset($currentImageMap[$id]));

    $remainingCount = count($currentImages) - count($validRemovedIds);
    $newCount = count($imagePaths);

    if (($remainingCount + $newCount) > 5) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => ['images' => 'Total images cannot exceed 5.']]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $dbVerificationMethod = 'description';
        if ($validatedData['verification_method'] === 'secret_question') {
            $dbVerificationMethod = 'questions';
        }

        $additionalNotes = [];
        if (!empty($validatedData['distinguishing_features'])) {
            $additionalNotes[] = "Features: " . $validatedData['distinguishing_features'];
        }
        if (!empty($validatedData['verification_detail'])) {
            $additionalNotes[] = "Verification Detail: " . $validatedData['verification_detail'];
        }
        if (!empty($validatedData['contact_preference'])) {
            $additionalNotes[] = "Preferred Contact: " . $validatedData['contact_preference'];
        }
        if (!empty($validatedData['report_time'])) {
            $additionalNotes[] = "Time: " . $validatedData['report_time'];
        }
        $finalAdditionalNote = !empty($additionalNotes) ? implode("\n", $additionalNotes) : null;

        // Perform UPDATE on the existing record — preserve item_code and id!
        $stmtUpdate = $pdo->prepare("
            UPDATE items SET
                title = :title,
                description = :description,
                category = :category,
                report_type = :report_type,
                event_location = :event_location,
                event_date = :event_date,
                additional_note = :additional_note,
                verification_method = :verification_method,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND (user_id = :user_id OR :is_admin = 1)
        ");
        $stmtUpdate->execute([
            ':id'                  => $existingItem['id'],
            ':user_id'             => $userId,
            ':is_admin'            => $isAdmin ? 1 : 0,
            ':title'               => $validatedData['item_name'],
            ':description'         => $validatedData['description'],
            ':category'            => $validatedData['category'],
            ':report_type'         => $validatedData['type'],
            ':event_location'      => $validatedData['location'],
            ':event_date'          => $validatedData['report_date'],
            ':additional_note'     => $finalAdditionalNote,
            ':verification_method' => $dbVerificationMethod
        ]);

        // Remove deleted images from DB and filesystem
        if (!empty($validRemovedIds)) {
            $inPlaceholders = implode(',', array_fill(0, count($validRemovedIds), '?'));
            $stmtDeleteImgs = $pdo->prepare("DELETE FROM item_images WHERE id IN ($inPlaceholders) AND item_id = ?");
            $stmtDeleteImgs->execute([...$validRemovedIds, $existingItem['id']]);

            foreach ($validRemovedIds as $rId) {
                $relPath = $currentImageMap[$rId] ?? '';
                if ($relPath) {
                    $absPath = __DIR__ . '/../../' . ltrim($relPath, '/');
                    if (file_exists($absPath) && is_file($absPath)) {
                        @unlink($absPath);
                    }
                }
            }
        }

        // Insert newly uploaded images
        if (!empty($imagePaths)) {
            $stmtMaxOrder = $pdo->prepare("SELECT COALESCE(MAX(image_order), 0) FROM item_images WHERE item_id = :item_id");
            $stmtMaxOrder->execute(['item_id' => $existingItem['id']]);
            $maxOrder = (int)$stmtMaxOrder->fetchColumn();

            $stmtInsertImg = $pdo->prepare("
                INSERT INTO item_images (
                    image_code,
                    item_id,
                    image,
                    image_order
                ) VALUES (
                    :image_code,
                    :item_id,
                    :image,
                    :image_order
                )
            ");

            foreach ($imagePaths as $p) {
                $maxOrder++;
                $stmtInsertImg->execute([
                    ':image_code'  => 'IMG-' . mt_rand(100000000, 999999999),
                    ':item_id'     => $existingItem['id'],
                    ':image'       => $p,
                    ':image_order' => min($maxOrder, 5)
                ]);
            }
        }

        // Normalize display order 1..N
        $stmtRemaining = $pdo->prepare("SELECT id FROM item_images WHERE item_id = :item_id ORDER BY image_order ASC, id ASC");
        $stmtRemaining->execute(['item_id' => $existingItem['id']]);
        $reOrderIds = $stmtRemaining->fetchAll(PDO::FETCH_COLUMN);

        $stmtUpdateOrder = $pdo->prepare("UPDATE item_images SET image_order = :ord WHERE id = :id");
        $newOrder = 1;
        foreach ($reOrderIds as $imgId) {
            $stmtUpdateOrder->execute([':ord' => $newOrder, ':id' => $imgId]);
            $newOrder++;
        }

        $pdo->commit();

        http_response_code(200);
        echo json_encode([
            'success'   => true,
            'message'   => 'Report updated successfully',
            'report_id' => (int)$existingItem['id'],
            'item_code' => $existingItem['item_code'],
            'is_edit'   => true
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($imagePaths as $path) {
            $abs = __DIR__ . '/../../' . ltrim($path, '/');
            if (file_exists($abs) && is_file($abs)) {
                @unlink($abs);
            }
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'errors'  => ['server' => 'An internal database error occurred while updating your report.']
        ]);
        exit;
    }
}

// ==========================================================================
// 5. CREATE MODE BRANCH (INSERT new item)
// ==========================================================================

try {
    $pdo->beginTransaction();

    // Map UI Verification Method to DB ENUM('description', 'questions')
    // and consolidate missing/extra fields into additional_note
    $dbVerificationMethod = 'description';
    if ($validatedData['verification_method'] === 'secret_question') {
        $dbVerificationMethod = 'questions';
    }

    // Combine additional data that doesn't have explicit columns in the schema
    $additionalNotes = [];
    if (!empty($validatedData['distinguishing_features'])) {
        $additionalNotes[] = "Features: " . $validatedData['distinguishing_features'];
    }
    if (!empty($validatedData['verification_detail'])) {
        $additionalNotes[] = "Verification Detail: " . $validatedData['verification_detail'];
    }
    if (!empty($validatedData['contact_preference'])) {
        $additionalNotes[] = "Preferred Contact: " . $validatedData['contact_preference'];
    }
    if (!empty($validatedData['report_time'])) {
        $additionalNotes[] = "Time: " . $validatedData['report_time'];
    }
    
    $finalAdditionalNote = !empty($additionalNotes) ? implode("\n", $additionalNotes) : null;

    // Generate unique 13-character Item Code
    $itemCode = 'ITM-' . mt_rand(100000000, 999999999);
    $initialStatus = 'pending'; // Derived from schema DEFAULT 'pending'

    // Insert Item
    $stmtItem = $pdo->prepare("
        INSERT INTO items (
            item_code,
            user_id,
            title,
            description,
            category,
            subcategory,
            report_type,
            event_location,
            event_date,
            additional_note,
            verification_method,
            status
        ) VALUES (
            :item_code,
            :user_id,
            :title,
            :description,
            :category,
            :subcategory,
            :report_type,
            :event_location,
            :event_date,
            :additional_note,
            :verification_method,
            :status
        )
    ");

    $stmtItem->execute([
        ':item_code'           => $itemCode,
        ':user_id'             => $userId,
        ':title'               => $validatedData['item_name'],
        ':description'         => $validatedData['description'],
        ':category'            => $validatedData['category'],
        ':subcategory'         => 'N/A', // Not collected by UI
        ':report_type'         => $validatedData['type'],
        ':event_location'      => $validatedData['location'],
        ':event_date'          => $validatedData['report_date'],
        ':additional_note'     => $finalAdditionalNote,
        ':verification_method' => $dbVerificationMethod,
        ':status'              => $initialStatus
    ]);

    $itemId = $pdo->lastInsertId();

    // Insert Images
    if (!empty($imagePaths)) {
        $stmtImage = $pdo->prepare("
            INSERT INTO item_images (
                image_code,
                item_id,
                image,
                image_order
            ) VALUES (
                :image_code,
                :item_id,
                :image,
                :image_order
            )
        ");

        $order = 1;
        foreach ($imagePaths as $path) {
            $stmtImage->execute([
                ':image_code'  => 'IMG-' . mt_rand(100000000, 999999999),
                ':item_id'     => $itemId,
                ':image'       => $path,
                ':image_order' => $order
            ]);
            $order++;
        }
    }

    // Commit Transaction
    $pdo->commit();

    // ==========================================================================
    // 5. SUCCESS RESPONSE
    // ==========================================================================
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Report submitted successfully',
        'report_id' => $itemId,
        'item_code' => $itemCode
    ]);

} catch (Exception $e) {
    // Rollback database
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Clean up uploaded files since the database transaction failed
    foreach ($imagePaths as $path) {
        // Convert the relative path back to an absolute filesystem path for unlinking
        $absolutePath = __DIR__ . '/../../' . $path;
        if (file_exists($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    http_response_code(500);
    // Do not expose stack trace or sensitive DB errors to the client
    echo json_encode([
        'success' => false,
        'errors'  => ['server' => 'An internal database error occurred while saving your report.']
    ]);
}
