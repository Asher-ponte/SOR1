<?php
// api.php
// Handles all API requests from the frontend (both main app and admin dashboard)

// 1. Error Reporting and Output Buffering:
// Prevent PHP errors from being displayed directly in the output, which can break JSON responses.
// Errors will still be logged to the server's error log.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// 2. Session Configuration:
// Set session cookie parameters before starting the session
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookie
ini_set('session.use_only_cookies', 1); // Forces sessions to only use cookies
ini_set('session.cookie_samesite', 'Strict'); // Protects against CSRF
ini_set('session.gc_maxlifetime', 3600); // Session timeout after 1 hour
ini_set('session.cookie_lifetime', 3600); // Cookie lifetime 1 hour

// Start output buffering to capture any unintended output before sending JSON.
ob_start();

include 'db_config.php'; // Include the database connection file

// Start session to manage user login state
// Ensure this is at the very top before any output
session_start();

// Set CORS headers to allow same-origin requests
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json'); // Set response header to JSON

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Define the upload directory
$uploadDir = 'uploads/';

// Create the uploads directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Create recursively and set permissions
}

// --- DEBUGGING: Log incoming request data ---
error_log("--- API Request Start ---");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET parameters: " . print_r($_GET, true));
error_log("POST parameters: " . print_r($_POST, true));
error_log("Files: " . print_r($_FILES, true));
// --- END DEBUGGING ---

// Get the requested action from GET or POST parameters
// Prioritize POST, then GET. This is crucial for FormData.
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- DEBUGGING: Log determined action ---
error_log("Determined action: " . ($action ?: 'NULL/Empty'));
// --- END DEBUGGING ---

// Output buffering and global error handler for JSON API
ob_start();
set_exception_handler(function($e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
});

// Function to validate session status
function validateSession() {
    // Check if session exists and is valid
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        return false;
    }

    // Check if session has expired (optional additional check)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        session_unset();
        session_destroy();
        return false;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

// Handle session check action
function checkSession() {
    $isValid = validateSession();
    
    if ($isValid) {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'loggedin' => true,
            'username' => $_SESSION['username'] ?? null,
            'message' => 'Session is valid'
        ]);
    } else {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'loggedin' => false,
            'message' => 'Session is invalid or expired'
        ]);
    }
}

// Use a switch statement to route actions to specific functions
switch ($action) {
    case 'login':
        loginUser($conn);
        break;
    case 'register':
        registerUser($conn);
        break;
    case 'logout':
        logoutUser();
        break;
    case 'check_session':
        checkSession();
        break;
    case 'save_observation':
        saveObservation($conn, $uploadDir);
        break;
    case 'get_observation':
        getObservation($conn);
        break;
    case 'get_observations':
        getObservations($conn);
        break;
    case 'update_observation':
        updateObservation($conn);
        break;
    case 'delete_observation':
        deleteObservation($conn);
        break;
    case 'get_dashboard_data':
        getDashboardData($conn);
        break;
    case 'save_comment':
        saveComment($conn);
        break;
    case 'get_comments':
        getComments($conn);
        break;
    case 'update_observation_status':
        updateObservationStatus($conn, $uploadDir);
        break;
    case 'delete_bbs_checklist':
        deleteBbsChecklist($conn);
        break;
    case 'get_sor_compliance_tracker':
        getSORComplianceTracker($conn);
        break;
    case 'edit_user':
        editUser($conn);
        break;
    case 'delete_user':
        deleteUser($conn);
        break;
    case 'add_user':
        addUser($conn);
        break;
    case 'delete_observer':
        if (!isset($_POST['observer'])) {
            echo json_encode(['success' => false, 'message' => 'Observer is required']);
            exit;
        }
        $observer = $_POST['observer'];
        
        // Start transaction
        $conn->begin_transaction();
        try {
            // Delete observer's checklist answers first
            $stmt = $conn->prepare("DELETE a FROM bbs_checklist_answers a 
                                  JOIN bbs_checklists c ON a.checklist_id = c.id 
                                  WHERE c.observer = ?");
            $stmt->bind_param('s', $observer);
            $stmt->execute();
            $stmt->close();

            // Then delete observer's checklists
            $stmt = $conn->prepare("DELETE FROM bbs_checklists WHERE observer = ?");
            $stmt->bind_param('s', $observer);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'edit_observer':
        if (!isset($_POST['observer']) || !isset($_POST['new_observer'])) {
            echo json_encode(['success' => false, 'message' => 'Observer and new observer name are required']);
            exit;
        }
        $observer = $_POST['observer'];
        $new_observer = $_POST['new_observer'];
        
        // Check if new observer name already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bbs_checklists WHERE observer = ? AND observer != ?");
        $stmt->bind_param('ss', $new_observer, $observer);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Observer name already exists']);
            exit;
        }
        $stmt->close();

        // Update observer name
        $stmt = $conn->prepare("UPDATE bbs_checklists SET observer = ? WHERE observer = ?");
        $stmt->bind_param('ss', $new_observer, $observer);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update observer']);
        }
        $stmt->close();
        break;

    case 'add_observer':
        if (!isset($_POST['observer'])) {
            echo json_encode(['success' => false, 'message' => 'Observer name is required']);
            exit;
        }
        $observer = $_POST['observer'];
        
        // Check if observer name already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bbs_checklists WHERE observer = ?");
        $stmt->bind_param('s', $observer);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Observer name already exists']);
            exit;
        }
        $stmt->close();

        // Since observers are only stored in bbs_checklists, we'll consider them added
        // when they submit their first checklist. For now, just return success.
        echo json_encode(['success' => true]);
        break;

    case 'get_filter_options':
        $locations = [];
        $categories = [];
        $assignees = [];
        $descriptions = [];
        $generated_by = [];
        $result = $conn->query("SELECT DISTINCT location FROM observations WHERE location IS NOT NULL AND location != ''");
        while ($row = $result->fetch_assoc()) $locations[] = $row['location'];
        $result = $conn->query("SELECT DISTINCT category FROM observations WHERE category IS NOT NULL AND category != ''");
        while ($row = $result->fetch_assoc()) $categories[] = $row['category'];
        $result = $conn->query("SELECT DISTINCT assign_to FROM observations WHERE assign_to IS NOT NULL AND assign_to != ''");
        while ($row = $result->fetch_assoc()) $assignees[] = $row['assign_to'];
        $result = $conn->query("SELECT DISTINCT description FROM observations WHERE description IS NOT NULL AND description != ''");
        while ($row = $result->fetch_assoc()) $descriptions[] = $row['description'];
        $result = $conn->query("SELECT DISTINCT generated_by FROM observations WHERE generated_by IS NOT NULL AND generated_by != ''");
        while ($row = $result->fetch_assoc()) $generated_by[] = $row['generated_by'];
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'locations' => $locations,
            'categories' => $categories,
            'assignees' => $assignees,
            'descriptions' => $descriptions,
            'generated_by' => $generated_by
        ]);
        exit;

    case 'get_sor_daily_report':
        getSORDailyReport($conn);
        break;
    case 'get_sor_weekly_by_department':
        getSORWeeklyByDepartment($conn);
        break;
    case 'get_sor_weekly_trend_current_month':
        getSORWeeklyTrendCurrentMonth($conn);
        break;
    case 'get_top7_open_by_assignee':
        getTop7OpenByAssignee($conn);
        break;

    case 'get_bbs_compliance_tracker':
        getBBSComplianceTracker($conn);
        break;

    case 'update_user_daily_target':
        updateUserDailyTarget($conn);
        break;

    case 'sor_submission_trend':
        getSORSubmissionTrend($conn);
        break;
    case 'bbs_submission_trend':
        getBBSSubmissionTrend($conn);
        break;

    case 'get_sor_users':
        getSORUsers($conn);
        break;
    case 'get_bbs_users':
        getBBSUsers($conn);
        break;

    case 'sor_weekly_trend':
        getSORWeeklyTrend($conn);
        break;

    case 'bbs_weekly_trend':
        getBBSWeeklyTrend($conn);
        break;

    case 'get_days_open_distribution':
        getDaysOpenDistribution($conn);
        break;

    case 'get_top5_observation_descriptions':
        getTop5ObservationDescriptions($conn);
        break;

    case 'get_monthly_observation_trend':
        getMonthlyObservationTrend($conn);
        break;

    case 'get_all_users':
        getAllUsers($conn);
        break;

    case 'update_user_department':
        updateUserDepartment($conn);
        break;

    case 'add_department':
        addDepartment($conn);
        break;

    case 'edit_department':
        editDepartment($conn);
        break;

    case 'get_location_status':
        getLocationStatus($conn);
        break;

    case 'get_top5_users':
        getTop5Users($conn);
        break;

    case 'delete_department':
        deleteDepartment($conn);
        break;

    case 'add_bbs_observer':
        if (!isset($_POST['observer'])) {
            echo json_encode(['success' => false, 'message' => 'Observer name is required']);
            exit;
        }
        $observer = $_POST['observer'];
        // Check if user exists in users table
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE username = ?");
        $stmt->bind_param('s', $observer);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($result['cnt'] == 0) {
            echo json_encode(['success' => false, 'message' => 'User does not exist']);
            exit;
        }
        // Check if already an observer in bbs_checklists
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bbs_checklists WHERE observer = ?");
        $stmt->bind_param('s', $observer);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($result['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Observer already exists in BBS KPI']);
            exit;
        }
        // Insert a dummy checklist for this observer
        $dummy_date = date('Y-m-d');
        $dummy_department_id = 1; // Use a valid department_id
        $dummy_employee_id = 1;   // Use a valid employee_id
        $stmt = $conn->prepare("INSERT INTO bbs_checklists (observer, department_id, employee_id, date_of_observation, ppe, body_mechanics, tools) VALUES (?, ?, ?, ?, '', '', '')");
        $stmt->bind_param('siis', $observer, $dummy_department_id, $dummy_employee_id, $dummy_date);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Observer added to BBS KPI.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add observer.']);
        }
        $stmt->close();
        exit;

    default:
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// --- Helper function for photo uploads ---
/**
 * Handles photo upload for both initial inspection and corrective action photos
 * @param string $fileInputName The form field name ('initial_image' or 'corrective_image')
 * @param string $uploadDir Directory where photos will be stored
 * @return string|null Returns the relative path to the saved file or null if upload fails
 */
