-- =========================================================
-- LostLink - Realistic Demo Dataset
-- =========================================================
-- Target: Local development & end-to-end multi-role testing
-- Safe & Demo-Scoped: Manages only records with @lostlink.test emails
-- Preserves all existing non-demo developer records.
-- =========================================================

START TRANSACTION;

-- ---------------------------------------------------------
-- 1. CLEANUP PREVIOUS DEMO DATA (Scoped to demo identifiers)
-- ---------------------------------------------------------

-- Notifications belonging to demo users
DELETE FROM notifications 
WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@lostlink.test');

-- Messages in demo conversations
DELETE FROM messages 
WHERE conversation_id IN (
    SELECT c.id FROM conversations c 
    INNER JOIN users u ON (c.participant_one_id = u.id OR c.participant_two_id = u.id)
    WHERE u.email LIKE '%@lostlink.test'
);

-- Demo conversations
DELETE FROM conversations 
WHERE participant_one_id IN (SELECT id FROM users WHERE email LIKE '%@lostlink.test')
   OR participant_two_id IN (SELECT id FROM users WHERE email LIKE '%@lostlink.test');

-- Demo contact requests
DELETE FROM contact_requests 
WHERE sender_id IN (SELECT id FROM users WHERE email LIKE '%@lostlink.test')
   OR reporter_id IN (SELECT id FROM users WHERE email LIKE '%@lostlink.test');

-- Break circular FK: set approved_claim_id = NULL on demo items
UPDATE items SET approved_claim_id = NULL 
WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@lostlink.test');

