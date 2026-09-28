<?php

/**
 * ==============================================================================
 * LOSTLINK REPORT ITEM VALIDATION LAYER
 * ==============================================================================
 * This file is strictly responsible for validating incoming data for the 
 * Report Item process. It does not perform database operations, image handling,
 * or HTML rendering.
 */

/**
 * Validates the submitted Report Item data.
 * 
 * @param array $input The raw incoming request data (typically $_POST)
 * @return array A structured array containing 'valid', 'errors', and 'data'
 */
function validateReportData(array $input): array
{
    $errors = [];
    $data = [];

    // ==========================================================================
    // 1. REPORT TYPE VALIDATION
    // Expected values from UI/DB: 'lost', 'found'
    // ==========================================================================
    $reportType = isset($input['type']) ? trim((string)$input['type']) : '';
    if (empty($reportType)) {
        $errors['type'] = 'Report type is required.';
    } elseif (!in_array($reportType, ['lost', 'found'], true)) {
        $errors['type'] = 'Invalid report type selected.';
    } else {
        $data['type'] = $reportType;
    }

    // ==========================================================================
    // 2. CATEGORY VALIDATION
    // Expected values from UI: 'electronics', 'bags_wallets', 'keys', 
    // 'documents', 'clothing', 'accessories', 'other'
    // ==========================================================================
    $validCategories = [
        'electronics', 'bags_wallets', 'keys', 
        'documents', 'clothing', 'accessories', 'other'
    ];
    $category = isset($input['category']) ? trim((string)$input['category']) : '';
    
    if (empty($category) || $category === 'Select category') {
        $errors['category'] = 'Please select a valid category.';
    } elseif (!in_array($category, $validCategories, true)) {
        $errors['category'] = 'Invalid category selected.';
    } else {
        $data['category'] = $category;
    }

    // ==========================================================================
    // 3. ITEM NAME VALIDATION
    // DB Column: `title` VARCHAR(150)
    // ==========================================================================
    $itemName = isset($input['item_name']) ? trim((string)$input['item_name']) : '';
    if (empty($itemName)) {
        $errors['item_name'] = 'Item name is required.';
    } elseif (mb_strlen($itemName, 'UTF-8') > 150) {
        $errors['item_name'] = 'Item name cannot exceed 150 characters.';
    } else {
        $data['item_name'] = $itemName;
    }

    // ==========================================================================
    // 4. DESCRIPTION VALIDATION
    // DB Column: `description` TEXT. Frontend enforces max 300 characters.
    // ==========================================================================
    $description = isset($input['description']) ? trim((string)$input['description']) : '';
    if (empty($description)) {
        $errors['description'] = 'Description is required.';
    } else {
        $descLength = mb_strlen($description, 'UTF-8');
        if ($descLength > 300) {
            $errors['description'] = 'Description cannot exceed 300 characters.';
        } else {
            $data['description'] = $description;
        }
    }

    // ==========================================================================
    // 5. LOCATION VALIDATION
    // DB Column: `event_location` VARCHAR(255)
    // ==========================================================================
    $location = isset($input['location']) ? trim((string)$input['location']) : '';
    if (empty($location)) {
        $errors['location'] = 'Location is required.';
    } elseif (mb_strlen($location, 'UTF-8') > 255) {
        $errors['location'] = 'Location cannot exceed 255 characters.';
    } else {
        $data['location'] = $location;
    }

    // ==========================================================================
    // 6. DATE VALIDATION
    // DB Column: `event_date` DATE
    // ==========================================================================
    $reportDate = isset($input['report_date']) ? trim((string)$input['report_date']) : '';
    if (empty($reportDate)) {
        $errors['report_date'] = 'Date is required.';
    } else {
        // Validate date format (YYYY-MM-DD) and check if it's a real calendar date
        $dateObj = DateTime::createFromFormat('Y-m-d', $reportDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $reportDate) {
            $errors['report_date'] = 'Please enter a valid date.';
        } else {
            // Check if date is in the future
            $currentDate = new DateTime();
            $currentDate->setTime(0, 0, 0); // reset time to midnight for accurate date comparison
            if ($dateObj > $currentDate) {
                $errors['report_date'] = 'Date cannot be in the future.';
            } else {
                $data['report_date'] = $reportDate;
            }
        }
    }

    // ==========================================================================
    // 7. TIME VALIDATION (Optional)
    // ==========================================================================
    $reportTime = isset($input['report_time']) ? trim((string)$input['report_time']) : '';
    if (!empty($reportTime)) {
        // Validate HH:MM or HH:MM:SS format
        if (!preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9](?::[0-5][0-9])?$/', $reportTime)) {
            $errors['report_time'] = 'Please enter a valid time.';
        } else {
            $data['report_time'] = $reportTime;
        }
    } else {
        $data['report_time'] = null; // Normalize empty optional field
    }

    // ==========================================================================
    // 8. ADDITIONAL INFO / DISTINGUISHING FEATURE (Optional)
    // DB Column: `additional_note` TEXT
    // ==========================================================================
    $distinguishingFeatures = isset($input['distinguishing_features']) ? trim((string)$input['distinguishing_features']) : '';
    if (!empty($distinguishingFeatures)) {
        // Safe check for TEXT column limits
        if (mb_strlen($distinguishingFeatures, 'UTF-8') > 2000) {
            $errors['distinguishing_features'] = 'Additional info is too long.';
        } else {
            $data['distinguishing_features'] = $distinguishingFeatures;
        }
    } else {
        $data['distinguishing_features'] = null;
    }

    // ==========================================================================
    // 9. VERIFICATION METHOD (Optional)
    // Exact values from UI: serial_number, receipt, unique_feature, passcode, 
    // secret_question, other
    // ==========================================================================
    $validVerificationMethods = [
        'serial_number', 'receipt', 'unique_feature', 
        'passcode', 'secret_question', 'other'
    ];
    $verificationMethod = isset($input['verification_method']) ? trim((string)$input['verification_method']) : '';
    
    if (!empty($verificationMethod)) {
        if (!in_array($verificationMethod, $validVerificationMethods, true)) {
            $errors['verification_method'] = 'Invalid verification method selected.';
        } else {
            $data['verification_method'] = $verificationMethod;
        }
    } else {
        $data['verification_method'] = null;
    }

    // ==========================================================================
    // 10. VERIFICATION DETAIL / QUESTION (Optional)
    // ==========================================================================
    $verificationDetail = isset($input['verification_detail']) ? trim((string)$input['verification_detail']) : '';
    if (!empty($verificationDetail)) {
        if (mb_strlen($verificationDetail, 'UTF-8') > 255) {
            $errors['verification_detail'] = 'Verification detail cannot exceed 255 characters.';
        } else {
            $data['verification_detail'] = $verificationDetail;
        }
    } else {
        $data['verification_detail'] = null;
    }

    // ==========================================================================
    // 11. PREFERRED CONTACT METHOD
    // Exact values from UI: Through LostLink Platform, Email, Phone
    // ==========================================================================
    $validContactMethods = ['Through LostLink Platform', 'Email', 'Phone'];
    $contactPreference = isset($input['contact_preference']) ? trim((string)$input['contact_preference']) : '';
    
    // UI treats this as having a default selection, so we treat empty as missing
    if (empty($contactPreference)) {
        $errors['contact_preference'] = 'Preferred contact method is required.';
    } elseif (!in_array($contactPreference, $validContactMethods, true)) {
        $errors['contact_preference'] = 'Invalid preferred contact method selected.';
    } else {
        $data['contact_preference'] = $contactPreference;
    }

    // ==========================================================================
    // RETURN STRUCTURED RESULT
    // ==========================================================================
    return [
        'valid'  => empty($errors),
        'errors' => $errors,
        'data'   => $data
    ];
}