function uploadImage($fileInputName, $uploadDir) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName = basename($_FILES[$fileInputName]['name']);
        $fileSize = $_FILES[$fileInputName]['size'];
        $fileType = $_FILES[$fileInputName]['type'];

        // Sanitize filename and make it unique
        $fileName = preg_replace("/[^a-zA-Z0-9.\-_]/", "", $fileName);
        $newFileName = uniqid() . '_' . $fileName;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            return $destPath;
        } else {
            error_log("Error moving uploaded file from $fileTmpPath to $destPath");
            return null;
        }
    } else if (isset($_FILES[$fileInputName])) {
        error_log("File upload error for $fileInputName: " . $_FILES[$fileInputName]['error']);
    }
    return null;
}


// --- API Action Functions ---

// Handles user registration
function registerUser($conn) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Username and password are required for registration.']);
        return;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in.']);
    } else {
        if ($conn->errno == 1062) {
             ob_end_clean();
             echo json_encode(['success' => false, 'message' => 'Registration failed. Username already exists.']);
        } else {
            error_log("Error during user registration: " . $stmt->error);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
        }
    }
    $stmt->close();
}

// Handles user login
function loginUser($conn) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Username and password are required for login.']);
        return;
    }

    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];

            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Login successful!', 'username' => $user['username']]);
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
        }
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
    $stmt->close();
}

// Handles saving a new safety observation report
/**
 * Saves a new observation with initial inspection photo
 * Photo handling:
 * - Accepts 'initial_image' in form data
 * - Stores photo path in 'initial_image_data_url'
 * - Stores original filename in 'initial_image_filename'
 */
function saveObservation($conn, $uploadDir) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to submit observations.']);
        return;
    }

    $generated_by = $_SESSION['username'] ?? 'Anonymous';
    $timestamp = $_POST['timestamp'] ?? '';
    $category = $_POST['category'] ?? '';
    $observation_type = $_POST['observation_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $corrective_actions = $_POST['corrective_actions'] ?? '';
    $preventive_actions = $_POST['preventive_actions'] ?? '';
    $location = $_POST['location'] ?? '';
    $assign_to = $_POST['assign_to'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $status = $_POST['status'] ?? 'Open';

    // Handle initial image upload
    $initial_image_path = uploadImage('initial_image', $uploadDir);
    $initial_image_filename = $_FILES['initial_image']['name'] ?? null; // Get original filename if uploaded

    if (empty($timestamp) || empty($category) || empty($description)) {
         ob_end_clean();
         echo json_encode(['success' => false, 'message' => 'Timestamp, Category, and Description are required fields.']);
         return;
    }

    $stmt = $conn->prepare("INSERT INTO observations (timestamp, initial_image_data_url, initial_image_filename, category, observation_type, description, corrective_actions, preventive_actions, location, assign_to, due_date, status, generated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssssssssssss",
        $timestamp,
        $initial_image_path, // Store the path
        $initial_image_filename,
        $category,
        $observation_type,
        $description,
        $corrective_actions,
        $preventive_actions,
        $location,
        $assign_to,
        $due_date,
        $status,
        $generated_by
    );

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Observation saved successfully.']);
    } else {
        error_log("Error saving observation: " . $stmt->error . " | SQLSTATE: " . $stmt->sqlstate);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to save observation. Please try again.']);
    }
    $stmt->close();
}

// Handles fetching all observations (used for the report table and potentially admin view)
/**
 * Retrieves observations with both initial and corrective photos
 * Returns:
 * - initial_image_data_url: Path to the "Before" photo
 * - initial_image_filename: Original filename of "Before" photo
 * - corrective_photo_data_url: Path to the "After" photo
 * - corrective_photo_filename: Original filename of "After" photo
 */
