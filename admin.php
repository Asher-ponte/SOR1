<?php
session_start();

// Enhanced session validation
function validateAdminSession() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: index.html');
        exit;
    }

    // Check session expiration
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        session_unset();
        session_destroy();
        header('Location: index.html');
        exit;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
}

// Validate session before proceeding
validateAdminSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Safety Observations</title>
    
    <!-- Core Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
        
        .sidebar-icon {
            transition: all 0.2s;
            padding: 0.5rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .sidebar-icon:hover, .sidebar-icon.active {
            background: linear-gradient(90deg, #e0f2fe 0%, #bae6fd 100%);
            color: #0ea5e9;
            transform: scale(1.12);
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.10);
        }
        
        .sidebar-icon svg {
            width: 1.5rem;
            height: 1.5rem;
            stroke-width: 2.2;
        }
        
        .card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            height: 100%;
            min-height: 300px;
            position: relative;
            overflow: hidden;
        }
        
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        
        .stats-card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .input-field {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            outline: none;
            transition: all 0.2s;
        }
        
        .input-field:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        
        .btn-primary {
            background-color: #0ea5e9;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background-color: #0284c7;
        }
        
        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .btn-secondary:hover {
            background-color: #d1d5db;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-height: 600px; /* Show up to 10 rows before scrolling */
            overflow-y: auto;
        }

        /* Add smooth scrolling */
        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #666;
        }

        .sidebar-avatar {
            box-shadow: 0 4px 16px rgba(14, 165, 233, 0.15);
        }
        .sidebar-avatar:hover {
            box-shadow: 0 8px 24px rgba(14, 165, 233, 0.25);
        }

        aside:hover {
            width: 6rem;
            transition: width 0.2s;
        }

        .sidebar-label {
            opacity: 0;
            margin-left: 0.5rem;
            transition: opacity 0.2s;
            white-space: nowrap;
            font-size: 1rem;
            color: #0ea5e9;
            font-weight: 500;
        }
        .sidebar-icon:hover .sidebar-label, .sidebar-icon.active .sidebar-label {
            opacity: 1;
        }

        /* Sidebar nav hover scroll effect */
        .sidebar-nav-scrollable {
            flex: 1 1 auto;
            overflow-y: auto;
            min-height: 0;
            width: 100%;
            position: relative;
        }
        .sidebar-nav-scrollable::before,
        .sidebar-nav-scrollable::after {
            content: '';
            display: block;
            position: absolute;
            left: 0; right: 0;
            height: 24px;
            pointer-events: none;
            z-index: 2;
        }
        .sidebar-nav-scrollable::before {
            top: 0;
            background: linear-gradient(to bottom, rgba(255,255,255,0.8), rgba(255,255,255,0));
        }
        .sidebar-nav-scrollable::after {
            bottom: 0;
            background: linear-gradient(to top, rgba(255,255,255,0.8), rgba(255,255,255,0));
        }
        .dashboard-chart {
            background: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 16px rgba(30, 64, 175, 0.10), 0 1.5px 6px rgba(0,0,0,0.08);
            border: 1.5px solid #e5e7eb;
            padding: 1.25rem;
            min-height: 18rem;
            height: 100%;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Top Navbar -->
    <nav class="w-full bg-white shadow-lg flex items-center justify-between px-8 py-3 space-x-4">
        <div class="flex items-center space-x-4">
            <div class="sidebar-avatar group relative w-12 h-12 flex items-center justify-center rounded-full bg-gradient-to-br from-cyan-500 to-blue-500 shadow-lg border-4 border-white transition-all duration-200 hover:scale-105 hover:shadow-2xl cursor-pointer" title="<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>">
                <?php if (!empty($_SESSION['profile_image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['profile_image_url']); ?>" 
                         alt="Profile" 
                         class="w-full h-full object-cover rounded-full" />
                <?php else: ?>
                    <span class="text-white font-bold text-2xl select-none">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                    </span>
                <?php endif; ?>
                <span class="absolute bottom-1 right-1 w-4 h-4 bg-green-400 border-2 border-white rounded-full shadow"></span>
            </div>
            <a href="admin.php" class="sidebar-icon text-blue-700 hover:text-orange-600" title="Dashboard" aria-label="Dashboard">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <span class="sidebar-label">Home</span>
            </a>
            <div class="relative group">
                <a href="bbs_dashboard.php" class="sidebar-icon text-blue-700 hover:text-orange-600 flex items-center" title="BBS Checklist Dashboard" aria-label="BBS Checklist Dashboard" id="bbs-dashboard-link">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <rect x="3" y="13" width="4" height="8" fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5"/>
                        <rect x="9" y="9" width="4" height="12" fill="#0ea5e9" stroke="#0ea5e9" stroke-width="1.5"/>
                        <rect x="15" y="5" width="4" height="16" fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5"/>
                    </svg>
                    <span class="sidebar-label">BBS Dashboard</span>
                    <svg class="ml-1 w-4 h-4 text-gray-400 group-hover:text-blue-700 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </a>
                <div class="absolute left-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 pointer-events-none group-hover:pointer-events-auto group-focus-within:pointer-events-auto transition-opacity duration-200 z-50" style="display: none;" id="bbs-dropdown">
                    <a href="bbs_dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-t font-semibold">BBS Dashboard</a>
                    <a href="admin_departments.php" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700">Departments</a>
                    <a href="admin_employees.php" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700">Employees</a>
                    <a href="bbs_checklist_report.php" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700">BBS Report</a>
                    <a href="admin_bbs_items.php" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-b">BBS Items</a>
                </div>
            </div>
            <a href="admin_users.php" class="sidebar-icon text-blue-700 hover:text-orange-600" title="User Management" aria-label="User Management">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="sidebar-label">User Management</span>
            </a>
            <a href="sor_report.php" class="sidebar-icon text-blue-700 hover:text-orange-600" title="SOR Report" aria-label="SOR Report">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="sidebar-label">SOR Report</span>
            </a>
            <a href="admin_sor_bbs_kpi.php" class="sidebar-icon text-cyan-600" title="SOR/BBS KPI Tracker">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="#0ea5e9" stroke-width="2" fill="white"/>
                    <path d="M8 12l2.5 2.5L16 9" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="sidebar-label">SOR/BBS KPI</span>
            </a>
        </div>
        <button onclick="goToMainApp()" class="sidebar-icon text-blue-500" title="Main App">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
        </button>
    </nav>

    <!-- Main Content -->
    <div class="min-h-screen bg-gray-50 p-2">
        <!-- Main Content -->
        <main class="flex-1 p-4 overflow-y-auto min-h-screen">
        <!-- Unified Chart & Stats Container: All stats and charts in one glance -->
        <div class="card mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-800">Safety Observations Overview</h3>
                <div class="flex items-center space-x-2">
                    <label for="department-filter" class="text-sm font-medium text-gray-700">Department:</label>
                    <select id="department-filter" class="input-field" onchange="fetchDashboardData(this.value)">
                        <option value="all">All Departments</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
            </div>
            <!-- Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                <div class="stats-card">
                    <h3 class="text-gray-500 text-sm font-medium">Total Observations</h3>
                    <p id="total-observations" class="text-3xl font-bold text-cyan-600">...</p>
                </div>
                <div class="stats-card">
                    <h3 class="text-gray-500 text-sm font-medium">Open Observations</h3>
                    <p id="open-observations" class="text-3xl font-bold text-red-500">...</p>
                </div>
                <div class="stats-card">
                    <h3 class="text-gray-500 text-sm font-medium">Closed Observations</h3>
                    <p id="closed-observations" class="text-3xl font-bold text-green-500">...</p>
                </div>
                <div class="stats-card">
                    <h3 class="text-gray-500 text-sm font-medium">Closure Rate</h3>
                    <p id="total-closure-rate" class="text-3xl font-bold text-purple-600">...</p>
                </div>
                <div class="stats-card">
                    <h3 class="text-gray-500 text-sm font-medium">On-time Rate</h3>
                    <p id="ontime-closure-rate" class="text-3xl font-bold text-indigo-600">...</p>
                </div>
            </div>
            <!-- Charts Grid: 3 per row, 3 rows -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4" style="grid-auto-rows: 1fr;">
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Status Distribution</div>
                    <div class="w-full h-full"><canvas id="statusChart"></canvas></div>
                </div>
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Observation Types</div>
                    <div class="w-full h-full"><canvas id="observationTypeChart"></canvas></div>
                </div>
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Daily SOR</div>
                    <div class="w-full h-full"><canvas id="sorDailyChart"></canvas></div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Categories</div>
                    <div class="w-full h-full"><canvas id="categoryChart"></canvas></div>
                </div>
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Locations</div>
                    <div class="w-full h-full"><canvas id="locationChart"></canvas></div>
                </div>
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Open/Closed per Location</div>
                    <div class="w-full h-full"><canvas id="locationStatusChart"></canvas></div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Weekly SOR Trend (Current Month)</div>
                    <div class="w-full h-full"><canvas id="sorWeeklyTrendChart"></canvas></div>
                </div>
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Weekly SOR by Department</div>
                    <div class="w-full h-full"><canvas id="sorWeeklyDeptChart"></canvas></div>
                </div>
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Top 10 Open Items per Assignee</div>
                    <div class="w-full h-full"><canvas id="top7OpenByAssigneeChart"></canvas></div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Days Open per Observation</div>
                    <div class="w-full h-full"><canvas id="daysOpenChart"></canvas></div>
                </div>
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Top 5 Observation Descriptions</div>
                    <div class="w-full h-full"><canvas id="top5DescriptionChart"></canvas></div>
                </div>
                <div class="dashboard-chart">
                    <div class="font-semibold text-gray-700 mb-2">Monthly Observations (Jan-Dec)</div>
                    <div class="w-full h-full"><canvas id="monthlyObservationChart"></canvas></div>
                </div>
            </div>
        </div>
        </main>
                </div>

    <!-- Modals -->
    <div id="edit-observation-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl">
            <div class="bg-white rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">Edit Observation</h3>
            <form id="edit-observation-form" class="space-y-4">
                    <!-- Form fields will be dynamically populated -->
            </form>
            </div>
        </div>
    </div>

    <div id="confirmation-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
            <div class="bg-white rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">Confirm Deletion</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to delete this observation?</p>
                <div class="flex justify-end space-x-4">
                    <button id="confirm-delete-btn" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">Delete</button>
                    <button onclick="closeConfirmationModal()" class="btn-secondary">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Add these constants at the top of your script
    const API_ENDPOINTS = {
        DASHBOARD_DATA: 'api.php?action=get_dashboard_data',
        LOGOUT: 'api.php?action=logout'
    };

    const CHART_COLORS = {
        primary: ['#0ea5e9', '#f59e0b', '#10b981', '#6366f1', '#ec4899'],
        status: ['#ef4444', '#22c55e'],
        observation: ['#f43f5e', '#0ea5e9']
    };

        // Global variables
        let statusChartInstance = null;
        let categoryChartInstance = null;
    let locationChartInstance = null;
    let observationTypeChartInstance = null;
    let userLineChartInstance = null;
    let locationStatusChartInstance = null;
    let sorDailyChartInstance = null;
    let sorWeeklyDeptChartInstance = null;
    let sorWeeklyTrendChartInstance = null;
    let top7OpenByAssigneeChartInstance = null;
    let daysOpenChartInstance = null;
    let top5DescriptionChartInstance = null;
    let monthlyObservationChartInstance = null;

    // Function to check API connection
    async function checkApiConnection(departmentId = 'all') {
        try {
            const response = await fetch(`${API_ENDPOINTS.DASHBOARD_DATA}&department_id=${departmentId}`, {
                method: 'GET',
                credentials: 'include'
            });
            const result = await response.json();
            return result.success === true;
            } catch (error) {
            console.error('API connection check failed:', error);
            return false;
        }
    }

    // Initialize with session check
    document.addEventListener('DOMContentLoaded', async () => {
        // Check session status first
        const formData = new FormData();
        formData.append('action', 'check_session');
        
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'  // Important for session cookies
            });
            const result = await response.json();

            if (!result.success || !result.loggedin) {
                window.location.href = 'index.html';
                return;
            }

            // If session is valid, proceed with initialization
            await populateDepartmentFilter();
            fetchDashboardData();
        } catch (error) {
            console.error('Error checking session:', error);
            window.location.href = 'index.html';
        }
    });

    async function populateDepartmentFilter() {
        try {
            const response = await fetch('get_departments.php', { credentials: 'include' });
            const departments = await response.json();
            const select = document.getElementById('department-filter');
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error populating department filter:', error);
        }
    }


    // Modify the fetchDashboardData function
    async function fetchDashboardData(departmentId = 'all') {
        const isApiConnected = await checkApiConnection(departmentId);
        
        if (!isApiConnected) {
            showNotification('Using static data - API not connected', 'warning');
            // Use static data
            const staticData = {
                total: 20,
                open: 5,
                closed: 15,
                total_closure_rate: 75,
                ontime_closure_rate: 80,
                categories: [
                    { category: 'Physical', count: 8 },
                    { category: 'Chemical', count: 5 },
                    { category: 'Biological', count: 3 },
                    { category: 'Mechanical', count: 4 }
                ],
                locations: [
                    { location: 'Factory Floor', count: 10 },
                    { location: 'Warehouse', count: 5 },
                    { location: 'Office', count: 3 },
                    { location: 'Laboratory', count: 2 }
                ],
                observation_types: [
                    { observation_type: 'Unsafe Act', count: 12 },
                    { observation_type: 'Unsafe Condition', count: 8 }
                ]
            };
            updateDashboardStats(staticData);
            renderAllCharts(staticData);
            return;
        }

        try {
            const response = await fetch(`${API_ENDPOINTS.DASHBOARD_DATA}&department_id=${departmentId}`, {
                credentials: 'include'  // Important for session cookies
            });
            const result = await response.json();

            if (result.success) {
                updateDashboardStats(result.data);
                renderAllCharts(result.data);
                showNotification('Dashboard data loaded successfully', 'success');
                } else {
                throw new Error(result.message || 'Failed to load dashboard data');
                }
            } catch (error) {
            console.error('Error:', error);
            showNotification('Using static data due to API error', 'warning');
            // Use static data as fallback
            const staticData = {
                total: 20,
                open: 5,
                closed: 15,
                total_closure_rate: 75,
                ontime_closure_rate: 80,
            categories: [
                { category: 'Physical', count: 8 },
                { category: 'Chemical', count: 5 },
                { category: 'Biological', count: 3 },
                { category: 'Mechanical', count: 4 }
            ],
            locations: [
                { location: 'Factory Floor', count: 10 },
                { location: 'Warehouse', count: 5 },
                { location: 'Office', count: 3 },
                { location: 'Laboratory', count: 2 }
            ],
            observation_types: [
                { observation_type: 'Unsafe Act', count: 12 },
                { observation_type: 'Unsafe Condition', count: 8 }
                ]
            };
            updateDashboardStats(staticData);
            renderAllCharts(staticData);
        }
        // Call these after dashboard loads
        fetchAndRenderSORDaily(departmentId);
        fetchAndRenderSORWeeklyDept(departmentId);
        fetchAndRenderSORWeeklyTrend(departmentId);
        fetchAndRenderTop7OpenByAssignee(departmentId);
        fetchAndRenderUserLineChart(departmentId);
        fetchAndRenderLocationStatusChart(departmentId);
        fetchAndRenderDaysOpenChart(departmentId);
        fetchAndRenderTop5DescriptionChart(departmentId);
        fetchAndRenderMonthlyObservationChart(departmentId);
    }

    function updateDashboardStats(data) {
        document.getElementById('total-observations').textContent = data.total;
        document.getElementById('open-observations').textContent = data.open;
        document.getElementById('closed-observations').textContent = data.closed;
        document.getElementById('total-closure-rate').textContent = `${data.total_closure_rate}%`;
        document.getElementById('ontime-closure-rate').textContent = `${data.ontime_closure_rate}%`;
    }

    // Chart Rendering Functions
    function renderAllCharts(data) {
        renderStatusChart(data.open, data.closed);
        renderCategoryChart(data.categories);
        renderLocationChart(data.locations);
        renderObservationTypeChart(data.observation_types);
        renderUserLineChart(data.usernames);
    }

        function renderStatusChart(openCount, closedCount) {
        if (statusChartInstance) statusChartInstance.destroy();

            const statusCanvas = document.getElementById('statusChart');
            if (!statusCanvas) return;
            const ctx = statusCanvas.getContext('2d');
            statusChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Open', 'Closed'],
                    datasets: [{
                        data: [openCount, closedCount],
                        backgroundColor: ['#ef4444', '#22c55e'],
                    borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 0
                },
                    plugins: {
                        legend: {
                        position: 'bottom',
                            labels: {
                            padding: 20,
                            font: { size: 12 }
                            }
                        },
                         tooltip: {
                            callbacks: {
                                label: function(context) {
                                const total = openCount + closedCount;
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                            }
                        }
                    }
                }
            });
        }

    function renderCategoryChart(categories) {
        if (categoryChartInstance) categoryChartInstance.destroy();

            const categoryCanvas = document.getElementById('categoryChart');
            if (!categoryCanvas) return;
            const ctx = categoryCanvas.getContext('2d');
            categoryChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                labels: categories.map(c => c.category),
                    datasets: [{
                    label: 'Observations',
                    data: categories.map(c => c.count),
                    backgroundColor: '#10b981',
                    borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 0
                },
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                        ticks: { precision: 0 }
                        }
                    }
                }
            });
        }

    function renderLocationChart(locations) {
        if (locationChartInstance) locationChartInstance.destroy();

            const locationCanvas = document.getElementById('locationChart');
            if (!locationCanvas) return;
            const ctx = locationCanvas.getContext('2d');
            locationChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                labels: locations.map(l => l.location),
                    datasets: [{
                        label: 'Number of Observations',
                    data: locations.map(l => l.count),
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 0
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                    title: {
                        display: true,
                        text: 'Observations by Location',
                        font: { size: 14 }
                    }
                },
                    scales: {
                        y: {
                            beginAtZero: true,
                        ticks: { precision: 0 }
                        },
                        x: {
                            ticks: {
                            maxRotation: 45,
                            minRotation: 45
                            }
                        }
                    }
                }
            });
        }

    function renderObservationTypeChart(types) {
        if (observationTypeChartInstance) observationTypeChartInstance.destroy();
        // Ensure only two types: Unsafe Condition and Unsafe Act
        const labels = ['Unsafe Condition', 'Unsafe Act'];
        const data = [0, 0];
        types.forEach(t => {
            if (t.observation_type === 'Unsafe Condition') data[0] = t.count;
            if (t.observation_type === 'Unsafe Act') data[1] = t.count;
        });
        const observationTypeCanvas = document.getElementById('observationTypeChart');
        if (!observationTypeCanvas) return;
        const ctx = observationTypeCanvas.getContext('2d');
        observationTypeChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: ['#f43f5e', '#0ea5e9'], // Red, Blue
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 12 } }
                    }
                }
            }
        });
    }

    // Fetch and render Top 5 Users
    async function fetchAndRenderUserLineChart(departmentId = 'all') {
        try {
            const res = await fetch(`api.php?action=get_top5_users&department_id=${departmentId}`, { credentials: 'include' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            renderUserLineChart(data.data);
        } catch (e) {
            console.error('Top 5 Users Chart Error:', e);
        }
    }

    function renderUserLineChart(usernames) {
        if (userLineChartInstance) userLineChartInstance.destroy();
        if (!usernames || usernames.length === 0) return;
        // Ensure canvas exists
        const canvas = document.getElementById('userLineChart');
        if (!canvas) return;
        // Sort and get top 5 users
        const topUsers = [...usernames].sort((a, b) => b.count - a.count).slice(0, 5);
        const ctx = canvas.getContext('2d');
        userLineChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: topUsers.map(u => u.generated_by),
                datasets: [{
                    label: 'Reports Submitted',
                    data: topUsers.map(u => u.count),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#6366f1',
                    pointRadius: 5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 0 },
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    // Fetch and render Location Status Chart
    async function fetchAndRenderLocationStatusChart(departmentId = 'all') {
        try {
            const res = await fetch(`api.php?action=get_location_status&department_id=${departmentId}`, { credentials: 'include' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            renderLocationStatusChart(data.data);
        } catch (e) {
            console.error('Location Status Chart Error:', e);
        }
    }

    function renderLocationStatusChart(locationsStatus) {
        if (locationStatusChartInstance) locationStatusChartInstance.destroy();
        if (!locationsStatus || locationsStatus.length === 0) return;
        // Sort by total (open + closed) descending
        const sorted = [...locationsStatus].sort((a, b) => (parseInt(b.open) + parseInt(b.closed)) - (parseInt(a.open) + parseInt(a.closed)));
        const locationStatusCanvas = document.getElementById('locationStatusChart');
        if (!locationStatusCanvas) return;
        const ctx = locationStatusCanvas.getContext('2d');
        locationStatusChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: sorted.map(l => l.location),
                datasets: [
                    {
                        label: 'Open',
                        data: sorted.map(l => parseInt(l.open)),
                        backgroundColor: '#f59e0b'
                    },
                    {
                        label: 'Closed',
                        data: sorted.map(l => parseInt(l.closed)),
                        backgroundColor: '#22c55e'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 0 },
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        } text-white`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    function goToMainApp() {
        window.location.href = 'index.html';
    }

    // Dropdown logic for BBS Dashboard
    const bbsLink = document.getElementById('bbs-dashboard-link');
    const bbsDropdown = document.getElementById('bbs-dropdown');
    if (bbsLink && bbsDropdown) {
        // Show on hover (desktop)
        bbsLink.parentElement.addEventListener('mouseenter', () => {
            bbsDropdown.style.display = 'block';
            bbsDropdown.style.opacity = '1';
            bbsDropdown.style.pointerEvents = 'auto';
        });
        bbsLink.parentElement.addEventListener('mouseleave', () => {
            bbsDropdown.style.display = 'none';
            bbsDropdown.style.opacity = '0';
            bbsDropdown.style.pointerEvents = 'none';
        });
        // Show on click (mobile)
        bbsLink.addEventListener('click', (e) => {
            e.preventDefault();
            if (bbsDropdown.style.display === 'block') {
                bbsDropdown.style.display = 'none';
                bbsDropdown.style.opacity = '0';
                bbsDropdown.style.pointerEvents = 'none';
            } else {
                bbsDropdown.style.display = 'block';
                bbsDropdown.style.opacity = '1';
                bbsDropdown.style.pointerEvents = 'auto';
            }
        });
    }

    // Fetch and render SOR Daily Report
    async function fetchAndRenderSORDaily(departmentId = 'all') {
        try {
            const res = await fetch(`api.php?action=get_sor_daily_report&department_id=${departmentId}`, { credentials: 'include' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            renderSORDailyChart(data.labels, data.data);
        } catch (e) {
            console.error('SOR Daily Report Error:', e);
        }
    }
    function renderSORDailyChart(labels, counts) {
        if (sorDailyChartInstance) sorDailyChartInstance.destroy();
        const dailyCanvas = document.getElementById('sorDailyChart');
        if (!dailyCanvas) return;
        const ctx = dailyCanvas.getContext('2d');
        sorDailyChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Reports',
                    data: counts,
                    backgroundColor: '#f43f5e',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
    // Fetch and render SOR Weekly by Department
    async function fetchAndRenderSORWeeklyDept(departmentId = 'all') {
        try {
            const res = await fetch(`api.php?action=get_sor_weekly_by_department&department_id=${departmentId}`, { credentials: 'include' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            renderSORWeeklyDeptChart(data.departments, data.data, data.week_start, data.week_end);
        } catch (e) {
            console.error('SOR Weekly by Department Error:', e);
        }
    }
    function renderSORWeeklyDeptChart(departments, deptData, weekStart, weekEnd) {
        if (sorWeeklyDeptChartInstance) sorWeeklyDeptChartInstance.destroy();
        const weeklyDeptCanvas = document.getElementById('sorWeeklyDeptChart');
        if (!weeklyDeptCanvas) return;
        const ctx = weeklyDeptCanvas.getContext('2d');
        // Sort departments by count descending
        const deptCounts = departments.map(dept => ({ dept, count: deptData[dept] || 0 }));
        deptCounts.sort((a, b) => b.count - a.count);
        const sortedDepartments = deptCounts.map(item => item.dept);
        const sortedCounts = deptCounts.map(item => item.count);
        sorWeeklyDeptChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: sortedDepartments,
                datasets: [{
                    label: 'Reports',
                    data: sortedCounts,
                    backgroundColor: '#0ea5e9',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, title: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
    // Fetch and render SOR Weekly Trend for Current Month
    async function fetchAndRenderSORWeeklyTrend(departmentId = 'all') {
        try {
            const res = await fetch(`api.php?action=get_sor_weekly_trend_current_month&department_id=${departmentId}`, { credentials: 'include' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            renderSORWeeklyTrendChart(data.labels, data.data);
        } catch (e) {
            console.error('SOR Weekly Trend Error:', e);
        }
    }
    function renderSORWeeklyTrendChart(labels, counts) {
        if (sorWeeklyTrendChartInstance) sorWeeklyTrendChartInstance.destroy();
        const weeklyTrendCanvas = document.getElementById('sorWeeklyTrendChart');
        if (!weeklyTrendCanvas) return;
        const ctx = weeklyTrendCanvas.getContext('2d');
        sorWeeklyTrendChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Reports',
                    data: counts,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,0.1)',
                    fill: false,
                    tension: 0.3,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
    // Fetch and render Top 7 Open Items per Assignee
    async function fetchAndRenderTop7OpenByAssignee(departmentId = 'all') {
        try {
            const res = await fetch(`api.php?action=get_top7_open_by_assignee&department_id=${departmentId}`, { credentials: 'include' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            renderTop7OpenByAssigneeChart(data.labels, data.data);
        } catch (e) {
            console.error('Top 7 Open by Assignee Error:', e);
        }
    }
    function renderTop7OpenByAssigneeChart(labels, counts) {
        if (top7OpenByAssigneeChartInstance) top7OpenByAssigneeChartInstance.destroy();
        const topAssigneeCanvas = document.getElementById('top7OpenByAssigneeChart');
        if (!topAssigneeCanvas) return;
        const ctx = topAssigneeCanvas.getContext('2d');
        top7OpenByAssigneeChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Open Items',
                    data: counts,
                    backgroundColor: '#f59e0b',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
    // Fetch and render Days Open per Observation
    async function fetchAndRenderDaysOpenChart(departmentId = 'all') {
        try {
            const res = await fetch(`api.php?action=get_days_open_distribution&department_id=${departmentId}`, { credentials: 'include' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            renderDaysOpenChart(data.labels, data.data);
        } catch (e) {
            console.error('Days Open Chart Error:', e);
        }
    }
    function renderDaysOpenChart(labels, counts) {
        if (daysOpenChartInstance) daysOpenChartInstance.destroy();
        const daysOpenCanvas = document.getElementById('daysOpenChart');
        if (!daysOpenCanvas) return;
        const ctx = daysOpenCanvas.getContext('2d');
        daysOpenChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Observations',
                    data: counts,
                    backgroundColor: ['#0ea5e9', '#f59e0b', '#f43f5e', '#6366f1'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
    // Fetch and render Top 5 Observation Descriptions
    async function fetchAndRenderTop5DescriptionChart(departmentId = 'all') {
        try {
            const res = await fetch(`api.php?action=get_top5_observation_descriptions&department_id=${departmentId}`, { credentials: 'include' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            renderTop5DescriptionChart(data.labels, data.data);
        } catch (e) {
            console.error('Top 5 Description Chart Error:', e);
        }
    }
    function renderTop5DescriptionChart(labels, counts) {
        if (top5DescriptionChartInstance) top5DescriptionChartInstance.destroy();
        const top5Canvas = document.getElementById('top5DescriptionChart');
        if (!top5Canvas) return;
        const ctx = top5Canvas.getContext('2d');
        top5DescriptionChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Count',
                    data: counts,
                    backgroundColor: '#6366f1',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.parsed.x}`;
                            }
                        }
                    }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },
                    y: { ticks: { callback: function(value, index, values) {
                        let label = labels[index];
                        return label.length > 45 ? label.slice(0, 42) + '...' : label;
                    }}}
                }
            }
        });
    }
    // Fetch and render Monthly Observations (Jan-Dec)
    async function fetchAndRenderMonthlyObservationChart(departmentId = 'all') {
        try {
            const res = await fetch(`api.php?action=get_monthly_observation_trend&department_id=${departmentId}`, { credentials: 'include' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            renderMonthlyObservationChart(data.labels, data.data);
        } catch (e) {
            console.error('Monthly Observation Chart Error:', e);
        }
    }
    function renderMonthlyObservationChart(labels, counts) {
        if (monthlyObservationChartInstance) monthlyObservationChartInstance.destroy();
        const canvas = document.getElementById('monthlyObservationChart');
        if (!canvas) return;
        const maxVal = Math.max(0, ...counts);
        const desiredTicks = 8;
        const computedStep = Math.max(1, Math.ceil(maxVal / desiredTicks));
        const ctx = canvas.getContext('2d');
        monthlyObservationChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Observations',
                    data: counts,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#0ea5e9',
                    showLine: true,
                    spanGaps: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false }, tooltip: { enabled: true } },
                elements: { point: { radius: 4 } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            stepSize: computedStep,
                            maxTicksLimit: desiredTicks + 1
                        }
                    }
                }
            }
        });
    }
    // Call these after dashboard loads
    fetchAndRenderSORDaily();
    fetchAndRenderSORWeeklyDept();
    fetchAndRenderSORWeeklyTrend();
    fetchAndRenderTop7OpenByAssignee();
    fetchAndRenderDaysOpenChart();
    fetchAndRenderTop5DescriptionChart();
    fetchAndRenderMonthlyObservationChart();
    </script>
</body>
</html>

