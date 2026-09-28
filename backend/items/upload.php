<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * LOSTLINK SECURE IMAGE UPLOAD LAYER
 * ==============================================================================
 * This file handles secure image uploads for the Report Item process.
 * It strictly validates files, enforces limits, generates secure filenames,
 * and handles partial failures atomically. It does NOT interact with the database.
 */

require_once __DIR__ . '/../../config/config.php';

/**
 * Securely processes and uploads a batch of item images.
 * 
 * @param array $fileArray The specific $_FILES array element (e.g., $_FILES['images'])
 *                         Expected to be in the structure of multiple file uploads.
 * @return array Structured result containing 'success', 'paths', and 'errors'
 */
function uploadReportImages(array $fileArray): array
{
    $result = [
        'success' => false,
        'paths'   => [],
        'errors'  => []
    ];

    // If no files were provided (or field is empty), that's technically not an upload failure.
    // The validation.php layer or future controller decides if images are strictly required.
    // A missing or empty upload array is treated as "0 images uploaded successfully".
    if (empty($fileArray) || empty($fileArray['name'][0])) {
        $result['success'] = true;
        return $result;
    }

    $uploadPath = ITEM_UPLOAD_PATH;
    
    // Ensure the destination directory exists
    if (!is_dir($uploadPath)) {
        if (!mkdir($uploadPath, 0755, true)) {
            $result['errors'][] = 'Server configuration error: Upload directory unavailable.';
            return $result;
        }
    }

    // Restructure the $_FILES array for easier iteration
    $files = [];
    $fileCount = count($fileArray['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($fileArray['error'][$i] !== UPLOAD_ERR_NO_FILE) {
            $files[] = [
                'name'     => $fileArray['name'][$i],
                'type'     => $fileArray['type'][$i],
                'tmp_name' => $fileArray['tmp_name'][$i],
                'error'    => $fileArray['error'][$i],
                'size'     => $fileArray['size'][$i],
            ];
        }
    }

    // If files were submitted but all were UPLOAD_ERR_NO_FILE
    if (empty($files)) {
        $result['success'] = true;
        return $result;
    }

    // Enforce Maximum 5 Images
    // If more than 5 are submitted, we process the first 5 and discard the rest.
    $filesToProcess = array_slice($files, 0, MAX_ITEM_IMAGES);
    if (count($files) > MAX_ITEM_IMAGES) {
        // We log or flag that extra files were discarded, but we don't necessarily fail the whole upload.
        // The prompt says: "process ONLY the first 5 valid upload entries... report that extra files were discarded"
        $result['errors'][] = 'Warning: More than ' . MAX_ITEM_IMAGES . ' images were provided. Extra images were discarded.';
    }

    $uploadedPaths = []; // Track paths internally to clean up on failure
    $relativePaths = []; // Track paths to return to the application

    // Map accepted MIME types to safe extensions
    $mimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    foreach ($filesToProcess as $index => $file) {
        $error = $file['error'];
        $tmpName = $file['tmp_name'];
        $originalName = $file['name'];
        $size = $file['size'];

        // 1. Check for PHP upload errors
        if ($error !== UPLOAD_ERR_OK) {
            $result['errors'][] = "File " . ($index + 1) . " failed to upload (Error Code: $error).";
            break; // Stop on first error to keep upload atomic
        }

        // 2. Validate existence and genuine upload
        if (!is_uploaded_file($tmpName)) {
            $result['errors'][] = "File " . ($index + 1) . " is not a valid uploaded file.";
            break;
        }

        // 3. File size validation
        if ($size > MAX_UPLOAD_SIZE) {
            $result['errors'][] = "File " . ($index + 1) . " exceeds the maximum allowed size of " . (MAX_UPLOAD_SIZE / 1024 / 1024) . "MB.";
            break;
        }

        // 4. Secure MIME type validation using Fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualMime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($actualMime, ALLOWED_IMAGE_TYPES, true)) {
            $result['errors'][] = "File " . ($index + 1) . " has an unsupported image format ($actualMime).";
            break;
        }
        
        // 5. Check if it's a genuine image by reading its dimensions
        // This prevents malicious files pretending to be images
        $imageSizeInfo = @getimagesize($tmpName);
        if ($imageSizeInfo === false) {
            $result['errors'][] = "File " . ($index + 1) . " is corrupted or not a valid image.";
            break;
        }

        // 6. Generate a secure, unique filename
        $safeExtension = $mimeToExtension[$actualMime];
        $secureFilename = bin2hex(random_bytes(16)) . '_' . time() . '.' . $safeExtension;
        
        $destinationPath = rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . $secureFilename;
        $relativePath = 'assets/uploads/items/' . $secureFilename;

        // 7. Move the uploaded file
        if (move_uploaded_file($tmpName, $destinationPath)) {
            $uploadedPaths[] = $destinationPath;
            $relativePaths[] = $relativePath;
        } else {
            $result['errors'][] = "Failed to save file " . ($index + 1) . " to storage.";
            break; // Stop on first error
        }
    }

    // Atomic upload handler:
    // If we encountered ANY error during the loop (which causes a break), the count of 
    // uploaded files won't match the number of files we tried to process (unless it failed on 0).
    // The only exception is the discard warning, which doesn't fail the loop.
    $hasFatalError = false;
    foreach ($result['errors'] as $err) {
        if (strpos($err, 'Warning:') !== 0) {
            $hasFatalError = true;
            break;
        }
    }

    if ($hasFatalError) {
        // Partial or complete failure occurred. Clean up orphan files immediately.
        foreach ($uploadedPaths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        
        $result['success'] = false;
        $result['paths'] = []; // Clear paths on failure
    } else {
        // Complete success for the processed batch
        $result['success'] = true;
        $result['paths'] = $relativePaths;
    }

    return $result;
}