function getObservations($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view observations.']);
        return;
    }

    // Initialize filter and pagination variables
    $category = $_POST['category'] ?? $_GET['category'] ?? '';
    $observation_type = $_POST['observation_type'] ?? $_GET['observation_type'] ?? '';
    $location = $_POST['location'] ?? $_GET['location'] ?? '';
    $status = $_POST['status'] ?? $_GET['status'] ?? '';
    $start_date = $_POST['start_date'] ?? $_GET['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? $_GET['end_date'] ?? '';
    $assign_to = $_POST['assign_to'] ?? $_GET['assign_to'] ?? '';
    $generated_by = $_POST['generated_by'] ?? $_GET['generated_by'] ?? '';
    
    // Pagination parameters (use frontend values if provided)
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : (isset($_POST['limit']) ? intval($_POST['limit']) : 16);
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : (isset($_POST['offset']) ? intval($_POST['offset']) : 0);
    $page = ($limit > 0) ? (floor($offset / $limit) + 1) : 1;

    // Build the WHERE clause for filters
    $where_clause = "WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($category)) {
        $where_clause .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    if (!empty($observation_type)) {
        $where_clause .= " AND observation_type = ?";
        $params[] = $observation_type;
        $types .= "s";
    }
    if (!empty($location)) {
        $where_clause .= " AND location = ?";
        $params[] = $location;
        $types .= "s";
    }
    if (!empty($status)) {
        $where_clause .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    if (!empty($assign_to)) {
        $where_clause .= " AND assign_to = ?";
        $params[] = $assign_to;
        $types .= "s";
    }
    if (!empty($generated_by)) {
        $where_clause .= " AND generated_by = ?";
        $params[] = $generated_by;
        $types .= "s";
    }
    if (!empty($start_date)) {
        $where_clause .= " AND timestamp >= ?";
        $params[] = $start_date . " 00:00:00";
        $types .= "s";
    }
    if (!empty($end_date)) {
        $where_clause .= " AND timestamp <= ?";
        $params[] = $end_date . " 23:59:59";
        $types .= "s";
    }

    // Get summary statistics
    $summary = [];
    
    // Total observations
    $total_sql = "SELECT COUNT(*) as total FROM observations " . $where_clause;
    $total_stmt = $conn->prepare($total_sql);
    if (!empty($params)) {
        $total_stmt->bind_param($types, ...$params);
    }
    $total_stmt->execute();
    $summary['total'] = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $total_stmt->close();

    // Open observations
    $open_sql = "SELECT COUNT(*) as open FROM observations " . $where_clause . " AND status = 'Open'";
    $open_stmt = $conn->prepare($open_sql);
    if (!empty($params)) {
        $open_stmt->bind_param($types, ...$params);
    }
    $open_stmt->execute();
    $summary['open'] = $open_stmt->get_result()->fetch_assoc()['open'] ?? 0;
    $open_stmt->close();

    // Closed observations
    $closed_sql = "SELECT COUNT(*) as closed FROM observations " . $where_clause . " AND status = 'Closed'";
    $closed_stmt = $conn->prepare($closed_sql);
    if (!empty($params)) {
        $closed_stmt->bind_param($types, ...$params);
    }
    $closed_stmt->execute();
    $summary['closed'] = $closed_stmt->get_result()->fetch_assoc()['closed'] ?? 0;
    $closed_stmt->close();

    // Calculate closure rate
    $summary['closure_rate'] = ($summary['total'] > 0) ? round(($summary['closed'] / $summary['total']) * 100, 2) : 0;

    // Add back unsafe acts and conditions counts
    $unsafe_acts_sql = "SELECT COUNT(*) as unsafe_acts FROM observations " . $where_clause . " AND observation_type = 'Unsafe Act'";
    $unsafe_acts_stmt = $conn->prepare($unsafe_acts_sql);
    if (!empty($params)) {
        $unsafe_acts_stmt->bind_param($types, ...$params);
    }
    $unsafe_acts_stmt->execute();
    $summary['unsafe_acts'] = $unsafe_acts_stmt->get_result()->fetch_assoc()['unsafe_acts'] ?? 0;
    $unsafe_acts_stmt->close();

    // Unsafe Conditions count
    $unsafe_conditions_sql = "SELECT COUNT(*) as unsafe_conditions FROM observations " . $where_clause . " AND observation_type = 'Unsafe Condition'";
    $unsafe_conditions_stmt = $conn->prepare($unsafe_conditions_sql);
    if (!empty($params)) {
        $unsafe_conditions_stmt->bind_param($types, ...$params);
    }
    $unsafe_conditions_stmt->execute();
    $summary['unsafe_conditions'] = $unsafe_conditions_stmt->get_result()->fetch_assoc()['unsafe_conditions'] ?? 0;
    $unsafe_conditions_stmt->close();

    // Get paginated observations
    $sql = "SELECT id, timestamp, initial_image_data_url, initial_image_filename, category, observation_type, 
            description, corrective_actions, preventive_actions, location, assign_to, due_date, status, 
            closed_date, generated_by, corrective_photo_data_url, corrective_photo_filename,
            CASE 
                WHEN status = 'Closed' AND closed_date IS NOT NULL 
                THEN DATEDIFF(closed_date, timestamp)
                ELSE DATEDIFF(CURDATE(), DATE(timestamp))
            END as days_open
            FROM observations " . $where_clause . " 
            ORDER BY timestamp DESC 
            LIMIT ? OFFSET ?";

    // Add limit and offset to params
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $observations = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                // Only get comment count for visible observations
                $row['comment_count'] = (int)($conn->query("SELECT COUNT(*) FROM comments WHERE observation_id = " . (int)$row['id'])->fetch_row()[0]);
                $observations[] = $row;
            }
        }
        $stmt->close();

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'observations' => $observations,
            'pagination' => [
                'total' => $summary['total'],
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($summary['total'] / $limit)
            ]
        ]);
    } else {
        error_log("Error preparing statement for getObservations: " . $conn->error . " | SQL: " . $sql);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error preparing statement. Error: ' . $conn->error]);
    }
}

// Handles updating the status of an observation and adding a corrective photo
/**
 * Updates an observation with corrective action photo
 * Photo handling:
 * - Accepts 'corrective_image' in form data
 * - Stores photo path in 'corrective_photo_data_url'
 * - Stores original filename in 'corrective_photo_filename'
 */
function updateObservationStatus($conn, $uploadDir) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to update observations.']);
        return;
    }

    // --- DEBUGGING: Log POST data specifically for updateObservationStatus ---
    error_log("Inside updateObservationStatus function. Received POST data: " . print_r($_POST, true));
    error_log("Received FILES data: " . print_r($_FILES, true));
    // --- END DEBUGGING ---

    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';

    // Handle corrective image upload
    $corrective_photo_path = uploadImage('corrective_image', $uploadDir);
    $corrective_photo_filename = $_FILES['corrective_image']['name'] ?? null; // Get original filename if uploaded

    if (empty($id) || empty($status)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Observation ID and status are required for update.']);
        return;
    }

    // Set closed_date if status is being changed to 'Closed'
    $closed_date = null;
    if ($status === 'Closed') {
        $closed_date = date('Y-m-d H:i:s');
    }

    $stmt = $conn->prepare("UPDATE observations SET status = ?, corrective_photo_data_url = ?, corrective_photo_filename = ?, closed_date = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $status, $corrective_photo_path, $corrective_photo_filename, $closed_date, $id);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Observation updated successfully.']);
    } else {
        error_log("Error updating observation status: " . $stmt->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to update observation. Please try again.']);
    }
    $stmt->close();
}