-- Demo claims
DELETE FROM claims 
WHERE claimant_id IN (SELECT id FROM users WHERE email LIKE '%@lostlink.test')
   OR item_id IN (SELECT id FROM items WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@lostlink.test'));

-- Demo item images
DELETE FROM item_images 
WHERE item_id IN (SELECT id FROM items WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@lostlink.test'));

-- Demo items
DELETE FROM items 
WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@lostlink.test');

-- Demo users
DELETE FROM users 
WHERE email LIKE '%@lostlink.test';

-- ---------------------------------------------------------
-- 2. INSERT DEMO USERS (8 Accounts: 2 Admins, 6 Students)
-- ---------------------------------------------------------
-- Passwords hashed with standard bcrypt (PASSWORD_DEFAULT):
-- Admin 1: Admin123
-- Admin 2: Admin456
-- User 1:  Rahul123
-- User 2:  Meera123
-- User 3:  Adithya123
-- User 4:  Nikhil123
-- User 5:  Diya123
-- User 6:  Vivek123

INSERT INTO users (
    user_code, name, student_id, email, password, department, phone, profile_image, role, is_banned, created_at
) VALUES 
(
    'USR-DEMO00001',
    'Arjun Nair',
    'ADM-2026-001',
    'arjun.admin@lostlink.test',
    '$2y$10$6c9BtiDrERM3hI3jz6U9LuMtq.Ahng6qpzuR3UHoe.dTOzEiM7jxG',
    'Administration & Campus Security',
    '9876543210',
    'default-profile.png',
    'admin',
    0,
    '2026-09-01 09:00:00'
),
(
    'USR-DEMO00002',
    'Ananya Menon',
    'ADM-2026-002',
    'ananya.admin@lostlink.test',
    '$2y$10$2o3axEjpwsoEMjpAFYS0UeyBEjT0bhSKGUwkhJd26fO5iq4lOK51u',
    'Dean of Student Affairs',
    '9876543211',
    'default-profile.png',
    'admin',
    0,
    '2026-09-01 09:15:00'
),
(
    'USR-DEMO00003',
    'Rahul Krishnan',
    'STU-2024-001',
    'rahul.user@lostlink.test',
    '$2y$10$V3nCKxQCutTDi2xYxh.4A.QUZE8ktp.cl5qK1W6qJv/sURVaAHYue',
    'Computer Science & Engineering',
    '9876543212',
    'default-profile.png',
    'student',
    0,
    '2026-09-02 10:00:00'
),
(
    'USR-DEMO00004',
    'Meera Thomas',
    'STU-2024-002',
    'meera.user@lostlink.test',
    '$2y$10$eM1Xjd3Fxv.R1lu6iJpQaeKmaAvnxIQl3Qcw0bqKD196GoOlTSH7m',
    'Electronics & Communication',
    '9876543213',
    'default-profile.png',
    'student',
    0,
    '2026-09-02 10:30:00'
),
(
    'USR-DEMO00005',
    'Adithya Varma',
    'STU-2024-003',
    'adithya.user@lostlink.test',
    '$2y$10$SNrO2iKTnV1EuCdUhNsdwu6NFSJY11HVqmZS8xnIk9UzDZ8S6R6Um',
    'Mechanical Engineering',
    '9876543214',
    'default-profile.png',
    'student',
    0,
    '2026-09-03 11:00:00'
),
(
    'USR-DEMO00006',
    'Nikhil Joseph',
    'STU-2024-004',
    'nikhil.user@lostlink.test',
    '$2y$10$3AFvu.t85FLxHMUZ7bbLyuki81XkBd7fp1Lb8ErV2enIZfOngffLi',
    'Civil Engineering',
    '9876543215',
    'default-profile.png',
    'student',
    0,
    '2026-09-03 11:30:00'
),
(
    'USR-DEMO00007',
    'Diya Raj',
    'STU-2024-005',
    'diya.user@lostlink.test',
    '$2y$10$Ctc./pOpst0.Aqj3THeqS.ZU7kb6TVNO1fivwaqQtDJFZMygRGfaK',
    'Information Technology',
    '9876543216',
    'default-profile.png',
    'student',
    0,
    '2026-09-04 12:00:00'
),
(
    'USR-DEMO00008',
    'Vivek Suresh',
    'STU-2024-006',
    'vivek.user@lostlink.test',
    '$2y$10$rJKh7J8wrB2cfIcp2ft4BOEIDWZAi/VBmUHhgTD70buBpZfkHWZr2',
    'Electrical & Electronics',
    '9876543217',
    'default-profile.png',
    'student',
    0,
    '2026-09-04 12:30:00'
);

-- ---------------------------------------------------------
-- 3. INSERT DEMO ITEMS (11 Items across multiple statuses)
-- ---------------------------------------------------------

INSERT INTO items (
    item_code, user_id, title, description, category, subcategory, report_type, event_location, event_date, additional_note, verification_method, status, approved_claim_id, created_at
) VALUES
-- Item 1: Arjun Nair (Admin with report)
(
    'ITM-DEMO00001',
    (SELECT id FROM users WHERE email = 'arjun.admin@lostlink.test'),
    'Dell Professional Laptop Backpack',
    'Black Dell professional 15.6-inch backpack with blue accent zippers. Contains a notebook and charger cable.',
    'bags_wallets',
    'Laptop Bags',
    'lost',
    'Main Auditorium, Row F',
    '2026-09-12',
    'Lost right after the morning department symposium.',
    'description',
    'active',
    NULL,
    '2026-09-12 14:00:00'
),
-- Item 2: Rahul Krishnan (Lost Watch - has conversation & pending claim)
(
    'ITM-DEMO00002',
    (SELECT id FROM users WHERE email = 'rahul.user@lostlink.test'),
    'Vintage Casio Youth Digital Watch',
    'Vintage Casio Youth digital watch with black resin strap and gold-tone bezel.',
    'accessories',
    'Watches',
    'lost',
    'Central Library, 2nd Floor Reading Hall',
    '2026-09-14',
    'Has a subtle scratch near the lower right edge of the acrylic dial.',
    'description',
    'active',
    NULL,
    '2026-09-14 10:30:00'
),
-- Item 3: Meera Thomas (Found Wallet - will be claimed and approved by Vivek)
(
    'ITM-DEMO00003',
    (SELECT id FROM users WHERE email = 'meera.user@lostlink.test'),
    'Black Leather Bi-Fold Wallet',
    'Bi-fold black leather wallet containing campus transit card and some receipts.',
    'bags_wallets',
    'Wallets',
    'found',
    'Campus Cafeteria, Table 14',
    '2026-09-10',
    'Found near the beverage counter during lunch hour.',
    'description',
    'claimed',
    NULL,
    '2026-09-10 13:00:00'
),
-- Item 4: Adithya Varma (Lost USB Drive)
(
    'ITM-DEMO00004',
    (SELECT id FROM users WHERE email = 'adithya.user@lostlink.test'),
    'Kingston DataTraveler 64GB USB Drive',
    'Kingston DataTraveler 64GB USB 3.2 flash drive in silver metal casing with blue lanyard loop.',
    'electronics',
    'Storage Devices',
    'lost',
    'Mechanical CAD Lab, Workstation 12',
    '2026-09-15',
    'Contains final year CAD drawings and assignment submissions.',
    'description',
    'active',
    NULL,
    '2026-09-15 16:00:00'
),
-- Item 5: Diya Raj (Found Earbuds - has pending contact request & rejected claim)
(
    'ITM-DEMO00005',
    (SELECT id FROM users WHERE email = 'diya.user@lostlink.test'),
    'White True Wireless Stereo Earbuds',
    'Pair of white true wireless stereo earbuds inside an oval charging case with matte finish.',
    'electronics',
    'Audio',
    'found',
    'Science Block Seminar Hall A',
    '2026-09-16',
    'Left on the front row armrest after guest lecture.',
    'description',
    'active',
    NULL,
    '2026-09-16 12:15:00'
),
-- Item 6: Rahul Krishnan (Lost Water Bottle)
(
    'ITM-DEMO00006',
    (SELECT id FROM users WHERE email = 'rahul.user@lostlink.test'),
    'Milton Insulated Navy Blue Water Bottle',
    'Milton 1000ml stainless steel insulated water bottle in matte navy blue with scratch on lid.',
    'other',
    'Personal Items',
    'lost',
    'Basketball Court Bleachers',
    '2026-09-13',
    'Left near the scorekeeper desk during evening practice.',
    'description',
    'active',
    NULL,
    '2026-09-13 18:30:00'
),
-- Item 7: Meera Thomas (Found ID Card)
(
    'ITM-DEMO00007',
    (SELECT id FROM users WHERE email = 'meera.user@lostlink.test'),
    'College Student Identity Card',
    'College student identity card in clear plastic lanyard holder.',
    'documents',
    'Cards & IDs',
    'found',
    'Campus Health Center Waiting Area',
    '2026-09-15',
    'Handed to security desk at administration reception.',
    'description',
    'active',
    NULL,
    '2026-09-15 11:00:00'
),
-- Item 8: Adithya Varma (Lost Notebook & Calculator)
(
    'ITM-DEMO00008',
    (SELECT id FROM users WHERE email = 'adithya.user@lostlink.test'),
    'College Notebook & Scientific Calculator',
    'Classmate spiral ruled notebook along with a Casio FX-991EX scientific calculator.',
    'documents',
    'Stationery',
    'lost',
    'Lecture Hall 204',
    '2026-09-11',
    'Last used during 3rd period Thermodynamics class.',
    'description',
    'active',
    NULL,
    '2026-09-11 15:45:00'
),
-- Item 9: Diya Raj (Returned Umbrella)
(
    'ITM-DEMO00009',
    (SELECT id FROM users WHERE email = 'diya.user@lostlink.test'),
    'Compact 3-Fold Black Umbrella',
    '3-fold black compact umbrella with wooden curved handle and auto open button.',
    'accessories',
    'Umbrellas',
    'found',
    'East Gate Bus Shelter',
    '2026-09-09',
    'Returned to original owner after verification.',
    'description',
    'returned',
    NULL,
    '2026-09-09 17:00:00'
),
-- Item 10: Rahul Krishnan (Closed House Keys)
(
    'ITM-DEMO00010',
    (SELECT id FROM users WHERE email = 'rahul.user@lostlink.test'),
    'Set of House Keys with Brass Keychain',
    'Ring with three Godrej brass door keys and a silver automobile badge keychain.',
    'accessories',
    'Keys',
    'lost',
    'Campus Parking Lot B, Near Bike Stand',
    '2026-09-08',
    'Report closed after replacing the lock set.',
    'description',
    'closed',
    NULL,
    '2026-09-08 09:30:00'
),
-- Item 11: Arjun Nair (Admin with second report, pending status)
(
    'ITM-DEMO00011',
    (SELECT id FROM users WHERE email = 'arjun.admin@lostlink.test'),
    'Grey Swiss Military Backpack',
    'Grey water-resistant Swiss Military backpack found on bench near locker rooms.',
    'bags_wallets',
    'Backpacks',
    'found',
    'Sports Complex Gym Area',
    '2026-09-17',
    'Currently held at security room awaiting owner claim.',
    'description',
    'pending',
    NULL,
    '2026-09-17 19:00:00'
);

-- ---------------------------------------------------------
-- 4. INSERT DEMO ITEM IMAGES (Reusing existing real assets)
-- ---------------------------------------------------------

INSERT INTO item_images (image_code, item_id, image, image_order, created_at)
VALUES
(
    'IMG-DEMO00001',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00001'),
    'assets/uploads/items/9b47c03bb04984c21dbf8f9c8f9858c2_1789058369.jpg',
    1,
    '2026-09-12 14:05:00'
),
(
    'IMG-DEMO00002',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00002'),
    'assets/uploads/items/a0e9aebfe3d0af5faaf6a4f32f69c993_1789058516.jpg',
    1,
    '2026-09-14 10:35:00'
),
(
    'IMG-DEMO00003',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00003'),
    'assets/uploads/items/9c58d7aacf254308028cee74fbf994bc_1789525397.png',
    1,
    '2026-09-10 13:05:00'
),
(
    'IMG-DEMO00005',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00005'),
    'assets/uploads/items/c76be7dcef60a2edc54a31b3c5d3ad69_1789525506.jpg',
    1,
    '2026-09-16 12:20:00'
),
(
    'IMG-DEMO00007',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00007'),
    'assets/uploads/items/36e788c39de4dba67437bca50f42f411_1789562390.png',
    1,
    '2026-09-15 11:05:00'
);

-- ---------------------------------------------------------
-- 5. INSERT DEMO CLAIMS (Pending, Approved, Rejected)
-- ---------------------------------------------------------

INSERT INTO claims (
    claim_code, item_id, claimant_id, verification_response, status, reporter_note, approved_at, rejected_at, created_at
) VALUES
-- Claim 1: PENDING (Adithya claims Rahul's Casio Watch)
(
    'CLM-DEMO00001',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00002'),
    (SELECT id FROM users WHERE email = 'adithya.user@lostlink.test'),
    '{"description": "I lost my Casio digital watch on the 2nd floor library reading table. It has a distinctive scratch on the bottom right corner of the dial glass and the alarm was set for 6:00 AM."}',
    'pending',
    NULL,
    NULL,
    NULL,
    '2026-09-14 11:30:00'
),
-- Claim 2: APPROVED (Vivek claims Meera's Wallet)
(
    'CLM-DEMO00002',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00003'),
    (SELECT id FROM users WHERE email = 'vivek.user@lostlink.test'),
    '{"description": "My black bi-fold wallet has my campus transit pass and my name Vivek S. on the emergency contact slip inside the cash pocket."}',
    'approved',
    'Verified transit pass details with claimant in person. Ownership confirmed.',
    '2026-09-11 14:30:00',
    NULL,
    '2026-09-10 15:00:00'
),
-- Claim 3: REJECTED (Nikhil claims Diya's Earbuds)
(
    'CLM-DEMO00003',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00005'),
    (SELECT id FROM users WHERE email = 'nikhil.user@lostlink.test'),
    '{"description": "White earbuds lost in seminar hall. I think they are Boat Airdopes."}',
    'rejected',
    'The found earbuds are Noise Buds VS102, not Boat Airdopes. Verification details did not match.',
    NULL,
    '2026-09-16 17:00:00',
    '2026-09-16 14:00:00'
);

-- Update Item 3 to link the approved claim
UPDATE items 
SET approved_claim_id = (SELECT id FROM claims WHERE claim_code = 'CLM-DEMO00002')
WHERE item_code = 'ITM-DEMO00003';

-- ---------------------------------------------------------
-- 6. INSERT DEMO CONTACT REQUESTS
-- ---------------------------------------------------------

INSERT INTO contact_requests (
    request_code, item_id, sender_id, reporter_id, initial_message, status, rejection_reason, responded_at, created_at
) VALUES
-- Request 1: ACCEPTED (Adithya -> Rahul regarding Casio Watch)
(
    'REQ-DEMO00001',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00002'),
    (SELECT id FROM users WHERE email = 'adithya.user@lostlink.test'),
    (SELECT id FROM users WHERE email = 'rahul.user@lostlink.test'),
    'Hi Rahul, I noticed your report for the Casio watch. I think it might be the one I lost near the library reading hall.',
    'accepted',
    NULL,
    '2026-09-14 11:10:00',
    '2026-09-14 11:00:00'
),
-- Request 2: PENDING (Nikhil -> Diya regarding Earbuds)
(
    'REQ-DEMO00002',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00005'),
    (SELECT id FROM users WHERE email = 'nikhil.user@lostlink.test'),
    (SELECT id FROM users WHERE email = 'diya.user@lostlink.test'),
    'Hi Diya, I saw your report for the wireless earbuds found at the Science Block. Could you tell me what brand or model the charging case is?',
    'pending',
    NULL,
    NULL,
    '2026-09-16 13:00:00'
),
-- Request 3: REJECTED (Vivek -> Arjun regarding Dell Bag)
(
    'REQ-DEMO00003',
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00001'),
    (SELECT id FROM users WHERE email = 'vivek.user@lostlink.test'),
    (SELECT id FROM users WHERE email = 'arjun.admin@lostlink.test'),
    'Hello, is this backpack a blue Samsonite bag? I left one in the auditorium.',
    'rejected',
    'This bag is a black Dell professional backpack, not a blue Samsonite. Hope you find yours soon.',
    '2026-09-12 16:30:00',
    '2026-09-12 15:30:00'
);

-- ---------------------------------------------------------
-- 7. INSERT DEMO CONVERSATIONS & MESSAGES
-- ---------------------------------------------------------

INSERT INTO conversations (
    conversation_code, contact_request_id, item_id, participant_one_id, participant_two_id, status, created_at
) VALUES
(
    'CNV-DEMO00001',
    (SELECT id FROM contact_requests WHERE request_code = 'REQ-DEMO00001'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00002'),
    (SELECT id FROM users WHERE email = 'adithya.user@lostlink.test'),
    (SELECT id FROM users WHERE email = 'rahul.user@lostlink.test'),
    'active',
    '2026-09-14 11:10:00'
);

INSERT INTO messages (
    message_code, conversation_id, sender_id, message, is_read, read_at, created_at
) VALUES
(
    'MSG-DEMO00001',
    (SELECT id FROM conversations WHERE conversation_code = 'CNV-DEMO00001'),
    (SELECT id FROM users WHERE email = 'adithya.user@lostlink.test'),
    'Hi Rahul, I noticed your report for the Casio watch. I think it might be the one I lost near the library reading hall.',
    1,
    '2026-09-14 11:12:00',
    '2026-09-14 11:05:00'
),
(
    'MSG-DEMO00002',
    (SELECT id FROM conversations WHERE conversation_code = 'CNV-DEMO00001'),
    (SELECT id FROM users WHERE email = 'rahul.user@lostlink.test'),
    'Hi Adithya, thanks for reaching out. Can you tell me something distinctive about it to confirm?',
    1,
    '2026-09-14 11:16:00',
    '2026-09-14 11:15:00'
),
(
    'MSG-DEMO00003',
    (SELECT id FROM conversations WHERE conversation_code = 'CNV-DEMO00001'),
    (SELECT id FROM users WHERE email = 'adithya.user@lostlink.test'),
    'Yes, there is a small scratch near the lower-right side of the dial and the resin strap is slightly worn near the buckle.',
    1,
    '2026-09-14 11:21:00',
    '2026-09-14 11:20:00'
),
(
    'MSG-DEMO00004',
    (SELECT id FROM conversations WHERE conversation_code = 'CNV-DEMO00001'),
    (SELECT id FROM users WHERE email = 'rahul.user@lostlink.test'),
    'Yes, I remember that mark on the dial! Please submit a formal claim on the item page so I can review and approve it.',
    1,
    '2026-09-14 11:26:00',
    '2026-09-14 11:25:00'
),
(
    'MSG-DEMO00005',
    (SELECT id FROM conversations WHERE conversation_code = 'CNV-DEMO00001'),
    (SELECT id FROM users WHERE email = 'adithya.user@lostlink.test'),
    'I have just submitted the claim with the full details. Please check and let me know when we can meet.',
    0,
    NULL,
    '2026-09-14 11:30:00'
);

-- ---------------------------------------------------------
-- 8. INSERT DEMO NOTIFICATIONS
-- ---------------------------------------------------------

INSERT INTO notifications (
    notification_code, user_id, item_id, claim_id, contact_request_id, conversation_id, title, message, type, category, is_read, read_at, created_at
) VALUES
-- NTF 1: Rahul received contact request from Adithya (Read)
(
    'NTF-DEMO00001',
    (SELECT id FROM users WHERE email = 'rahul.user@lostlink.test'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00002'),
    NULL,
    (SELECT id FROM contact_requests WHERE request_code = 'REQ-DEMO00001'),
    NULL,
    'New Contact Request',
    'Adithya Varma has sent you a contact request regarding Vintage Casio Youth Digital Watch.',
    'info',
    'contact',
    1,
    '2026-09-14 11:10:00',
    '2026-09-14 11:00:00'
),
-- NTF 2: Rahul received new unread chat message from Adithya (Unread)
(
    'NTF-DEMO00002',
    (SELECT id FROM users WHERE email = 'rahul.user@lostlink.test'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00002'),
    NULL,
    NULL,
    (SELECT id FROM conversations WHERE conversation_code = 'CNV-DEMO00001'),
    'New Message Received',
    'Adithya Varma sent you a message: I have just submitted the claim with the full details...',
    'info',
    'contact',
    0,
    NULL,
    '2026-09-14 11:30:00'
),
-- NTF 3: Rahul received claim from Adithya (Unread)
(
    'NTF-DEMO00003',
    (SELECT id FROM users WHERE email = 'rahul.user@lostlink.test'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00002'),
    (SELECT id FROM claims WHERE claim_code = 'CLM-DEMO00001'),
    NULL,
    NULL,
    'New Claim Submitted',
    'Adithya Varma submitted an ownership claim for Vintage Casio Youth Digital Watch.',
    'info',
    'claim',
    0,
    NULL,
    '2026-09-14 11:30:00'
),
-- NTF 4: Adithya's contact request was accepted by Rahul (Read)
(
    'NTF-DEMO00004',
    (SELECT id FROM users WHERE email = 'adithya.user@lostlink.test'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00002'),
    NULL,
    (SELECT id FROM contact_requests WHERE request_code = 'REQ-DEMO00001'),
    (SELECT id FROM conversations WHERE conversation_code = 'CNV-DEMO00001'),
    'Contact Request Accepted',
    'Rahul Krishnan accepted your contact request for Vintage Casio Youth Digital Watch. You can now chat.',
    'success',
    'contact',
    1,
    '2026-09-14 11:15:00',
    '2026-09-14 11:10:00'
),
-- NTF 5: Meera received claim from Vivek (Read)
(
    'NTF-DEMO00005',
    (SELECT id FROM users WHERE email = 'meera.user@lostlink.test'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00003'),
    (SELECT id FROM claims WHERE claim_code = 'CLM-DEMO00002'),
    NULL,
    NULL,
    'Claim Submitted',
    'Vivek Suresh submitted a claim for Black Leather Bi-Fold Wallet.',
    'info',
    'claim',
    1,
    '2026-09-10 15:10:00',
    '2026-09-10 15:00:00'
),
-- NTF 6: Vivek's claim was approved by Meera (Read)
(
    'NTF-DEMO00006',
    (SELECT id FROM users WHERE email = 'vivek.user@lostlink.test'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00003'),
    (SELECT id FROM claims WHERE claim_code = 'CLM-DEMO00002'),
    NULL,
    NULL,
    'Claim Approved',
    'Your claim for Black Leather Bi-Fold Wallet has been approved by Meera Thomas.',
    'success',
    'claim',
    1,
    '2026-09-11 15:00:00',
    '2026-09-11 14:30:00'
),
-- NTF 7: Vivek's contact request was declined by Arjun Nair (Read)
(
    'NTF-DEMO00007',
    (SELECT id FROM users WHERE email = 'vivek.user@lostlink.test'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00001'),
    NULL,
    (SELECT id FROM contact_requests WHERE request_code = 'REQ-DEMO00003'),
    NULL,
    'Contact Request Declined',
    'Your inquiry for Dell Professional Laptop Backpack was declined by Arjun Nair.',
    'warning',
    'contact',
    1,
    '2026-09-12 17:00:00',
    '2026-09-12 16:30:00'
),
-- NTF 8: Diya received contact request from Nikhil (Unread)
(
    'NTF-DEMO00008',
    (SELECT id FROM users WHERE email = 'diya.user@lostlink.test'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00005'),
    NULL,
    (SELECT id FROM contact_requests WHERE request_code = 'REQ-DEMO00002'),
    NULL,
    'New Contact Request',
    'Nikhil Joseph sent you an inquiry regarding White True Wireless Stereo Earbuds.',
    'info',
    'contact',
    0,
    NULL,
    '2026-09-16 13:00:00'
),
-- NTF 9: Diya received claim from Nikhil (Read)
(
    'NTF-DEMO00009',
    (SELECT id FROM users WHERE email = 'diya.user@lostlink.test'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00005'),
    (SELECT id FROM claims WHERE claim_code = 'CLM-DEMO00003'),
    NULL,
    NULL,
    'Claim Submitted',
    'Nikhil Joseph submitted a claim for White True Wireless Stereo Earbuds.',
    'info',
    'claim',
    1,
    '2026-09-16 14:30:00',
    '2026-09-16 14:00:00'
),
-- NTF 10: Nikhil's claim was rejected by Diya (Read)
(
    'NTF-DEMO00010',
    (SELECT id FROM users WHERE email = 'nikhil.user@lostlink.test'),
    (SELECT id FROM items WHERE item_code = 'ITM-DEMO00005'),
    (SELECT id FROM claims WHERE claim_code = 'CLM-DEMO00003'),
    NULL,
    NULL,
    'Claim Rejected',
    'Your claim for White True Wireless Stereo Earbuds was not approved by Diya Raj.',
    'error',
    'claim',
    1,
    '2026-09-16 17:30:00',
    '2026-09-16 17:00:00'
);

COMMIT;
