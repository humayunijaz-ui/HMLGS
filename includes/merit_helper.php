<?php
/**
 * Merit list / ranking helpers.
 *
 * IMPORTANT: HMLGS does not calculate a new merit score.
 * The existing `percentage` supplied on the application is used for ranking only.
 *
 * Tie-breaking rule (approved default): when percentages are equal,
 * the application submitted earlier (lower id / created_at) ranks higher.
 * This can be revisited once an official tie-break rule is approved.
 */

/**
 * Build the base "eligible applications" query with optional filters.
 * Returns [sql, params] - caller appends ORDER BY / rank logic.
 */
function build_eligible_query($sessionId, $filters = []) {
    $sql = "SELECT a.*, d.department_name, p.program_name
            FROM hostel_applications a
            JOIN eligibility_results er ON er.application_id = a.id AND er.is_eligible = 1
            JOIN departments d ON d.id = a.department_id
            JOIN programs p ON p.id = a.program_id
            WHERE a.hostel_session_id = ?";
    $params = [$sessionId];

    if (!empty($filters['gender'])) {
        $sql .= " AND a.gender = ?";
        $params[] = $filters['gender'];
    }
    if (!empty($filters['department_id'])) {
        $sql .= " AND a.department_id = ?";
        $params[] = $filters['department_id'];
    }
    if (!empty($filters['program_id'])) {
        $sql .= " AND a.program_id = ?";
        $params[] = $filters['program_id'];
    }

    $sql .= " ORDER BY a.percentage DESC, a.created_at ASC, a.id ASC";

    return [$sql, $params];
}

/**
 * Generate a merit list (General / Gender / Department / Program),
 * persist it, and return the merit_list id + ranked rows.
 */
function generate_merit_list(PDO $pdo, $sessionId, $listType, $filters = [], $generatedBy = null) {
    [$sql, $params] = build_eligible_query($sessionId, $filters);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO merit_lists (hostel_session_id, list_type, gender, department_id, program_id, generated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $sessionId,
            $listType,
            $filters['gender'] ?? null,
            $filters['department_id'] ?? null,
            $filters['program_id'] ?? null,
            $generatedBy,
        ]);
        $meritListId = $pdo->lastInsertId();

        $rank = 1;
        $entryStmt = $pdo->prepare(
            "INSERT INTO merit_list_entries (merit_list_id, application_id, rank_no, percentage) VALUES (?, ?, ?, ?)"
        );
        $statusStmt = $pdo->prepare(
            "UPDATE hostel_applications SET status = 'General Merit' WHERE id = ? AND status IN ('Eligible','General Merit')"
        );
        foreach ($rows as $row) {
            $entryStmt->execute([$meritListId, $row['id'], $rank, $row['percentage']]);
            $statusStmt->execute([$row['id']]);
            $rank++;
        }

        $pdo->commit();
        return ['merit_list_id' => $meritListId, 'rows' => $rows];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Fetch a previously generated merit list with its ranked entries.
 */
function get_merit_list_entries(PDO $pdo, $meritListId) {
    $stmt = $pdo->prepare(
        "SELECT mle.rank_no, a.*, d.department_name, p.program_name
         FROM merit_list_entries mle
         JOIN hostel_applications a ON a.id = mle.application_id
         JOIN departments d ON d.id = a.department_id
         JOIN programs p ON p.id = a.program_id
         WHERE mle.merit_list_id = ?
         ORDER BY mle.rank_no ASC"
    );
    $stmt->execute([$meritListId]);
    return $stmt->fetchAll();
}