// Handles updating an observation from the admin dashboard (full edit)
function updateObservation($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to update observations.']);
        return;
    }

    $id = $_POST['id'] ?? '';
    $timestamp = $_POST['timestamp'] ?? '';
    $category = $_POST['category'] ?? '';
    $observation_type = $_POST['observation_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $corrective_actions = $_POST['corrective_actions'] ?? '';
    $preventive_actions = $_POST['preventive_actions'] ?? '';
    $location = $_POST['location'] ?? '';
    $assign_to = $_POST['assign_to'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $status = $_POST['status'] ?? 'Open';

    // Fetch current observation for old image paths
    $stmt = $conn->prepare("SELECT initial_image_data_url, corrective_photo_data_url FROM observations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();

    // Handle new image uploads
    global $uploadDir;
    $initial_image_path = $current['initial_image_data_url'];
    $corrective_photo_path = $current['corrective_photo_data_url'];
    if (isset($_FILES['initial_image']) && $_FILES['initial_image']['error'] === UPLOAD_ERR_OK) {
        if ($initial_image_path && file_exists($initial_image_path)) unlink($initial_image_path);
        $initial_image_path = uploadImage('initial_image', $uploadDir);
    }
    if (isset($_FILES['corrective_image']) && $_FILES['corrective_image']['error'] === UPLOAD_ERR_OK) {
        if ($corrective_photo_path && file_exists($corrective_photo_path)) unlink($corrective_photo_path);
        $corrective_photo_path = uploadImage('corrective_image', $uploadDir);
    }

    // Set closed_date if status is being changed to 'Closed'
    $closed_date = null;
    if ($status === 'Closed') {
        $closed_date = date('Y-m-d H:i:s');
    }

    $sql = "UPDATE observations SET timestamp = ?, category = ?, observation_type = ?, description = ?, corrective_actions = ?, preventive_actions = ?, location = ?, assign_to = ?, due_date = ?, status = ?, closed_date = ?, initial_image_data_url = ?, corrective_photo_data_url = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssssssi",
        $timestamp,
        $category,
        $observation_type,
        $description,
        $corrective_actions,
        $preventive_actions,
        $location,
        $assign_to,
        $due_date,
        $status,
        $closed_date,
        $initial_image_path,
        $corrective_photo_path,
        $id
    );
    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Observation updated successfully.', 'initial_image_data_url' => $initial_image_path, 'corrective_photo_data_url' => $corrective_photo_path]);
    } else {
        error_log("Error updating observation: " . $stmt->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to update observation.']);
    }
    $stmt->close();
}

// Handles deleting an observation from the admin dashboard
/**
 * Deletes an observation and its associated photos
 * - Removes both initial and corrective photos from the uploads directory
 * - Deletes the database record
 */
function deleteObservation($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to delete observations.']);
        return;
    }

    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Observation ID is required for deletion.']);
        return;
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        // Get file paths before deletion
        $stmt = $conn->prepare("SELECT initial_image_data_url, corrective_photo_data_url FROM observations WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement for getting file paths");
        }
        
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement for getting file paths");
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        // Delete associated comments first (due to foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM comments WHERE observation_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement for deleting comments");
        }
        
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete associated comments");
        }
        $stmt->close();

        // Delete the observation record
        $stmt = $conn->prepare("DELETE FROM observations WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement for deleting observation");
        }
        
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete observation record");
        }
        $stmt->close();

        // Delete associated files if they exist
        if ($row) {
            if ($row['initial_image_data_url'] && file_exists($row['initial_image_data_url'])) {
                unlink($row['initial_image_data_url']);
            }
            if ($row['corrective_photo_data_url'] && file_exists($row['corrective_photo_data_url'])) {
                unlink($row['corrective_photo_data_url']);
            }
        }

        // Commit transaction
        $conn->commit();

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Observation deleted successfully.']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error deleting observation: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to delete observation: ' . $e->getMessage()]);
    }
}


