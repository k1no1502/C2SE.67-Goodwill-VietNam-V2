<?php
require_once __DIR__ . '/_base.php';

function getStaffRoleId() {
    $role = Database::fetch("SELECT role_id FROM roles WHERE role_name = 'staff' LIMIT 1");
    if ($role && isset($role['role_id'])) {
        return (int)$role['role_id'];
    }
    Database::execute(
        "INSERT INTO roles (role_id, role_name, description, permissions)
         VALUES (4, 'staff', 'Staff member', '{\"staff\": true}')
         ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), description = VALUES(description), permissions = VALUES(permissions)"
    );
    $role = Database::fetch("SELECT role_id FROM roles WHERE role_name = 'staff' LIMIT 1");
    return (int)($role['role_id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $payload['action'] ?? '';
    $applicationId = (int)($payload['application_id'] ?? 0);
    $note = sanitize($payload['admin_note'] ?? '');

    if ($applicationId <= 0 || $action === '') {
        api_json(false, ['message' => 'Invalid request.'], 400);
    }

    try {
        $application = Database::fetch(
            "SELECT * FROM recruitment_applications WHERE application_id = ?",
            [$applicationId]
        );
        if (!$application) {
            throw new Exception('Application not found.');
        }
        if (!in_array($application['status'], ['pending'], true)) {
            throw new Exception('Already processed.');
        }

        Database::beginTransaction();

        if ($action === 'approve') {
            $staffRoleId = getStaffRoleId();
            if ($staffRoleId === 0) {
                throw new Exception('Missing staff role.');
            }

            Database::execute(
                "UPDATE users SET role_id = ? WHERE user_id = ?",
                [$staffRoleId, $application['user_id']]
            );

            $staff = Database::fetch("SELECT staff_id FROM staff WHERE user_id = ? LIMIT 1", [$application['user_id']]);
            if (!$staff) {
                $employeeId = 'GW' . date('ymd') . str_pad((string)$application['user_id'], 4, '0', STR_PAD_LEFT);
                Database::execute(
                    "INSERT INTO staff (user_id, employee_id, position, phone, hire_date, status, created_at)
                     VALUES (?, ?, ?, ?, CURDATE(), 'active', NOW())",
                    [$application['user_id'], $employeeId, $application['position'], $application['phone']]
                );
            } else {
                Database::execute(
                    "UPDATE staff
                     SET position = ?, phone = COALESCE(NULLIF(?, ''), phone), updated_at = NOW()
                     WHERE staff_id = ?",
                    [$application['position'], $application['phone'], $staff['staff_id']]
                );
            }

            Database::execute(
                "UPDATE recruitment_applications
                 SET status = 'approved', admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE application_id = ?",
                [$note, $currentUserId, $applicationId]
            );

            logActivity($currentUserId, 'recruitment_approve', "Approved recruitment application #{$applicationId}");
            Database::commit();
            api_json(true, ['message' => 'Approved']);
        }

        if ($action === 'reject') {
            Database::execute(
                "UPDATE recruitment_applications
                 SET status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE application_id = ?",
                [$note, $currentUserId, $applicationId]
            );
            logActivity($currentUserId, 'recruitment_reject', "Rejected recruitment application #{$applicationId}");
            Database::commit();
            api_json(true, ['message' => 'Rejected']);
        }

        Database::rollback();
        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        if (Database::getConnection()->inTransaction()) {
            Database::rollback();
        }
        error_log('Admin recruitment error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $applications = Database::fetchAll(
        "SELECT ra.*, u.name AS account_name, u.email AS account_email
         FROM recruitment_applications ra
         LEFT JOIN users u ON ra.user_id = u.user_id
         ORDER BY FIELD(ra.status, 'pending', 'approved', 'rejected'), ra.created_at DESC"
    );

    api_json(true, ['applications' => $applications]);
} catch (Exception $e) {
    error_log('Admin recruitment list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load applications.'], 500);
}
?>
