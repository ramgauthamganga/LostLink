<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LostLink Business Constants
|--------------------------------------------------------------------------
|
| Stores application-wide business constants used throughout
| the project.
|
*/


/*
|--------------------------------------------------------------------------
| User Roles
|--------------------------------------------------------------------------
*/

define('ROLE_STUDENT', 'student');
define('ROLE_ADMIN', 'admin');


/*
|--------------------------------------------------------------------------
| Report Types
|--------------------------------------------------------------------------
*/

define('REPORT_LOST', 'lost');
define('REPORT_FOUND', 'found');


/*
|--------------------------------------------------------------------------
| Item Status
|--------------------------------------------------------------------------
*/

define('ITEM_PENDING', 'pending');
define('ITEM_ACTIVE', 'active');
define('ITEM_CLAIMED', 'claimed');
define('ITEM_RETURNED', 'returned');
define('ITEM_CLOSED', 'closed');


/*
|--------------------------------------------------------------------------
| Claim Status
|--------------------------------------------------------------------------
*/

define('CLAIM_PENDING', 'pending');
define('CLAIM_APPROVED', 'approved');
define('CLAIM_REJECTED', 'rejected');
define('CLAIM_CANCELLED', 'cancelled');


/*
|--------------------------------------------------------------------------
| Verification Methods
|--------------------------------------------------------------------------
*/

define('VERIFY_DESCRIPTION', 'description');
define('VERIFY_QUESTIONS', 'questions');


/*
|--------------------------------------------------------------------------
| Notification Types
|--------------------------------------------------------------------------
*/

define('NOTIFICATION_SUCCESS', 'success');
define('NOTIFICATION_INFO', 'info');
define('NOTIFICATION_WARNING', 'warning');
define('NOTIFICATION_ERROR', 'error');


/*
|--------------------------------------------------------------------------
| Notification Categories
|--------------------------------------------------------------------------
*/

define('CATEGORY_ACCOUNT', 'account');
define('CATEGORY_REPORT', 'report');
define('CATEGORY_CLAIM', 'claim');
define('CATEGORY_ADMIN', 'admin');
define('CATEGORY_SYSTEM', 'system');
define('CATEGORY_CONTACT', 'contact');


/*
|--------------------------------------------------------------------------
| Contact Request Status
|--------------------------------------------------------------------------
*/

define('CONTACT_PENDING', 'pending');
define('CONTACT_ACCEPTED', 'accepted');
define('CONTACT_REJECTED', 'rejected');
define('CONTACT_CLOSED', 'closed');


/*
|--------------------------------------------------------------------------
| Conversation Status
|--------------------------------------------------------------------------
*/

define('CONVERSATION_ACTIVE', 'active');
define('CONVERSATION_CLOSED', 'closed');


/*
|--------------------------------------------------------------------------
| Announcement Categories
|--------------------------------------------------------------------------
*/

define('ANC_CAT_COLLEGE', 'college');
define('ANC_CAT_LOSTLINK', 'lostlink');
define('ANC_CAT_SYSTEM', 'system');
define('ANC_CAT_EVENT', 'event');
define('ANC_CAT_IMPORTANT', 'important');


/*
|--------------------------------------------------------------------------
| Announcement Priorities
|--------------------------------------------------------------------------
*/

define('ANC_PRIORITY_NORMAL', 'normal');
define('ANC_PRIORITY_HIGH', 'high');
define('ANC_PRIORITY_URGENT', 'urgent');


/*
|--------------------------------------------------------------------------
| Announcement Statuses
|--------------------------------------------------------------------------
*/

define('ANC_STATUS_DRAFT', 'draft');
define('ANC_STATUS_PUBLISHED', 'published');
define('ANC_STATUS_SCHEDULED', 'scheduled');
define('ANC_STATUS_ARCHIVED', 'archived');