function getDashboardData($conn) {
     if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
         ob_end_clean();
         echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view the dashboard.']);
         return;
     }

     $department_id = $_GET['department_id'] ?? 'all';
     $where_clause = '';
     $params = [];
     $types = '';

     if ($department_id !== 'all') {
        $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $department_name = $result->fetch_assoc()['name'];
            $where_clause = ' WHERE location = ?';
            $params[] = $department_name;
            $types .= 's';
        }
     }

     $data = [];

     // 1. Total observations count
     $total_sql = "SELECT COUNT(*) as total FROM observations" . $where_clause;
     $total_stmt = $conn->prepare($total_sql);
     if (!empty($params)) {
        $total_stmt->bind_param($types, ...$params);
     }
     $total_stmt->execute();
     $data['total'] = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;
     $total_stmt->close();

     // 2. Open observations count
     $open_sql = "SELECT COUNT(*) as open FROM observations" . $where_clause . ($where_clause ? ' AND' : ' WHERE') . " status = 'Open'";
     $open_stmt = $conn->prepare($open_sql);
     if (!empty($params)) {
        $open_stmt->bind_param($types, ...$params);
     }
     $open_stmt->execute();
     $data['open'] = $open_stmt->get_result()->fetch_assoc()['open'] ?? 0;
     $open_stmt->close();

     // 3. Closed observations count
     $closed_sql = "SELECT COUNT(*) as closed FROM observations" . $where_clause . ($where_clause ? ' AND' : ' WHERE') . " status = 'Closed'";
     $closed_stmt = $conn->prepare($closed_sql);
     if (!empty($params)) {
        $closed_stmt->bind_param($types, ...$params);
     }
     $closed_stmt->execute();
     $data['closed'] = $closed_stmt->get_result()->fetch_assoc()['closed'] ?? 0;
     $closed_stmt->close();

     // Calculate Total Closure Rate
     $data['total_closure_rate'] = ($data['total'] > 0) ? round(($data['closed'] / $data['total']) * 100, 2) : 0;

     // Calculate On-time Closure Rate
     $ontime_closed_sql = "SELECT COUNT(*) as ontime_closed FROM observations" . $where_clause . ($where_clause ? ' AND' : ' WHERE') . " status = 'Closed' AND DATE(timestamp) <= due_date";
     $ontime_closed_stmt = $conn->prepare($ontime_closed_sql);
     if (!empty($params)) {
        $ontime_closed_stmt->bind_param($types, ...$params);
     }
     $ontime_closed_stmt->execute();
     $ontime_closed_count = $ontime_closed_stmt->get_result()->fetch_assoc()['ontime_closed'] ?? 0;
     $data['ontime_closure_rate'] = ($data['closed'] > 0) ? round(($ontime_closed_count / $data['closed']) * 100, 2) : 0;


     // 4. Observations by Category
     $category_sql = "SELECT category, COUNT(*) as count FROM observations" . $where_clause . " GROUP BY category ORDER BY count DESC";
     $category_stmt = $conn->prepare($category_sql);
     if (!empty($params)) {
        $category_stmt->bind_param($types, ...$params);
     }
     $category_stmt->execute();
     $category_query = $category_stmt->get_result();
     $data['categories'] = [];
     while($row = $category_query->fetch_assoc()) {
         $data['categories'][] = $row;
     }
     $category_stmt->close();

     // 5. Observations by Location (NEW)
     $location_sql = "SELECT location, COUNT(*) as count FROM observations" . $where_clause . " GROUP BY location ORDER BY count DESC";
     $location_stmt = $conn->prepare($location_sql);
     if (!empty($params)) {
        $location_stmt->bind_param($types, ...$params);
     }
     $location_stmt->execute();
     $location_query = $location_stmt->get_result();
     $data['locations'] = [];
     while($row = $location_query->fetch_assoc()) {
         $data['locations'][] = $row;
     }
     $location_stmt->close();

     // 5b. Open/Closed per Location (for chart)
     $locations_status_sql = "SELECT location, SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open, SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed FROM observations" . $where_clause . " GROUP BY location ORDER BY location";
     $locations_status_stmt = $conn->prepare($locations_status_sql);
     if (!empty($params)) {
        $locations_status_stmt->bind_param($types, ...$params);
     }
     $locations_status_stmt->execute();
     $locations_status_query = $locations_status_stmt->get_result();
     $data['locations_status'] = [];
     while($row = $locations_status_query->fetch_assoc()) {
         $data['locations_status'][] = $row;
     }
     $locations_status_stmt->close();

     // 6. Observations by User (generated_by) (NEW)
     $username_sql = "SELECT generated_by, COUNT(*) as count FROM observations" . $where_clause . " GROUP BY generated_by ORDER BY count DESC";
     $username_stmt = $conn->prepare($username_sql);
     if (!empty($params)) {
        $username_stmt->bind_param($types, ...$params);
     }
     $username_stmt->execute();
     $username_query = $username_stmt->get_result();
     $data['usernames'] = [];
     while($row = $username_query->fetch_assoc()) {
         $data['usernames'][] = $row;
     }
     $username_stmt->close();

     // 7. Observations by Type (Unsafe Act vs Unsafe Condition) (NEW)
     $observation_type_sql = "SELECT observation_type, COUNT(*) as count FROM observations" . $where_clause . " GROUP BY observation_type ORDER BY count DESC";
     $observation_type_stmt = $conn->prepare($observation_type_sql);
     if (!empty($params)) {
        $observation_type_stmt->bind_param($types, ...$params);
     }
     $observation_type_stmt->execute();
     $observation_type_query = $observation_type_stmt->get_result();
     $data['observation_types'] = [];
     while($row = $observation_type_query->fetch_assoc()) {
         $data['observation_types'][] = $row;
     }
     $observation_type_stmt->close();

     ob_end_clean();
     echo json_encode(['success' => true, 'data' => $data]);
}

// Handles user logout
function logoutUser() {
    // Clear all session data
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

// Handles saving a new comment
function saveComment($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to comment.']);
        return;
    }
    $observation_id = $_POST['observation_id'] ?? '';
    $comment_text = trim($_POST['comment_text'] ?? '');
    $user = $_SESSION['username'] ?? 'Anonymous';
    if (empty($observation_id) || empty($comment_text)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Observation ID and comment text are required.']);
        return;
    }
    $stmt = $conn->prepare("INSERT INTO comments (observation_id, user, comment_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $observation_id, $user, $comment_text);
    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Comment posted.']);
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to post comment.']);
    }
    $stmt->close();
}

// Handles fetching comments for an observation
function getComments($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view comments.']);
        return;
    }
    $observation_id = $_POST['observation_id'] ?? '';
    if (empty($observation_id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Observation ID is required.']);
        return;
    }
    $stmt = $conn->prepare("SELECT user, comment_text, created_at FROM comments WHERE observation_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $observation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    $stmt->close();
    ob_end_clean();
    echo json_encode(['success' => true, 'comments' => $comments]);
}

// Add this function before the switch statement
function getObservation($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view observation details.']);
        return;
    }

    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Observation ID is required.']);
        return;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM observations WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }
        
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement");
        }
        
        $result = $stmt->get_result();
        $observation = $result->fetch_assoc();
        $stmt->close();

        if (!$observation) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Observation not found.']);
            return;
        }

        ob_end_clean();
        echo json_encode(['success' => true, 'observation' => $observation]);
    } catch (Exception $e) {
        error_log("Error getting observation: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to get observation details: ' . $e->getMessage()]);
    }
}

// Add this function after the switch
function deleteBbsChecklist($conn) {
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to delete checklists.']);
        return;
    }
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Checklist ID is required.']);
        return;
    }
    $id = intval($id);
    // Delete answers first (due to FK constraint)
    $conn->query("DELETE FROM bbs_checklist_answers WHERE checklist_id = $id");
    $conn->query("DELETE FROM bbs_checklists WHERE id = $id");
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Checklist deleted.']);
}

// Add SOR Compliance Tracker API
function getSORComplianceTracker($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view SOR Compliance.']);
        return;
    }
    $week_start = $_GET['week_start'] ?? $_POST['week_start'] ?? null;
    if (!$week_start) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing week_start parameter.']);
        return;
    }
    
    $daily_target = 3; // Make configurable in future
    // Validate and ensure week_start is a Monday
    $start = new DateTime($week_start);
    $day_of_week = $start->format('N'); // 1 (Monday) through 7 (Sunday)
    
    if ($day_of_week !== '1') {
        // Adjust to Monday
        $days_to_subtract = $day_of_week - 1;
        $start->modify("-{$days_to_subtract} days");
        $week_start = $start->format('Y-m-d');
    }
    
    // Generate 6 days: Monday to Saturday
    $days = [];
    for ($i = 0; $i < 6; $i++) {
        $d = clone $start;
        $d->modify("+{$i} days");
        $days[] = $d->format('Y-m-d');
    }
    
    // Get all users
    $users = [];
    $user_result = $conn->query("SELECT id, username FROM users ORDER BY username ASC");
    while ($row = $user_result->fetch_assoc()) {
        $users[] = $row;
    }
    
    // For each user, for each day, count observations
    $compliance = [];
    $total_hits = 0;
    foreach ($users as $user) {
        // Get user's daily target from database, default to 3 if not set
        $stmt = $conn->prepare("SELECT daily_target FROM user_daily_targets WHERE username = ? AND target_type = 'sor'");
        $stmt->bind_param("s", $user['username']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_daily_target = $result->fetch_assoc()['daily_target'] ?? 3;
        $stmt->close();
        
        $hits = 0;
        $user_days = [];
        foreach ($days as $day) {
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM observations WHERE generated_by = ? AND DATE(timestamp) = ?");
            $stmt->bind_param("ss", $user['username'], $day);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $hit = $cnt >= $user_daily_target;
            if ($hit) $hits++;
            $user_days[] = [ 'date' => $day, 'count' => (int)$cnt, 'hit' => $hit ];
            $stmt->close();
        }
        $compliance_percent = round(($hits / 6) * 100);
        $compliance[$user['username']] = [ 'days' => $user_days, 'compliance' => $compliance_percent, 'daily_target' => $user_daily_target ];
        $total_hits += $hits;
    }
    $leadership_compliance = count($users) > 0 ? round(($total_hits / (count($users)*6)) * 100) : 0;
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'users' => $users,
        'compliance' => $compliance,
        'leadership_compliance' => $leadership_compliance,
        'week_start' => $week_start,
        'days' => $days,
        'daily_target' => $daily_target
    ]);
}

