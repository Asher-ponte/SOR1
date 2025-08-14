<?php
ob_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
session_start();
include 'db_config.php';
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Not authorized');
}

function handleQueryError($conn, $query, $error) {
    return [
        'error' => $error,
        'query' => $query,
        'mysql_error' => $conn->error
    ];
}

// Remove all filter logic and always use the full range of data
$start_date = '1970-01-01';
$end_date = date('Y-m-d');
$dept_condition = '';

try {
    // Total BBS Observations
    $query = "SELECT COUNT(*) as cnt FROM bbs_checklists c 
              WHERE date_of_observation BETWEEN ? AND ? {$dept_condition}";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare total observations query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    // Safe/Unsafe counts
    $query = "SELECT 
                SUM(CASE WHEN a.value = 'safe' THEN 1 ELSE 0 END) as safe_cnt,
                SUM(CASE WHEN a.value = 'unsafe' THEN 1 ELSE 0 END) as unsafe_cnt
              FROM bbs_checklist_answers a
              JOIN bbs_checklists c ON a.checklist_id = c.id
              WHERE c.date_of_observation BETWEEN ? AND ? {$dept_condition}";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare safe/unsafe counts query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $safe = $result['safe_cnt'] ?? 0;
    $unsafe = $result['unsafe_cnt'] ?? 0;
    $stmt->close();

    // Calculate percentages safely
    $total_observations = $safe + $unsafe;
    $safe_pct = $total_observations > 0 ? round(($safe / $total_observations) * 100, 1) : 0;
    $unsafe_pct = $total_observations > 0 ? round(($unsafe / $total_observations) * 100, 1) : 0;

    $response = [
        'kpi' => [
            'total_reports' => $total,
            'safe_pct' => $safe_pct,
            'unsafe_pct' => $unsafe_pct
        ],
        'safe_emps' => [],
        'unsafe_emps' => [],
        'violated_items' => [],
        'unsafe_locations' => [],
        'unsafe_emp_items' => [],
        'daily_target' => $daily_target
    ];

    // Top 5 most frequent unsafe items
    $query = "SELECT i.label, COUNT(*) as cnt 
              FROM bbs_checklist_answers a 
              JOIN bbs_observation_items i ON a.item_id = i.id
              JOIN bbs_checklists c ON a.checklist_id = c.id
              WHERE a.value = 'unsafe' 
              AND c.date_of_observation BETWEEN ? AND ? {$dept_condition}
              GROUP BY a.item_id 
              ORDER BY cnt DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare unsafe items query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $violated_items = ['labels' => [], 'counts' => []];
    while ($row = $result->fetch_assoc()) {
        $violated_items['labels'][] = $row['label'];
        $violated_items['counts'][] = $row['cnt'];
    }
    $stmt->close();

    // Top 5 employees with most unsafe observations
    $query = "SELECT e.name, COUNT(*) as cnt 
              FROM bbs_checklists c 
              JOIN bbs_checklist_answers a ON c.id = a.checklist_id 
              JOIN employees e ON c.employee_id = e.id
              WHERE a.value = 'unsafe' 
              AND c.date_of_observation BETWEEN ? AND ? {$dept_condition}
              GROUP BY c.employee_id 
              ORDER BY cnt DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare unsafe employees query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $unsafe_emps = ['labels' => [], 'counts' => []];
    $top_employees = [];
    while ($row = $result->fetch_assoc()) {
        $unsafe_emps['labels'][] = $row['name'];
        $unsafe_emps['counts'][] = $row['cnt'];
        $top_employees[] = $row['name'];
    }
    $stmt->close();

    // Get violation items for each top employee (for note)
    $unsafe_emp_items = [];
    foreach ($top_employees as $employee) {
        $query = "SELECT i.label, COUNT(*) as cnt 
                  FROM bbs_checklist_answers a 
                  JOIN bbs_observation_items i ON a.item_id = i.id
                  JOIN bbs_checklists c ON a.checklist_id = c.id
                  JOIN employees e ON c.employee_id = e.id
                  WHERE a.value = 'unsafe' 
                  AND e.name = ?
                  AND c.date_of_observation BETWEEN ? AND ? {$dept_condition}
                  GROUP BY i.label 
                  ORDER BY cnt DESC 
                  LIMIT 5";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare employee violation items query for note")));
        }
        $stmt->bind_param('sss', $employee, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            // For each item, fetch the occurrences (date and time)
            $item_label = $row['label'];
            $occ_query = "SELECT c.date_of_observation, d.name AS department
                          FROM bbs_checklist_answers a
                          JOIN bbs_checklists c ON a.checklist_id = c.id
                          JOIN employees e ON c.employee_id = e.id
                          JOIN bbs_observation_items i ON a.item_id = i.id
                          JOIN departments d ON c.department_id = d.id
                          WHERE a.value = 'unsafe'
                          AND e.name = ?
                          AND i.label = ?
                          AND c.date_of_observation BETWEEN ? AND ? {$dept_condition}
                          ORDER BY c.date_of_observation";
            $occ_stmt = $conn->prepare($occ_query);
            if (!$occ_stmt) {
                throw new Exception(json_encode(handleQueryError($conn, $occ_query, "Failed to prepare occurrences query for note")));
            }
            $occ_stmt->bind_param('ssss', $employee, $item_label, $start_date, $end_date);
            $occ_stmt->execute();
            $occ_result = $occ_stmt->get_result();
            $occurrences = [];
            while ($occ = $occ_result->fetch_assoc()) {
                $occurrences[] = [
                    'date' => $occ['date_of_observation'],
                    'department' => $occ['department']
                ];
            }
            $occ_stmt->close();
            $items[] = ['label' => $item_label, 'count' => $row['cnt'], 'occurrences' => $occurrences];
        }
        $unsafe_emp_items[$employee] = $items;
        $stmt->close();
    }

    // Top 5 employees with highest safe rate
    $query = "SELECT 
                e.name, 
                COUNT(*) as total_observations,
                SUM(CASE WHEN a.value = 'safe' THEN 1 ELSE 0 END) as safe_cnt,
                (SUM(CASE WHEN a.value = 'safe' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as safe_rate
              FROM bbs_checklists c 
              JOIN bbs_checklist_answers a ON c.id = a.checklist_id 
              JOIN employees e ON c.employee_id = e.id
              WHERE c.date_of_observation BETWEEN ? AND ? {$dept_condition}
              GROUP BY c.employee_id, e.name
              HAVING total_observations > 0
              ORDER BY safe_rate DESC, total_observations DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare safe employees query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $safe_emps = ['labels' => [], 'counts' => [], 'rates' => []];
    while ($row = $result->fetch_assoc()) {
        $safe_emps['labels'][] = $row['name'];
        $safe_emps['counts'][] = $row['safe_cnt'];
        $safe_emps['rates'][] = round($row['safe_rate'], 1);
    }
    $stmt->close();

    // Top 5 Unsafe Locations
    $query = "SELECT 
                d.name AS department,
                COUNT(*) as cnt
              FROM bbs_checklist_answers a
              JOIN bbs_checklists c ON a.checklist_id = c.id
              JOIN departments d ON c.department_id = d.id
              WHERE a.value = 'unsafe'
              AND c.date_of_observation BETWEEN ? AND ? {$dept_condition}
              GROUP BY d.id
              ORDER BY cnt DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare unsafe locations query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $unsafe_locations = ['labels' => [], 'counts' => []];
    while ($row = $result->fetch_assoc()) {
        $unsafe_locations['labels'][] = $row['department'];
        $unsafe_locations['counts'][] = $row['cnt'];
    }
    $stmt->close();

    $response['safe_emps'] = $safe_emps;
    $response['unsafe_emps'] = $unsafe_emps;
    $response['violated_items'] = $violated_items;
    $response['unsafe_emp_items'] = $unsafe_emp_items;
    $response['unsafe_locations'] = $unsafe_locations;

    // Set proper JSON header
    header('Content-Type: application/json');
    
    // Return JSON response
    echo json_encode($response, JSON_THROW_ON_ERROR);

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to load dashboard data: ' . $e->getMessage()
    ], JSON_THROW_ON_ERROR);
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}
ob_end_flush();
?> 