<?php
date_default_timezone_set('Asia/Manila');
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: index.html');
    exit;
}
include 'db_config.php';
// Total BBS Observations
$total = $conn->query("SELECT COUNT(*) as cnt FROM bbs_checklists")->fetch_assoc()['cnt'];
// Safe/Unsafe counts
$safe = $conn->query("SELECT COUNT(*) as cnt FROM bbs_checklist_answers WHERE value='safe'")->fetch_assoc()['cnt'];
$unsafe = $conn->query("SELECT COUNT(*) as cnt FROM bbs_checklist_answers WHERE value='unsafe'")->fetch_assoc()['cnt'];
// Top 5 most frequent unsafe items
$top_unsafe_items = $conn->query("SELECT i.label, COUNT(*) as cnt FROM bbs_checklist_answers a JOIN bbs_observation_items i ON a.item_id=i.id WHERE a.value='unsafe' GROUP BY a.item_id ORDER BY cnt DESC LIMIT 5");
$unsafe_items_labels = [];
$unsafe_items_counts = [];
while ($row = $top_unsafe_items->fetch_assoc()) {
    $unsafe_items_labels[] = $row['label'];
    $unsafe_items_counts[] = $row['cnt'];
}
// Top 5 employees with most unsafe
$top_unsafe_emps = $conn->query("SELECT e.name, COUNT(*) as cnt FROM bbs_checklists c JOIN bbs_checklist_answers a ON c.id=a.checklist_id JOIN employees e ON c.employee_id=e.id WHERE a.value='unsafe' GROUP BY c.employee_id ORDER BY cnt DESC LIMIT 5");
$unsafe_emps_labels = [];
$unsafe_emps_counts = [];
while ($row = $top_unsafe_emps->fetch_assoc()) {
    $unsafe_emps_labels[] = $row['name'];
    $unsafe_emps_counts[] = $row['cnt'];
}
// Top 5 employees with highest safe rate (min 1 answer)
$top_safe_emps = $conn->query("SELECT e.name, SUM(a.value='safe') as safe_cnt FROM bbs_checklists c JOIN bbs_checklist_answers a ON c.id=a.checklist_id JOIN employees e ON c.employee_id=e.id GROUP BY c.employee_id ORDER BY safe_cnt DESC LIMIT 5");
$safe_emps_labels = [];
$safe_emps_counts = [];
while ($row = $top_safe_emps->fetch_assoc()) {
    $safe_emps_labels[] = $row['name'];
    $safe_emps_counts[] = $row['safe_cnt'];
}
// BBS Daily Submission (today, per observer)
$today = date('Y-m-d');
$daily_submissions = [];
$observers = [];
$res = $conn->query("SELECT DISTINCT observer FROM bbs_checklists WHERE date_of_observation = '$today'");
if (!$res) {
    die("Query Error (observer fetch): " . $conn->error);
}
while ($row = $res->fetch_assoc()) {
    $observers[] = $row['observer'];
}
foreach ($observers as $observer) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bbs_checklists WHERE observer = ? AND date_of_observation = ?");
    $stmt->bind_param('ss', $observer, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $daily_submissions[] = $result->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();
}
// Top 5 Unsafe Locations (by department)
$top_unsafe_locations = $conn->query("
    SELECT d.name AS department, COUNT(*) as cnt
    FROM bbs_checklist_answers a
    JOIN bbs_checklists c ON a.checklist_id = c.id
    JOIN departments d ON c.department_id = d.id
    WHERE a.value = 'unsafe'
    GROUP BY d.name
    ORDER BY cnt DESC
    LIMIT 5
");
if (!$top_unsafe_locations) {
    die('Query Error (unsafe locations): ' . $conn->error);
}
$unsafe_locations_labels = [];
$unsafe_locations_counts = [];
while ($row = $top_unsafe_locations->fetch_assoc()) {
    $unsafe_locations_labels[] = $row['department'];
    $unsafe_locations_counts[] = $row['cnt'];
}
// --- KPI Calculations ---
$total_reports = $total;
$total_safe = $safe;
$total_unsafe = $unsafe;
$unique_observers = $conn->query("SELECT COUNT(DISTINCT observer) as cnt FROM bbs_checklists")->fetch_assoc()['cnt'];
$avg_per_user = $unique_observers ? round($total_reports / $unique_observers, 2) : 0;
$safe_pct = ($total_safe + $total_unsafe) > 0 ? round(($total_safe / ($total_safe + $total_unsafe)) * 100, 1) : 0;
$unsafe_pct = ($total_safe + $total_unsafe) > 0 ? round(($total_unsafe / ($total_safe + $total_unsafe)) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BBS Checklist Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-orange-50 min-h-screen">
    <!-- Top Navbar (copied from admin.php) -->
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
                <span class="sidebar-label">Dashboard</span>
            </a>
            <a href="admin_departments.php" class="sidebar-icon text-cyan-600" title="Manage Departments">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span class="sidebar-label">Departments</span>
            </a>
            <a href="admin_employees.php" class="sidebar-icon text-cyan-600" title="Manage Employees">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="sidebar-label">Employees</span>
            </a>
            <a href="bbs_checklist_report.php" class="sidebar-icon text-cyan-600" title="BBS Checklist Report">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="sidebar-label">BBS Report</span>
            </a>
            <a href="admin_bbs_items.php" class="sidebar-icon text-cyan-600" title="Manage BBS Checkpoints">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span class="sidebar-label">BBS Items</span>
            </a>
            <a href="bbs_dashboard.php" class="sidebar-icon text-blue-700 hover:text-orange-600" title="BBS Checklist Dashboard" aria-label="BBS Checklist Dashboard">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="3" y="13" width="4" height="8" fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5"/>
                    <rect x="9" y="9" width="4" height="12" fill="#0ea5e9" stroke="#0ea5e9" stroke-width="1.5"/>
                    <rect x="15" y="5" width="4" height="16" fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5"/>
                </svg>
                <span class="sidebar-label">BBS Dashboard</span>
            </a>
            <a href="sor_report.php" class="sidebar-icon text-blue-700 hover:text-orange-600" title="SOR Report" aria-label="SOR Report">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="sidebar-label">SOR Report</span>
            </a>
        </div>
        <button onclick="goToMainApp()" class="sidebar-icon text-blue-500" title="Main App">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
        </button>
    </nav>
    <div class="max-w-7xl mx-auto py-8">
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-green-50 rounded-xl p-6 text-center border border-green-100 shadow">
                <div class="text-sm text-green-700 font-semibold mb-1">Total Reports</div>
                <div class="text-3xl font-bold text-green-700" id="kpi-total"></div>
            </div>
            <div class="bg-green-50 rounded-xl p-6 text-center border border-green-100 shadow">
                <div class="text-sm text-green-700 font-semibold mb-1">Safe %</div>
                <div class="text-2xl font-bold text-green-700" id="kpi-safe"></div>
            </div>
            <div class="bg-red-50 rounded-xl p-6 text-center border border-red-100 shadow">
                <div class="text-sm text-red-700 font-semibold mb-1">Unsafe %</div>
                <div class="text-2xl font-bold text-red-700" id="kpi-unsafe"></div>
            </div>
        </div>
        <!-- Chart Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 w-full">
            <div class="bg-white rounded-2xl border border-green-100 p-6 shadow flex flex-col items-center min-h-[400px]">
                <h3 class="text-base font-bold text-green-700 mb-3">Top 5 Employees (Safe Records)</h3>
                <canvas id="safeEmpsChart"></canvas>
            </div>
            <div class="bg-white rounded-2xl border border-red-100 p-6 shadow flex flex-col items-center min-h-[400px] w-full">
                <h3 class="text-base font-bold text-red-700 mb-3">Top 5 Employees (Unsafe Records)</h3>
                <canvas id="unsafeEmpsChart"></canvas>
            </div>
        </div>
        <div id="unsafe-emp-items-table-container" class="bg-white rounded-2xl border border-red-100 p-6 shadow min-h-[200px] mb-8"></div>
        <div class="grid grid-cols-1 gap-8 mb-8 w-full">
            <div class="bg-white rounded-2xl border border-red-100 p-6 shadow flex flex-col items-center min-h-[400px]">
            <h3 class="text-base font-bold text-red-700 mb-3">Top 5 Most Violated Items</h3>
            <canvas id="violatedItemsChart"></canvas>
        </div>
            </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 w-full">
            <div class="bg-white rounded-2xl border border-orange-100 p-6 shadow flex flex-col items-center min-h-[400px]">
                <h3 class="text-base font-bold text-orange-700 mb-3">Top 5 Locations (Unsafe Observations)</h3>
                <canvas id="unsafeLocationsChart"></canvas>
            </div>
            <div class="bg-white rounded-2xl border border-blue-100 p-6 shadow flex flex-col items-center min-h-[400px]">
                <h3 class="text-base font-bold text-blue-700 mb-3">BBS Submission Trend</h3>
                <canvas id="bbsSubmissionChart"></canvas>
            </div>
        </div>
    </div>
    <!-- Observer Edit Modal (hidden by default) -->
    <div id="observerModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4" id="observerModalTitle">Edit Observer</h3>
            <form id="observerForm" class="space-y-4">
                <input type="hidden" id="observerId">
                <div>
                    <label class="block text-gray-700">Observer Name</label>
                    <input type="text" id="observerInput" class="input-field w-full border rounded px-3 py-2" required>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeObserverModal()" class="btn-secondary px-4 py-2 rounded">Cancel</button>
                    <button type="submit" class="btn-primary bg-cyan-600 text-white px-4 py-2 rounded">Save</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    let safeEmpsChart, unsafeEmpsChart, violatedItemsChart, employeeViolationItemsChart, unsafeLocationsChart, bbsSubmissionChart;
    
    // Remove flatpickr, loadBbsDashboardData, loadDepartments, and all references to weekPicker, departmentFilter, dailyTarget
    // Only keep code for loading and displaying all data
    document.addEventListener('DOMContentLoaded', function() {
        fetchDashboardData();
    });

    function formatDate(date) {
        const options = { month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }

    function setWeekDates(monday) {
        // Ensure we have a Monday
            monday = getMonday(monday);
        
        // Create dates for the week (Monday-Saturday)
        const dates = [];
        for (let i = 0; i < 6; i++) {
            const d = new Date(monday);
            d.setDate(monday.getDate() + i);
            dates.push(d);
        }

        // Update column headers with dates
        const headers = document.querySelectorAll('th[id^="date-"]');
        headers.forEach((header, index) => {
            if (dates[index]) {
                header.textContent = formatDate(dates[index]);
            }
        });

        // Update selected week range display
        const saturday = dates[5];
        document.getElementById('selected-week-range').textContent = 
            `Week of ${formatDate(monday)} - ${formatDate(saturday)}`;
    }

    async function loadBbsDashboardData(monday) {
        // Validate input
        if (!monday || !(monday instanceof Date)) {
            console.error('loadBbsDashboardData: Invalid date input');
            return;
        }
        
        setWeekDates(monday);
        const weekStart = monday.toISOString().slice(0,10);
        const department = document.getElementById('departmentFilter').value;
        
        try {
            const res = await fetch(`bbs_dashboard_data.php?filter=weekly&week_start=${weekStart}&department=${department}`);
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON response:', text);
                throw new Error('Server did not return valid JSON. See console for details.');
            }
            setDailyTargetFromBackend(data);
            renderAllCharts(data);
        } catch (err) {
            console.error('Failed to load dashboard data:', err);
            alert('Failed to load dashboard data: ' + err.message);
        }
    }

    function renderAllCharts(data) {
        // Defensive check for KPI data
        if (!data.kpi) {
            alert('Dashboard data error: ' + (data.error || 'No KPI data returned'));
            return;
        }
        updateKPIs(data.kpi);
        // Safe Employees
        if (safeEmpsChart) safeEmpsChart.destroy();
        safeEmpsChart = new Chart(document.getElementById('safeEmpsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.safe_emps.labels || [],
                datasets: [{
                    label: 'Safe Records',
                    data: data.safe_emps.counts || [],
                    backgroundColor: '#22c55e',
                    borderRadius: 8,
                    barThickness: 32
                }]
            },
            options: {
                indexAxis: 'x',
                plugins: { 
                    legend: { display: false } 
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 12, weight: 'bold' } }
                    },
                    x: {
                        beginAtZero: true,
                        ticks: {
                            font: { size: 12, weight: 'bold' },
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });

        // Unsafe Employees
        if (unsafeEmpsChart) unsafeEmpsChart.destroy();
        unsafeEmpsChart = new Chart(document.getElementById('unsafeEmpsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.unsafe_emps.labels || [],
                datasets: [{
                    label: 'Unsafe Records',
                    data: data.unsafe_emps.counts || [],
                    backgroundColor: '#ef4444',
                    borderRadius: 8,
                    barThickness: 32
                }]
            },
            options: {
                indexAxis: 'x',
                plugins: { 
                    legend: { display: false } 
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 12, weight: 'bold' } }
                    },
                    x: {
                        beginAtZero: true,
                        ticks: {
                            font: { size: 12, weight: 'bold' },
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });

        // Violated Items
        if (violatedItemsChart) violatedItemsChart.destroy();
        violatedItemsChart = new Chart(document.getElementById('violatedItemsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.violated_items.labels || [],
                datasets: [{
                    label: 'Violations',
                    data: data.violated_items.counts || [],
                    backgroundColor: '#ef4444',
                    borderRadius: 8,
                    barThickness: 32
                }]
            },
            options: {
                indexAxis: 'x',
                plugins: { 
                    legend: { 
                        display: false 
                    } 
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            font: { 
                                size: 12, 
                                weight: 'bold' 
                            }
                        }
                    },
                    x: {
                        beginAtZero: true,
                        ticks: { 
                            font: { 
                                size: 12, 
                                weight: 'bold' 
                            },
                            maxRotation: 0,
                            minRotation: 0,
                            callback: function(value) {
                                const label = this.getLabelForValue(value);
                                const words = label.split(' ');
                                const lines = [];
                                let currentLine = '';
                                
                                words.forEach(word => {
                                    if (currentLine.length + word.length > 15) {
                                        lines.push(currentLine);
                                        currentLine = word;
                                    } else {
                                        currentLine += (currentLine.length ? ' ' : '') + word;
                                    }
                                });
                                if (currentLine) {
                                    lines.push(currentLine);
                                }
                                return lines;
                            }
                        }
                    }
                }
            }
        });

        // Unsafe Locations
        if (unsafeLocationsChart) unsafeLocationsChart.destroy();
        unsafeLocationsChart = new Chart(document.getElementById('unsafeLocationsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.unsafe_locations.labels || [],
                datasets: [{
                    label: 'Unsafe Observations',
                    data: data.unsafe_locations.counts || [],
                    backgroundColor: '#f59e0b',
                    borderRadius: 8,
                    barThickness: 32
                }]
            },
            options: {
                indexAxis: 'x',
                plugins: { 
                    legend: { 
                        display: false 
                    } 
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            font: { 
                                size: 12, 
                                weight: 'bold' 
                            }
                        }
                    },
                    x: {
                        beginAtZero: true,
                        ticks: { 
                            font: { 
                                size: 12, 
                                weight: 'bold' 
                            },
                            maxRotation: 0,
                            minRotation: 0,
                            callback: function(value) {
                                const label = this.getLabelForValue(value);
                                const words = label.split(' ');
                                const lines = [];
                                let currentLine = '';
                                
                                words.forEach(word => {
                                    if (currentLine.length + word.length > 15) {
                                        lines.push(currentLine);
                                        currentLine = word;
                                    } else {
                                        currentLine += (currentLine.length ? ' ' : '') + word;
                                    }
                                });
                                if (currentLine) {
                                    lines.push(currentLine);
                                }
                                return lines;
                            }
                        }
                    }
                }
            }
        });

        // Render unsafe items per employee table
        renderUnsafeEmpItemsTable(data.unsafe_emp_items || {});
    }

    // Observer management functions
    function editObserver(observer) {
        document.getElementById('observerId').value = observer;
        document.getElementById('observerInput').value = observer;
        document.getElementById('observerModalTitle').textContent = 'Edit Observer';
        document.getElementById('observerModal').classList.remove('hidden');
    }

    function closeObserverModal() {
        document.getElementById('observerModal').classList.add('hidden');
    }

    async function deleteObserver(observer) {
        if (!confirm('Delete this observer?')) return;
        try {
            const res = await fetch('api.php?action=delete_observer', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `observer=${encodeURIComponent(observer)}`,
                credentials: 'include'
            });
            const data = await res.json();
            if (data.success) {
                const monday = getMonday(new Date(document.getElementById('weekPicker').value));
                loadBbsDashboardData(monday);
            } else {
                alert(data.message || 'Failed to delete observer');
            }
        } catch (err) {
            alert('Failed to delete observer: ' + err.message);
        }
    }

    document.getElementById('observerForm').onsubmit = async function(e) {
        e.preventDefault();
        const observer = document.getElementById('observerId').value;
        const newObserver = document.getElementById('observerInput').value;
        
        if (!newObserver) {
            alert('Observer name is required');
            return;
        }

        let url, body;
        if (observer) {
            url = 'api.php?action=edit_observer';
            body = `observer=${encodeURIComponent(observer)}&new_observer=${encodeURIComponent(newObserver)}`;
        } else {
            url = 'api.php?action=add_observer';
            body = `observer=${encodeURIComponent(newObserver)}`;
        }

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body,
                credentials: 'include'
            });
            const data = await res.json();
            if (data.success) {
                closeObserverModal();
                const monday = getMonday(new Date(document.getElementById('weekPicker').value));
                loadBbsDashboardData(monday);
            } else {
                alert(data.message || 'Failed to save observer');
            }
        } catch (err) {
            alert('Failed to save observer: ' + err.message);
        }
    };

    document.getElementById('addObserverBtn').onclick = function() {
        document.getElementById('observerId').value = '';
        document.getElementById('observerInput').value = '';
        document.getElementById('observerModalTitle').textContent = 'Add Observer';
        document.getElementById('observerModal').classList.remove('hidden');
    };

    // Render unsafe items per employee as a table
    function renderUnsafeEmpItemsTable(unsafeEmpItems) {
        const container = document.getElementById('unsafe-emp-items-table-container');
        if (!unsafeEmpItems || Object.keys(unsafeEmpItems).length === 0) {
            container.innerHTML = '';
            return;
        }
        let html = `<div class='overflow-x-auto'><table class='min-w-full text-xs text-left border border-gray-200'>`;
        html += `<thead><tr class='bg-red-100 text-red-700'>
            <th class='px-2 py-1 border'>Employee</th>
            <th class='px-2 py-1 border'>Violated Item</th>
            <th class='px-2 py-1 border'>Count</th>
            <th class='px-2 py-1 border'>Department</th>
            <th class='px-2 py-1 border'>Dates</th>
        </tr></thead><tbody>`;
        for (const [emp, items] of Object.entries(unsafeEmpItems)) {
            if (items.length === 0) {
                html += `<tr><td class='border px-2 py-1'>${emp}</td><td class='border px-2 py-1' colspan='4'>No violations</td></tr>`;
            } else {
                for (const item of items) {
                    // Get unique departments for this item
                    const departments = [...new Set(item.occurrences.map(o => o.department))];
                    html += `<tr>`;
                    html += `<td class='border px-2 py-1'>${emp}</td>`;
                    html += `<td class='border px-2 py-1'>${item.label}</td>`;
                    html += `<td class='border px-2 py-1'>${item.count}</td>`;
                    html += `<td class='border px-2 py-1'>[${departments.map(d => `'${d}'`).join(', ')}]</td>`;
                    html += `<td class='border px-2 py-1'>[${item.occurrences.map(o => `'${o.date}'`).join(', ')}]</td>`;
                    html += `</tr>`;
                }
            }
        }
        html += `</tbody></table></div>`;
        container.innerHTML = html;
    }

    // Patch: Use dailyTarget from backend
    let dailyTarget = 3;
    function setDailyTargetFromBackend(data) {
        if (data.daily_target) {
            dailyTarget = data.daily_target;
            document.getElementById('dailyTarget').value = dailyTarget;
        }
    }

    // UpdateKPIs function to use new backend data structure
    function updateKPIs(kpi) {
        // Update KPI cards if they exist
        const totalReports = document.getElementById('kpi-total');
        const safePct = document.getElementById('kpi-safe');
        const unsafePct = document.getElementById('kpi-unsafe');
        if (totalReports) totalReports.textContent = kpi.total_reports || 0;
        if (safePct) safePct.textContent = (kpi.safe_pct || 0).toFixed(1) + '%';
        if (unsafePct) unsafePct.textContent = (kpi.unsafe_pct || 0).toFixed(1) + '%';
    }

    // Update data loading to only use new structure
    async function fetchDashboardData() {
        try {
            const response = await fetch('bbs_dashboard_data.php');
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to load dashboard data');
            }
            const data = await response.json();
            if (!data || typeof data !== 'object') {
                throw new Error('Invalid response format');
            }
            renderAllCharts(data);
        } catch (error) {
            console.error('Dashboard Error:', error);
            alert(`Error: ${error.message}`);
        }
    }

    // Update event listeners to use fetchDashboardData
    document.addEventListener('DOMContentLoaded', function() {
        // Set initial week to current week
        const today = new Date();
        const monday = getMonday(today);
        document.getElementById('weekPicker').value = monday.toISOString().split('T')[0];
        setWeekDates(monday);
        fetchDashboardData();
    });
    document.addEventListener('DOMContentLoaded', function() {
        const weekPicker = document.getElementById('weekPicker');
        const departmentFilter = document.getElementById('departmentFilter');
        if (weekPicker) {
            weekPicker.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                setWeekDates(selectedDate);
                fetchDashboardData();
            });
        }
        if (departmentFilter) {
            departmentFilter.addEventListener('change', fetchDashboardData);
        }
    });
    </script>
</body>
</html> 