// Edit user
function editUser($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }
    $id = $_POST['id'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $department_id = $_POST['department_id'] ?? null;

    if (!$id || !$username) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing user id or username.']);
        return;
    }

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, department_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $username, $hashed_password, $department_id, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ?, department_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $username, $department_id, $id);
    }

    $ok = $stmt->execute();
    $stmt->close();
    ob_end_clean();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'User updated.' : 'Failed to update user.']);
}

// Delete user
function deleteUser($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }
    $id = $_POST['id'] ?? '';
    if (!$id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing user id.']);
        return;
    }
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    ob_end_clean();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'User deleted.' : 'Failed to delete user.']);
}

// Add user
function addUser($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '123456';
    $department_id = $_POST['department_id'] ?? null;

    if (!$username) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Username is required.']);
        return;
    }
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, department_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $username, $hashed_password, $department_id);
    $ok = $stmt->execute();
    $stmt->close();
    ob_end_clean();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'User added.' : 'Failed to add user. Username may already exist.']);
}

// --- SOR Daily Report (last 7 days) ---
function getSORDailyReport($conn) {
    try {
        $days = [];
        $counts = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = (new DateTime("-$i days"))->format('Y-m-d');
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM observations WHERE DATE(timestamp) = ?");
            $stmt->bind_param('s', $date);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $stmt->close();
            $days[] = $date;
            $counts[] = (int)$cnt;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'labels' => $days, 'data' => $counts]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- SOR Weekly by Department (last 8 weeks) ---
function getSORWeeklyByDepartment($conn) {
    try {
        // Get all locations (as departments)
        $departments = [];
        $result = $conn->query("SELECT DISTINCT location FROM observations WHERE location IS NOT NULL AND location != '' ORDER BY location");
        while ($row = $result->fetch_assoc()) $departments[] = $row['location'];
        // Get current week's Monday and Sunday
        $monday = (new DateTime('monday this week'))->format('Y-m-d');
        $sunday = (new DateTime('sunday this week'))->format('Y-m-d');
        // Build data: location => count for current week
        $data = [];
        foreach ($departments as $dept) {
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM observations WHERE location = ? AND DATE(timestamp) BETWEEN ? AND ?");
            $stmt->bind_param('sss', $dept, $monday, $sunday);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $stmt->close();
            $data[$dept] = (int)$cnt;
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'departments' => $departments,
            'week_start' => $monday,
            'week_end' => $sunday,
            'data' => $data
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

// Add new function for SOR Weekly Trend Current Month
function getSORWeeklyTrendCurrentMonth($conn) {
    try {
        $now = new DateTime();
        $firstDay = new DateTime($now->format('Y-m-01'));
        $lastDay = new DateTime($now->format('Y-m-t'));
        // Find the first Monday on or before the 1st
        $firstMonday = clone $firstDay;
        if ($firstMonday->format('N') !== '1') {
            $firstMonday->modify('last monday');
        }
        // Find the last Sunday on or after the last day
        $lastSunday = clone $lastDay;
        if ($lastSunday->format('N') !== '7') {
            $lastSunday->modify('next sunday');
        }
        $weeks = [];
        $counts = [];
        $weekStart = clone $firstMonday;
        while ($weekStart <= $lastSunday) {
            $weekEnd = (clone $weekStart)->modify('+6 days');
            $label = $weekStart->format('M d') . ' - ' . $weekEnd->format('M d');
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM observations WHERE DATE(timestamp) BETWEEN ? AND ?");
            $startStr = $weekStart->format('Y-m-d');
            $endStr = $weekEnd->format('Y-m-d');
            $stmt->bind_param('ss', $startStr, $endStr);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $stmt->close();
            $weeks[] = $label;
            $counts[] = (int)$cnt;
            $weekStart->modify('+7 days');
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'labels' => $weeks, 'data' => $counts]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

// Add new function for get_top7_open_by_assignee
function getTop7OpenByAssignee($conn) {
    try {
        $department_id = $_GET['department_id'] ?? 'all';
        $where_clause = "WHERE status = 'Open' AND assign_to IS NOT NULL AND assign_to != ''";
        $params = [];
        $types = '';

        if ($department_id !== 'all') {
            $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
            if (!$stmt) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Database error.']);
                return;
            }
            $stmt->bind_param("i", $department_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $department_name = $result->fetch_assoc()['name'];
                $where_clause .= ' AND location = ?';
                $params[] = $department_name;
                $types .= 's';
            }
            $stmt->close();
        }

        $sql = "SELECT assign_to, COUNT(*) as cnt FROM observations " . $where_clause . " GROUP BY assign_to ORDER BY cnt DESC LIMIT 10";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            return;
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $labels = [];
        $counts = [];
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['assign_to'];
            $counts[] = (int) $row['cnt'];
        }
        $stmt->close();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'labels' => $labels, 'data' => $counts]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

// Add BBS Compliance Tracker API
function getBBSComplianceTracker($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view BBS Compliance.']);
        return;
    }
    
    $week_start = $_GET['week_start'] ?? $_POST['week_start'] ?? null;
    $department = $_GET['department'] ?? $_POST['department'] ?? 'all';
    
    if (!$week_start) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing week_start parameter.']);
        return;
    }
    
    $daily_target = 3; // Make configurable in future
    
    // Validate and ensure week_start is a Monday
    $start = new DateTime($week_start);
    $day_of_week = $start->format('N'); // 1 (Monday) through 7 (Sunday)
    
    if ($day_of_week !== '1') {
        // Adjust to Monday
        $days_to_subtract = $day_of_week - 1;
        $start->modify("-{$days_to_subtract} days");
        $week_start = $start->format('Y-m-d');
    }
    
    // Generate 6 days: Monday to Saturday
    $days = [];
    for ($i = 0; $i < 6; $i++) {
        $d = clone $start;
        $d->modify("+{$i} days");
        $days[] = $d->format('Y-m-d');
    }
    
    // Department condition
    $dept_condition = $department !== 'all' ? "AND c.department_id = " . intval($department) : "";
    
    // Get all observers
    $users = [];
    $user_query = "SELECT DISTINCT observer as username FROM bbs_checklists WHERE 1=1 {$dept_condition} ORDER BY observer ASC";
    $user_result = $conn->query($user_query);
    while ($row = $user_result->fetch_assoc()) {
        $users[] = $row;
    }
    
    // For each observer, for each day, count submissions
    $compliance = [];
    $total_hits = 0;
    $total_observers = 0;
    
    foreach ($users as $user) {
        // Get user's daily target from database, default to 3 if not set
        $stmt = $conn->prepare("SELECT daily_target FROM user_daily_targets WHERE username = ? AND target_type = 'bbs'");
        $stmt->bind_param("s", $user['username']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_daily_target = $result->fetch_assoc()['daily_target'] ?? 3;
        $stmt->close();
        
        $hits = 0;
        $user_days = [];
        
        foreach ($days as $day) {
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bbs_checklists WHERE observer = ? AND DATE(date_of_observation) = ? {$dept_condition}");
            $stmt->bind_param("ss", $user['username'], $day);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $hit = $cnt >= $user_daily_target;
            if ($hit) $hits++;
            $user_days[] = [ 'date' => $day, 'count' => (int)$cnt, 'hit' => $hit ];
            $stmt->close();
        }
        
        $compliance_percent = round(($hits / 6) * 100);
        $compliance[$user['username']] = [ 'days' => $user_days, 'compliance' => $compliance_percent, 'daily_target' => $user_daily_target ];
        
        if ($compliance_percent > 0) {
            $total_hits += $hits;
            $total_observers++;
        }
    }
    
    $observer_compliance = $total_observers > 0 ? round(($total_hits / ($total_observers * 6)) * 100) : 0;
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'users' => $users,
        'compliance' => $compliance,
        'observer_compliance' => $observer_compliance,
        'week_start' => $week_start,
        'days' => $days,
        'daily_target' => $daily_target
    ]);
}

// Update user daily target
function updateUserDailyTarget($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to update user targets.']);
        return;
    }
    
    $username = $_POST['username'] ?? '';
    $type = $_POST['type'] ?? ''; // 'sor' or 'bbs'
    $daily_target = $_POST['daily_target'] ?? '';
    
    if (!$username || !$type || !$daily_target) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required parameters: username, type, or daily_target.']);
        return;
    }
    
    // Validate daily target
    $daily_target = intval($daily_target);
    if ($daily_target < 1 || $daily_target > 10) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Daily target must be between 1 and 10.']);
        return;
    }
    
    // Validate type
    if (!in_array($type, ['sor', 'bbs'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid type. Must be "sor" or "bbs".']);
        return;
    }
    
    try {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both insert and update
        $stmt = $conn->prepare("INSERT INTO user_daily_targets (username, target_type, daily_target) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE daily_target = ?");
        $stmt->bind_param("ssii", $username, $type, $daily_target, $daily_target);
        
        if ($stmt->execute()) {
            ob_end_clean();
            echo json_encode([
                'success' => true, 
                'message' => 'Daily target updated successfully.',
                'username' => $username,
                'type' => $type,
                'daily_target' => $daily_target
            ]);
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to update daily target: ' . $stmt->error]);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// SOR Submission Trend (per month for current year)
function getSORSubmissionTrend($conn) {
    $username = $_GET['username'] ?? $_POST['username'] ?? '';
    $year = date('Y');
    $labels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    $data = array_fill(0, 12, 0);
    if ($username) {
        $stmt = $conn->prepare("SELECT MONTH(timestamp) as m, COUNT(*) as cnt FROM observations WHERE generated_by = ? AND YEAR(timestamp) = ? GROUP BY m");
        $stmt->bind_param("si", $username, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $m = (int)$row['m'];
            $data[$m-1] = (int)$row['cnt'];
        }
        $stmt->close();
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
}

// BBS Submission Trend (per month for current year)
function getBBSSubmissionTrend($conn) {
    $username = $_GET['username'] ?? $_POST['username'] ?? '';
    $year = date('Y');
    $labels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    $data = array_fill(0, 12, 0);
    if ($username) {
        $stmt = $conn->prepare("SELECT MONTH(date_of_observation) as m, COUNT(*) as cnt FROM bbs_checklists WHERE observer = ? AND YEAR(date_of_observation) = ? GROUP BY m");
        $stmt->bind_param("si", $username, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $m = (int)$row['m'];
            $data[$m-1] = (int)$row['cnt'];
        }
        $stmt->close();
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
}

// Get all SOR users
function getSORUsers($conn) {
    $users = [];
    $result = $conn->query("SELECT username FROM users ORDER BY username ASC");
    while ($row = $result->fetch_assoc()) {
        $users[] = $row['username'];
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'users' => $users]);
}

// Get all BBS users (observers)
function getBBSUsers($conn) {
    $users = [];
    $result = $conn->query("SELECT DISTINCT observer as username FROM bbs_checklists ORDER BY observer ASC");
    while ($row = $result->fetch_assoc()) {
        $users[] = $row['username'];
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'users' => $users]);
}

// --- SOR Weekly Trend for a User for a Given Month ---
function getSORWeeklyTrend($conn) {
    $username = $_GET['username'] ?? $_POST['username'] ?? '';
    $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($_POST['year']) ? intval($_POST['year']) : intval(date('Y')));
    $month = isset($_GET['month']) ? intval($_GET['month']) : (isset($_POST['month']) ? intval($_POST['month']) : intval(date('n')));
    if (!$username) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing username parameter.']);
        return;
    }
    try {
        // Get the first and last day of the month
        $firstDay = new DateTime("$year-$month-01");
        $lastDay = new DateTime($firstDay->format('Y-m-t'));
        // Find the first Monday on or before the 1st
        $firstMonday = clone $firstDay;
        if ($firstMonday->format('N') !== '1') {
            $firstMonday->modify('last monday');
        }
        // Find the last Sunday on or after the last day
        $lastSunday = clone $lastDay;
        if ($lastSunday->format('N') !== '7') {
            $lastSunday->modify('next sunday');
        }
        $weeks = [];
        $counts = [];
        $weekStart = clone $firstMonday;
        while ($weekStart <= $lastSunday) {
            $weekEnd = (clone $weekStart)->modify('+6 days');
            $label = $weekStart->format('M d') . ' - ' . $weekEnd->format('M d');
            $startStr = $weekStart->format('Y-m-d');
            $endStr = $weekEnd->format('Y-m-d');
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM observations WHERE generated_by = ? AND DATE(timestamp) BETWEEN ? AND ?");
            $stmt->bind_param('sss', $username, $startStr, $endStr);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $stmt->close();
            $weeks[] = $label;
            $counts[] = (int)$cnt;
            $weekStart->modify('+7 days');
        }
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'labels' => $weeks, 'data' => $counts]);
    } catch (Exception $e) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

// --- BBS Weekly Trend for a User for a Given Month ---
function getBBSWeeklyTrend($conn) {
    $username = $_GET['username'] ?? $_POST['username'] ?? '';
    $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($_POST['year']) ? intval($_POST['year']) : intval(date('Y')));
    $month = isset($_GET['month']) ? intval($_GET['month']) : (isset($_POST['month']) ? intval($_POST['month']) : intval(date('n')));
    if (!$username) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing username parameter.']);
        return;
    }
    try {
        // Get the first and last day of the month
        $firstDay = new DateTime("$year-$month-01");
        $lastDay = new DateTime($firstDay->format('Y-m-t'));
        // Find the first Monday on or before the 1st
        $firstMonday = clone $firstDay;
        if ($firstMonday->format('N') !== '1') {
            $firstMonday->modify('last monday');
        }
        // Find the last Sunday on or after the last day
        $lastSunday = clone $lastDay;
        if ($lastSunday->format('N') !== '7') {
            $lastSunday->modify('next sunday');
        }
        $weeks = [];
        $counts = [];
        $weekStart = clone $firstMonday;
        while ($weekStart <= $lastSunday) {
            $weekEnd = (clone $weekStart)->modify('+6 days');
            $label = $weekStart->format('M d') . ' - ' . $weekEnd->format('M d');
            $startStr = $weekStart->format('Y-m-d');
            $endStr = $weekEnd->format('Y-m-d');
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bbs_checklists WHERE observer = ? AND DATE(date_of_observation) BETWEEN ? AND ?");
            $stmt->bind_param('sss', $username, $startStr, $endStr);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $stmt->close();
            $weeks[] = $label;
            $counts[] = (int)$cnt;
            $weekStart->modify('+7 days');
        }
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'labels' => $weeks, 'data' => $counts]);
    } catch (Exception $e) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

// --- Days Open Distribution for Observations ---
function getDaysOpenDistribution($conn) {
    $bins = [
        ['label' => '0-7 days', 'min' => 0, 'max' => 7],
        ['label' => '8-14 days', 'min' => 8, 'max' => 14],
        ['label' => '15-30 days', 'min' => 15, 'max' => 30],
        ['label' => '31-40 days', 'min' => 31, 'max' => 40],
        ['label' => '41-50 days', 'min' => 41, 'max' => 50],
        ['label' => '51-60 days', 'min' => 51, 'max' => 60],
        ['label' => '61+ days', 'min' => 61, 'max' => 10000]
    ];
    $counts = array_fill(0, count($bins), 0);
    $sql = "SELECT timestamp, DATEDIFF(CURDATE(), DATE(timestamp)) as days_open FROM observations WHERE status = 'Open'";
    $result = $conn->query($sql);
    if (!$result) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $conn->error]);
        return;
    }
    while ($row = $result->fetch_assoc()) {
        $days = (int)$row['days_open'];
        foreach ($bins as $i => $bin) {
            if ($days >= $bin['min'] && $days <= $bin['max']) {
                $counts[$i]++;
                break;
            }
        }
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'labels' => array_column($bins, 'label'),
        'data' => $counts
    ]);
}

// --- Top 5 Observation Descriptions ---
function getTop5ObservationDescriptions($conn) {
    $department_id = $_GET['department_id'] ?? 'all';
    $where_clause = '';
    $params = [];
    $types = '';

    if ($department_id !== 'all') {
        $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
        if (!$stmt) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            return;
        }
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $department_name = $result->fetch_assoc()['name'];
            $where_clause = ' WHERE location = ?';
            $params[] = $department_name;
            $types .= 's';
        }
        $stmt->close();
    }

    $sql = "SELECT description, COUNT(*) as cnt FROM observations" . $where_clause . " GROUP BY description ORDER BY cnt DESC LIMIT 5";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        return;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        return;
    }

    $result = $stmt->get_result();
    $labels = [];
    $counts = [];
    while ($row = $result->fetch_assoc()) {
        $desc = $row['description'] ?? '';
        if (mb_strlen($desc) > 45) {
            $desc = mb_substr($desc, 0, 42) . '...';
        }
        $labels[] = $desc;
        $counts[] = (int)$row['cnt'];
    }
    $stmt->close();

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'data' => $counts
    ]);
}

// --- Monthly Observation Trend (All Observations, Jan-Dec) ---
function getMonthlyObservationTrend($conn) {
    $year = date('Y');
    $labels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    $data = array_fill(0, 12, 0);
    $stmt = $conn->prepare("SELECT MONTH(timestamp) as m, COUNT(*) as cnt FROM observations WHERE YEAR(timestamp) = ? GROUP BY m");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $m = (int)$row['m'];
        $data[$m-1] = (int)$row['cnt'];
    }
    $stmt->close();
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
}

// Close the database connection when the script finishes
$conn->close();

// Add this function at the end of the file
function getAllUsers($conn) {
    $users = [];
    $sql = "SELECT `id`, `username`, `department_id` FROM `users` ORDER BY `username` ASC";
    $result = $conn->query($sql);

    if ($result === false) {
        error_log("SQL Error in getAllUsers: " . $conn->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error while fetching users.']);
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'users' => $users]);
}

function updateUserDepartment($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }

    $user_id = $_POST['user_id'] ?? '';
    $department_id = $_POST['department_id'] ?? '';

    if (empty($user_id) || empty($department_id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'User ID and Department ID are required.']);
        return;
    }

    $stmt = $conn->prepare("UPDATE users SET department_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $department_id, $user_id);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'User department updated successfully.']);
    } else {
        error_log("Error updating user department: " . $stmt->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to update user department.']);
    }
    $stmt->close();
}

function addDepartment($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }

    $name = $_POST['name'] ?? '';

    if (empty($name)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Department name is required.']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
    $stmt->bind_param("s", $name);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Department added successfully.']);
    } else {
        error_log("Error adding department: " . $stmt->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to add department.']);
    }
    $stmt->close();
}

function editDepartment($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }

    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';

    if (empty($id) || empty($name)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Department ID and name are required.']);
        return;
    }

    $stmt = $conn->prepare("UPDATE departments SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $id);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Department updated successfully.']);
    } else {
        error_log("Error updating department: " . $stmt->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to update department.']);
    }
    $stmt->close();
}

function deleteDepartment($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }

    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Department ID is required.']);
        return;
    }

    // Before deleting the department, we need to handle users that are assigned to this department.
    // One option is to set their department_id to NULL.
    $stmt = $conn->prepare("UPDATE users SET department_id = NULL WHERE department_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Department deleted successfully.']);
    } else {
        error_log("Error deleting department: " . $stmt->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to delete department.']);
    }
    $stmt->close();
}

function getTop5Users($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }

    $department_id = $_GET['department_id'] ?? 'all';
    $where_clause = '';
    $params = [];
    $types = '';

    if ($department_id !== 'all') {
        $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            return;
        }
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $department_name = $result->fetch_assoc()['name'];
            $where_clause = ' WHERE location = ?';
            $params[] = $department_name;
            $types .= 's';
        }
        $stmt->close();
    }

    $sql = "SELECT generated_by, COUNT(*) as count FROM observations" . $where_clause . " GROUP BY generated_by ORDER BY count DESC LIMIT 5";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        return;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        return;
    }

    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();

    ob_end_clean();
    echo json_encode(['success' => true, 'data' => $data]);
}

function getLocationStatus($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }

    $department_id = $_GET['department_id'] ?? 'all';
    $where_clause = '';
    $params = [];
    $types = '';

    if ($department_id !== 'all') {
        $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $department_name = $result->fetch_assoc()['name'];
            $where_clause = ' WHERE location = ?';
            $params[] = $department_name;
            $types .= 's';
        }
    }

    $sql = "SELECT location, SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open, SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed FROM observations" . $where_clause . " GROUP BY location ORDER BY location";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();

    ob_end_clean();
    echo json_encode(['success' => true, 'data' => $data]);
}
?>
