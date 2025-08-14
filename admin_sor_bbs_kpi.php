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
    <title>SOR/BBS KPI Tracker</title>
    
    <!-- Core Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
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
            max-height: 600px;
            overflow-y: auto;
        }

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

        /* Tab Styles */
        .tab-button {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            background: transparent;
        }

        .tab-button.active {
            background-color: white;
            color: #0ea5e9;
            border-bottom: 3px solid #0ea5e9;
        }

        .tab-button:not(.active) {
            background-color: #f3f4f6;
            color: #6b7280;
        }

        .tab-button:not(.active):hover {
            background-color: #e5e7eb;
            color: #374151;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="admin.php" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <span class="font-bold text-lg text-cyan-700">SOR/BBS KPI Tracker</span>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex space-x-2">
                        <select id="weekFilter" class="input-field text-sm">
                            <option value="current">Current Week</option>
                            <option value="last">Last Week</option>
                            <option value="custom">Custom Range</option>
                        </select>
                        <div id="customDateRange" class="hidden">
                            <input type="date" id="startDate" class="input-field text-sm">
                            <input type="date" id="endDate" class="input-field text-sm">
                        </div>
                    </div>
                    <select id="departmentFilter" class="input-field text-sm">
                        <option value="all">All Departments</option>
                    </select>
                    <button onclick="refreshData()" class="btn-primary text-sm">Refresh</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
        <div class="border-b border-gray-200">
            <div class="tabs flex gap-2 mb-6">
                <button class="tab-button active" onclick="switchTab('sor')">SOR KPI</button>
                <button class="tab-button" onclick="switchTab('bbs')">BBS KPI</button>
                <button class="tab-button" onclick="switchTab('sor-trend')">SOR Submission Trend</button>
                <button class="tab-button" onclick="switchTab('bbs-trend')">BBS Submission Trend</button>
            </div>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
        <!-- SOR KPI Tab -->
        <div id="sor-tab" class="tab-content active">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">SOR KPI Compliance</h2>
                
                <!-- Observer Filter -->
                <div class="mb-2 flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label for="sor-observer-filter" class="text-sm font-medium">Observer:</label>
                        <select id="sor-observer-filter" class="input-field text-sm">
                            <option value="all">All Observers</option>
                        </select>
                    </div>
                    <div id="sor-week-range" class="text-sm text-gray-600 font-medium"></div>
                </div>
                
                <!-- SOR Compliance Table -->
                <div class="table-container bg-white rounded shadow p-2 overflow-x-auto mb-6">
                    <table class="min-w-full border text-center">
                        <thead class="bg-cyan-100">
                            <tr>
                                <th class="p-2">No.</th>
                                <th class="p-2">Observer</th>
                                <th class="p-2">Monday</th>
                                <th class="p-2">Tuesday</th>
                                <th class="p-2">Wednesday</th>
                                <th class="p-2">Thursday</th>
                                <th class="p-2">Friday</th>
                                <th class="p-2">Saturday</th>
                                <th class="p-2">Days Met Target</th>
                                <th class="p-2">Total Submissions</th>
                                <th class="p-2">Individual % Compliance</th>
                                <th class="p-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sor-table-body">
                            <!-- Data will be injected here -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8" class="font-bold text-left p-2">Average Compliance</td>
                                <td colspan="3" id="sor-average-compliance" class="text-red-600 font-bold"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <div class="font-semibold text-lg text-cyan-700">Leadership SOR Compliance: <span id="leadership-compliance" class="text-red-500">0%</span></div>
                </div>
            </div>
        </div>

        <!-- BBS KPI Tab -->
        <div id="bbs-tab" class="tab-content">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">BBS KPI Compliance</h2>
                    <button id="add-bbs-observer-btn" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded">Add Observer</button>
                </div>
                
                <!-- Observer Filter -->
                <div class="mb-2 flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label for="bbs-observer-filter" class="text-sm font-medium">Observer:</label>
                        <select id="bbs-observer-filter" class="input-field text-sm">
                            <option value="all">All Observers</option>
                        </select>
                    </div>
                    <div id="bbs-week-range" class="text-sm text-gray-600 font-medium"></div>
                </div>
                
                <!-- BBS Compliance Table -->
                <div class="table-container bg-white rounded shadow p-2 overflow-x-auto mb-6">
                    <table class="min-w-full border text-center">
                        <thead class="bg-blue-100">
                            <tr>
                                <th class="p-2">No.</th>
                                <th class="p-2">Observer</th>
                                <th class="p-2">Monday</th>
                                <th class="p-2">Tuesday</th>
                                <th class="p-2">Wednesday</th>
                                <th class="p-2">Thursday</th>
                                <th class="p-2">Friday</th>
                                <th class="p-2">Saturday</th>
                                <th class="p-2">Days Met Target</th>
                                <th class="p-2">Total Submissions</th>
                                <th class="p-2">Individual % Compliance</th>
                                <th class="p-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bbs-table-body">
                            <!-- Data will be injected here -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8" class="font-bold text-left p-2">Average Compliance</td>
                                <td colspan="3" id="bbs-average-compliance" class="text-red-600 font-bold"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <div class="font-semibold text-lg text-blue-700">Observer BBS Compliance: <span id="observer-compliance" class="text-red-500">0%</span></div>
                </div>
            </div>
        </div>

        <!-- SOR Submission Trend Tab -->
        <div id="sor-trend-tab" class="tab-content">
            <div class="mb-4 flex gap-2">
                <select id="sor-trend-username" class="border rounded px-2 py-1">
                    <option value="">Select user</option>
                </select>
                <button onclick="addSORTrendUser()" class="bg-blue-600 text-white px-4 py-1 rounded">Add User</button>
            </div>
            <div id="sor-trend-cards" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
        </div>

        <!-- BBS Submission Trend Tab -->
        <div id="bbs-trend-tab" class="tab-content">
            <div class="mb-4 flex gap-2">
                <select id="bbs-trend-username" class="border rounded px-2 py-1">
                    <option value="">Select user</option>
                </select>
                <button onclick="addBBSTrendUser()" class="bg-blue-600 text-white px-4 py-1 rounded">Add User</button>
            </div>
            <div id="bbs-trend-cards" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
        </div>
    </div>

    <!-- User Edit Modal (hidden by default) -->
    <div id="edit-user-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
            <div class="bg-white rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">Edit User Compliance</h3>
                <form id="edit-user-form" class="space-y-4">
                    <input type="hidden" id="edit-user-id">
                    <input type="hidden" id="edit-user-type">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" id="edit-username" class="input-field w-full" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Daily Target</label>
                        <input type="number" id="edit-daily-target" class="input-field w-full" min="1" max="10">
                    </div>
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="closeEditModal()" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Observer Modal -->
    <div id="add-bbs-observer-modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">Add Observer</h3>
            <form id="addBbsObserverForm" class="space-y-4">
                <div>
                    <label class="block text-gray-700">Select Observer</label>
                    <select id="bbsObserverSelect" class="input-field w-full border rounded px-3 py-2" required></select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="cancelAddBbsObserver" class="btn-secondary px-4 py-2 rounded">Cancel</button>
                    <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded">Add</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentTab = 'sor';
        let currentWeekStart = '';
        let currentWeekEnd = '';
        let currentDepartment = 'all';

        // Add global variables to store last loaded SOR and BBS data
        let lastSORUsers = [];
        let lastSORCompliance = {};
        let lastSORLeadershipCompliance = 0;
        let lastSORDailyTarget = 3;
        let lastBBSUsers = [];
        let lastBBSCompliance = {};
        let lastBBSObserverCompliance = 0;
        let lastBBSDailyTarget = 3;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            initializeDateRange();
            loadDepartments();
            loadSORData();
            loadBBSData();
        });

        // Tab switching function
        function switchTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tab + '-tab').classList.add('active');
            
            currentTab = tab;
        }

        // Initialize date range picker
        function initializeDateRange() {
            const today = new Date();
            const monday = new Date(today);
            monday.setDate(today.getDate() - today.getDay() + 1);
            
            currentWeekStart = monday.toISOString().split('T')[0];
            currentWeekEnd = new Date(monday.getTime() + 5 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            
            document.getElementById('startDate').value = currentWeekStart;
            document.getElementById('endDate').value = currentWeekEnd;
        }

        // Load departments
        async function loadDepartments() {
            try {
                const response = await fetch('get_departments.php');
                const departments = await response.json();
                
                const select = document.getElementById('departmentFilter');
                departments.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept.id;
                    option.textContent = dept.name;
                    select.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading departments:', error);
            }
        }

        // Load SOR compliance data
        async function loadSORData() {
            try {
                const response = await fetch('api.php?action=get_sor_compliance_tracker', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `week_start=${currentWeekStart}&week_end=${currentWeekEnd}&department=${currentDepartment}`
                });
                const data = await response.json();
                if (data.success) {
                    lastSORUsers = data.users;
                    lastSORCompliance = data.compliance;
                    lastSORLeadershipCompliance = data.leadership_compliance;
                    lastSORDailyTarget = data.daily_target;
                    renderSORTableFiltered();
                } else {
                    console.error('SOR Compliance API error:', data.message);
                }
            } catch (error) {
                console.error('Failed to load SOR compliance data:', error);
            }
            updateWeekRangeDisplays();
        }

        // Load BBS compliance data
        async function loadBBSData() {
            try {
                const response = await fetch('api.php?action=get_bbs_compliance_tracker', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `week_start=${currentWeekStart}&week_end=${currentWeekEnd}&department=${currentDepartment}`
                });
                const data = await response.json();
                if (data.success) {
                    lastBBSUsers = data.users;
                    lastBBSCompliance = data.compliance;
                    lastBBSObserverCompliance = data.observer_compliance;
                    lastBBSDailyTarget = data.daily_target;
                    renderBBSTableFiltered();
                } else {
                    console.error('BBS Compliance API error:', data.message);
                }
            } catch (error) {
                console.error('Failed to load BBS compliance data:', error);
            }
            updateWeekRangeDisplays();
        }

        // Render SOR table
        function renderSORTable(users, compliance, leadershipCompliance, dailyTarget) {
            const filter = document.getElementById('sor-observer-filter');
            let selected = filter ? filter.value : 'all';
            if (filter && (filter.options.length !== users.length + 1 || Array.from(filter.options).slice(1).some((opt, i) => opt.value !== users[i].username))) {
                filter.innerHTML = '<option value="all">All Observers</option>';
                users.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.username;
                    opt.textContent = u.username;
                    filter.appendChild(opt);
                });
                filter.value = selected;
            } else if (filter) {
                filter.value = selected;
            }
            let filteredUsers = users;
            if (filter && filter.value !== 'all') {
                filteredUsers = users.filter(u => u.username === filter.value);
            }
            filteredUsers = filteredUsers.slice().sort((a, b) => {
                const aTotal = (compliance[a.username]?.days || []).reduce((sum, day) => sum + (day.count || 0), 0);
                const bTotal = (compliance[b.username]?.days || []).reduce((sum, day) => sum + (day.count || 0), 0);
                return bTotal - aTotal;
            });
            const tbody = document.getElementById('sor-table-body');
            tbody.innerHTML = '';
            let totalCompliance = 0;
            let observerCount = 0;
            filteredUsers.forEach((user, index) => {
                const userCompliance = compliance[user.username] || {};
                const days = userCompliance.days || [];
                const userDailyTarget = userCompliance.daily_target || dailyTarget;
                const daysMet = days.filter(day => day.count >= userDailyTarget).length;
                const totalSubmissions = days.reduce((sum, day) => sum + day.count, 0);
                const individualCompliance = userCompliance.compliance || 0;
                if (individualCompliance > 0) {
                    totalCompliance += individualCompliance;
                    observerCount++;
                }
                const dayData = {
                    monday: days[0]?.count || 0,
                    tuesday: days[1]?.count || 0,
                    wednesday: days[2]?.count || 0,
                    thursday: days[3]?.count || 0,
                    friday: days[4]?.count || 0,
                    saturday: days[5]?.count || 0
                };
                const row = `
                    <tr class="hover:bg-gray-50">
                        <td class="p-2">${index + 1}</td>
                        <td class="p-2 font-medium">${user.username}</td>
                        <td class="p-2 ${dayData.monday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.monday}</td>
                        <td class="p-2 ${dayData.tuesday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.tuesday}</td>
                        <td class="p-2 ${dayData.wednesday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.wednesday}</td>
                        <td class="p-2 ${dayData.thursday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.thursday}</td>
                        <td class="p-2 ${dayData.friday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.friday}</td>
                        <td class="p-2 ${dayData.saturday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.saturday}</td>
                        <td class="p-2 font-bold">${daysMet}</td>
                        <td class="p-2">${totalSubmissions}</td>
                        <td class="p-2 font-bold ${individualCompliance >= 80 ? 'text-green-600' : individualCompliance >= 60 ? 'text-yellow-600' : 'text-red-600'}">${individualCompliance.toFixed(1)}%</td>
                        <td class="p-2">
                            <button onclick="editUser('${user.username}', 'sor', ${userDailyTarget})" class="text-blue-600 hover:text-blue-900 text-sm">Edit</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
            const averageCompliance = observerCount > 0 ? (totalCompliance / observerCount).toFixed(1) : '0.0';
            document.getElementById('sor-average-compliance').textContent = averageCompliance + '%';
            document.getElementById('leadership-compliance').textContent = leadershipCompliance.toFixed(1) + '%';
        }

        // Render BBS table
        function renderBBSTable(users, compliance, observerCompliance, dailyTarget) {
            const filter = document.getElementById('bbs-observer-filter');
            let selected = filter ? filter.value : 'all';
            if (filter && (filter.options.length !== users.length + 1 || Array.from(filter.options).slice(1).some((opt, i) => opt.value !== users[i].username))) {
                filter.innerHTML = '<option value="all">All Observers</option>';
                users.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.username;
                    opt.textContent = u.username;
                    filter.appendChild(opt);
                });
                filter.value = selected;
            } else if (filter) {
                filter.value = selected;
            }
            let filteredUsers = users;
            if (filter && filter.value !== 'all') {
                filteredUsers = users.filter(u => u.username === filter.value);
            }
            filteredUsers = filteredUsers.slice().sort((a, b) => {
                const aTotal = (compliance[a.username]?.days || []).reduce((sum, day) => sum + (day.count || 0), 0);
                const bTotal = (compliance[b.username]?.days || []).reduce((sum, day) => sum + (day.count || 0), 0);
                return bTotal - aTotal;
            });
            const tbody = document.getElementById('bbs-table-body');
            tbody.innerHTML = '';
            let totalCompliance = 0;
            let observerCount = 0;
            filteredUsers.forEach((user, index) => {
                const userCompliance = compliance[user.username] || {};
                const days = userCompliance.days || [];
                const userDailyTarget = userCompliance.daily_target || dailyTarget;
                const daysMet = days.filter(day => day.count >= userDailyTarget).length;
                const totalSubmissions = days.reduce((sum, day) => sum + day.count, 0);
                const individualCompliance = userCompliance.compliance || 0;
                if (individualCompliance > 0) {
                    totalCompliance += individualCompliance;
                    observerCount++;
                }
                const dayData = {
                    monday: days[0]?.count || 0,
                    tuesday: days[1]?.count || 0,
                    wednesday: days[2]?.count || 0,
                    thursday: days[3]?.count || 0,
                    friday: days[4]?.count || 0,
                    saturday: days[5]?.count || 0
                };
                const row = `
                    <tr class="hover:bg-gray-50">
                        <td class="p-2">${index + 1}</td>
                        <td class="p-2 font-medium">${user.username}</td>
                        <td class="p-2 ${dayData.monday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.monday}</td>
                        <td class="p-2 ${dayData.tuesday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.tuesday}</td>
                        <td class="p-2 ${dayData.wednesday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.wednesday}</td>
                        <td class="p-2 ${dayData.thursday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.thursday}</td>
                        <td class="p-2 ${dayData.friday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.friday}</td>
                        <td class="p-2 ${dayData.saturday >= userDailyTarget ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${dayData.saturday}</td>
                        <td class="p-2 font-bold">${daysMet}</td>
                        <td class="p-2">${totalSubmissions}</td>
                        <td class="p-2 font-bold ${individualCompliance >= 80 ? 'text-green-600' : individualCompliance >= 60 ? 'text-yellow-600' : 'text-red-600'}">${individualCompliance.toFixed(1)}%</td>
                        <td class="p-2">
                            <button onclick="editUser('${user.username}', 'bbs', ${userDailyTarget})" class="text-blue-600 hover:text-blue-900 text-sm">Edit</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
            const averageCompliance = observerCount > 0 ? (totalCompliance / observerCount).toFixed(1) : '0.0';
            document.getElementById('bbs-average-compliance').textContent = averageCompliance + '%';
            document.getElementById('observer-compliance').textContent = observerCompliance.toFixed(1) + '%';
        }

        // Edit user function
        function editUser(username, type, currentTarget) {
            document.getElementById('edit-user-id').value = username;
            document.getElementById('edit-user-type').value = type;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-daily-target').value = currentTarget;
            document.getElementById('edit-user-modal').classList.remove('hidden');
        }

        // Close edit modal
        function closeEditModal() {
            document.getElementById('edit-user-modal').classList.add('hidden');
        }

        // Handle form submission
        document.getElementById('edit-user-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('edit-username').value;
            const type = document.getElementById('edit-user-type').value;
            const dailyTarget = document.getElementById('edit-daily-target').value;
            
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_user_daily_target&username=${username}&type=${type}&daily_target=${dailyTarget}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeEditModal();
                    if (type === 'sor') {
                        loadSORData();
                    } else {
                        loadBBSData();
                    }
                } else {
                    alert('Failed to update user: ' + result.message);
                }
            } catch (error) {
                console.error('Error updating user:', error);
                alert('Error updating user');
            }
        });

        // Refresh data
        function refreshData() {
            if (currentTab === 'sor') {
                loadSORData();
            } else {
                loadBBSData();
            }
        }

        // Event listeners for filters
        document.getElementById('weekFilter').addEventListener('change', function() {
            const value = this.value;
            const customRange = document.getElementById('customDateRange');
            
            if (value === 'custom') {
                customRange.classList.remove('hidden');
            } else {
                customRange.classList.add('hidden');
                if (value === 'current') {
                    initializeDateRange();
                } else if (value === 'last') {
                    const lastMonday = new Date(currentWeekStart);
                    lastMonday.setDate(lastMonday.getDate() - 7);
                    currentWeekStart = lastMonday.toISOString().split('T')[0];
                    currentWeekEnd = new Date(lastMonday.getTime() + 5 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                }
                refreshData();
            }
        });

        document.getElementById('departmentFilter').addEventListener('change', function() {
            currentDepartment = this.value;
            refreshData();
        });

        document.getElementById('startDate').addEventListener('change', function() {
            currentWeekStart = this.value;
            refreshData();
        });

        document.getElementById('endDate').addEventListener('change', function() {
            currentWeekEnd = this.value;
            refreshData();
        });

        // --- Trend Tab Logic ---
        let sorTrendUsers = [];
        let bbsTrendUsers = [];
        let sorTrendCharts = {};
        let bbsTrendCharts = {};
        let sorUserList = [];
        let bbsUserList = [];

        async function fetchSORUserList() {
            try {
                const res = await fetch('api.php?action=get_sor_users');
                const data = await res.json();
                if (data.success) sorUserList = data.users;
            } catch {}
        }
        async function fetchBBSUserList() {
            try {
                const res = await fetch('api.php?action=get_bbs_users');
                const data = await res.json();
                if (data.success) bbsUserList = data.users;
            } catch {}
        }
        function populateSORUserDropdown() {
            const select = document.getElementById('sor-trend-username');
            select.innerHTML = '<option value="">Select user</option>';
            sorUserList.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u;
                opt.textContent = u;
                select.appendChild(opt);
            });
        }
        function populateBBSUserDropdown() {
            const select = document.getElementById('bbs-trend-username');
            select.innerHTML = '<option value="">Select user</option>';
            bbsUserList.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u;
                opt.textContent = u;
                select.appendChild(opt);
            });
        }
        async function activateTrendTab(tab) {
            if (tab === 'sor-trend') {
                await fetchSORUserList();
                populateSORUserDropdown();
            } else if (tab === 'bbs-trend') {
                await fetchBBSUserList();
                populateBBSUserDropdown();
            }
        }
        // Patch switchTab to call activateTrendTab
        const origSwitchTab = window.switchTab;
        window.switchTab = function(tab) {
            origSwitchTab(tab);
            activateTrendTab(tab);
        }
        // Replace input with select in HTML (do this in the DOM for now)
        document.addEventListener('DOMContentLoaded', async function() {
            // Replace SOR input with select
            const sorInput = document.getElementById('sor-trend-username');
            if (sorInput && sorInput.tagName === 'INPUT') {
                const sel = document.createElement('select');
                sel.id = 'sor-trend-username';
                sel.className = sorInput.className;
                sorInput.parentNode.replaceChild(sel, sorInput);
            }
            // Replace BBS input with select
            const bbsInput = document.getElementById('bbs-trend-username');
            if (bbsInput && bbsInput.tagName === 'INPUT') {
                const sel = document.createElement('select');
                sel.id = 'bbs-trend-username';
                sel.className = bbsInput.className;
                bbsInput.parentNode.replaceChild(sel, bbsInput);
            }
            await activateTrendTab('sor-trend');
            await activateTrendTab('bbs-trend');
        });
        function addSORTrendUser() {
            const select = document.getElementById('sor-trend-username');
            const username = select.value;
            if (!username || sorTrendUsers.includes(username)) return;
            sorTrendUsers.push(username);
            renderSORTrendCards();
            select.value = '';
        }
        function addBBSTrendUser() {
            const select = document.getElementById('bbs-trend-username');
            const username = select.value;
            if (!username || bbsTrendUsers.includes(username)) return;
            bbsTrendUsers.push(username);
            renderBBSTrendCards();
            select.value = '';
        }
        function removeSORTrendUser(username) {
            sorTrendUsers = sorTrendUsers.filter(u => u !== username);
            renderSORTrendCards();
        }
        function removeBBSTrendUser(username) {
            bbsTrendUsers = bbsTrendUsers.filter(u => u !== username);
            renderBBSTrendCards();
        }
        async function renderSORTrendCards() {
            const container = document.getElementById('sor-trend-cards');
            container.innerHTML = '';
            const now = new Date();
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth(); // 0-based
            for (const username of sorTrendUsers) {
                const monthlyCardId = `sor-monthly-card-${username}`;
                const weeklyCardId = `sor-weekly-card-${username}`;
                const monthlyChartId = `sor-trend-chart-${username}`;
                const weeklyChartId = `sor-weekly-trend-chart-${username}`;
                const monthSelectId = `sor-trend-month-${username}`;
                // Month options
                let monthOptions = '';
                for (let m = 0; m < 12; m++) {
                    monthOptions += `<option value="${m}"${m === currentMonth ? ' selected' : ''}>${new Date(0, m).toLocaleString('default', { month: 'short' })}</option>`;
                }
                container.innerHTML += `
                    <!-- Monthly Trend Card -->
                    <div id="${monthlyCardId}" class="bg-white rounded shadow p-4 flex flex-col">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-blue-700">${username} - Monthly Trend</span>
                            <button onclick="removeSORTrendUser('${username}')" class="text-red-500 hover:underline">Remove</button>
                        </div>
                        <canvas id="${monthlyChartId}" height="180"></canvas>
                    </div>
                    <!-- Weekly Trend Card -->
                    <div id="${weeklyCardId}" class="bg-white rounded shadow p-4 flex flex-col">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-blue-700">${username} - Weekly Trend</span>
                            <div class="flex items-center gap-2">
                                <label for="${monthSelectId}" class="text-sm font-medium">Month:</label>
                                <select id="${monthSelectId}" class="border rounded px-2 py-1">
                                    ${monthOptions}
                                </select>
                            </div>
                        </div>
                        <canvas id="${weeklyChartId}" height="180"></canvas>
                    </div>
                `;
                // Fetch and render monthly chart
                const monthlyData = await fetchSORTrendData(username);
                renderTrendChart(monthlyChartId, monthlyData, 'SOR Submissions');
                // Fetch and render weekly chart for current month
                await renderSORWeeklyTrendChart(username, currentYear, currentMonth + 1, weeklyChartId);
                // Add event listener for month change
                setTimeout(() => {
                    const monthSelect = document.getElementById(monthSelectId);
                    if (monthSelect) {
                        monthSelect.addEventListener('change', async function() {
                            await renderSORWeeklyTrendChart(username, currentYear, parseInt(this.value) + 1, weeklyChartId);
                        });
                    }
                }, 0);
            }
        }
        // Helper to fetch and render weekly trend for a user/month
        async function renderSORWeeklyTrendChart(username, year, month, chartId) {
            try {
                const res = await fetch(`api.php?action=sor_weekly_trend&username=${encodeURIComponent(username)}&year=${year}&month=${month}`);
                const result = await res.json();
                if (result.success) {
                    renderTrendChart(chartId, result, 'Weekly SOR Submissions');
                } else {
                    renderTrendChart(chartId, { labels: [], data: [] }, 'Weekly SOR Submissions');
                }
            } catch {
                renderTrendChart(chartId, { labels: [], data: [] }, 'Weekly SOR Submissions');
            }
        }
        async function renderBBSTrendCards() {
            const container = document.getElementById('bbs-trend-cards');
            container.innerHTML = '';
            const now = new Date();
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth(); // 0-based
            for (const username of bbsTrendUsers) {
                const monthlyCardId = `bbs-monthly-card-${username}`;
                const weeklyCardId = `bbs-weekly-card-${username}`;
                const monthlyChartId = `bbs-trend-chart-${username}`;
                const weeklyChartId = `bbs-weekly-trend-chart-${username}`;
                const monthSelectId = `bbs-trend-month-${username}`;
                // Month options
                let monthOptions = '';
                for (let m = 0; m < 12; m++) {
                    monthOptions += `<option value="${m}"${m === currentMonth ? ' selected' : ''}>${new Date(0, m).toLocaleString('default', { month: 'short' })}</option>`;
                }
                container.innerHTML += `
                    <!-- Monthly Trend Card -->
                    <div id="${monthlyCardId}" class="bg-white rounded shadow p-4 flex flex-col">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-blue-700">${username} - Monthly Trend</span>
                            <button onclick="removeBBSTrendUser('${username}')" class="text-red-500 hover:underline">Remove</button>
                        </div>
                        <canvas id="${monthlyChartId}" height="180"></canvas>
                    </div>
                    <!-- Weekly Trend Card -->
                    <div id="${weeklyCardId}" class="bg-white rounded shadow p-4 flex flex-col">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-blue-700">${username} - Weekly Trend</span>
                            <div class="flex items-center gap-2">
                                <label for="${monthSelectId}" class="text-sm font-medium">Month:</label>
                                <select id="${monthSelectId}" class="border rounded px-2 py-1">
                                    ${monthOptions}
                                </select>
                            </div>
                        </div>
                        <canvas id="${weeklyChartId}" height="180"></canvas>
                    </div>
                `;
                // Fetch and render monthly chart
                const monthlyData = await fetchBBSTrendData(username);
                renderTrendChart(monthlyChartId, monthlyData, 'BBS Submissions');
                // Fetch and render weekly chart for current month
                await renderBBSWeeklyTrendChart(username, currentYear, currentMonth + 1, weeklyChartId);
                // Add event listener for month change
                setTimeout(() => {
                    const monthSelect = document.getElementById(monthSelectId);
                    if (monthSelect) {
                        monthSelect.addEventListener('change', async function() {
                            await renderBBSWeeklyTrendChart(username, currentYear, parseInt(this.value) + 1, weeklyChartId);
                        });
                    }
                }, 0);
            }
        }
        // Helper to fetch and render weekly trend for a user/month (BBS)
        async function renderBBSWeeklyTrendChart(username, year, month, chartId) {
            try {
                const res = await fetch(`api.php?action=bbs_weekly_trend&username=${encodeURIComponent(username)}&year=${year}&month=${month}`);
                const result = await res.json();
                if (result.success) {
                    renderTrendChart(chartId, result, 'Weekly BBS Submissions');
                } else {
                    renderTrendChart(chartId, { labels: [], data: [] }, 'Weekly BBS Submissions');
                }
            } catch {
                renderTrendChart(chartId, { labels: [], data: [] }, 'Weekly BBS Submissions');
            }
        }
        async function fetchSORTrendData(username) {
            // TODO: Implement API call
            // Return { labels: ["Jan",...], data: [count,...] }
            try {
                const res = await fetch(`api.php?action=sor_submission_trend&username=${encodeURIComponent(username)}`);
                const result = await res.json();
                if (result.success) return result;
            } catch {}
            return { labels: [], data: [] };
        }
        async function fetchBBSTrendData(username) {
            // TODO: Implement API call
            // Return { labels: ["Jan",...], data: [count,...] }
            try {
                const res = await fetch(`api.php?action=bbs_submission_trend&username=${encodeURIComponent(username)}`);
                const result = await res.json();
                if (result.success) return result;
            } catch {}
            return { labels: [], data: [] };
        }
        function renderTrendChart(chartId, trendData, label) {
            const existing = Chart.getChart(chartId);
            if (existing) existing.destroy();
            const ctx = document.getElementById(chartId).getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trendData.labels,
                    datasets: [{
                        label: label,
                        data: trendData.data,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#2563eb',
                        showLine: true,
                        spanGaps: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false }, tooltip: { enabled: true } },
                    elements: { point: { radius: 4 } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        }

        // New filtered renderers
        function renderSORTableFiltered() {
            renderSORTable(lastSORUsers, lastSORCompliance, lastSORLeadershipCompliance, lastSORDailyTarget);
        }
        function renderBBSTableFiltered() {
            renderBBSTable(lastBBSUsers, lastBBSCompliance, lastBBSObserverCompliance, lastBBSDailyTarget);
        }
        // Change event listeners to only re-render table, not reload data
        setTimeout(() => {
            const sorFilter = document.getElementById('sor-observer-filter');
            if (sorFilter) sorFilter.addEventListener('change', renderSORTableFiltered);
            const bbsFilter = document.getElementById('bbs-observer-filter');
            if (bbsFilter) bbsFilter.addEventListener('change', renderBBSTableFiltered);
        }, 0);

        // Update week range displays
        function updateWeekRangeDisplays() {
            // Use currentWeekStart and currentWeekEnd
            function formatDate(dateStr) {
                const d = new Date(dateStr);
                return d.toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' });
            }
            const sorWeek = document.getElementById('sor-week-range');
            const bbsWeek = document.getElementById('bbs-week-range');
            if (sorWeek) sorWeek.textContent = `Week: ${formatDate(currentWeekStart)} - ${formatDate(currentWeekEnd)}`;
            if (bbsWeek) bbsWeek.textContent = `Week: ${formatDate(currentWeekStart)} - ${formatDate(currentWeekEnd)}`;
        }

        // Add Observer Modal Logic
        const addBbsObserverBtn = document.getElementById('add-bbs-observer-btn');
        const addBbsObserverModal = document.getElementById('add-bbs-observer-modal');
        const cancelAddBbsObserver = document.getElementById('cancelAddBbsObserver');
        const addBbsObserverForm = document.getElementById('addBbsObserverForm');
        const bbsObserverSelect = document.getElementById('bbsObserverSelect');

        if (addBbsObserverBtn) {
            addBbsObserverBtn.onclick = async function() {
                bbsObserverSelect.innerHTML = '';
                // Fetch all users from the API
                try {
                    const res = await fetch('api.php?action=get_all_users');
                    const data = await res.json();
                    if (data.success && Array.isArray(data.users)) {
                        data.users.forEach(username => {
                            const opt = document.createElement('option');
                            opt.value = username;
                            opt.textContent = username;
                            bbsObserverSelect.appendChild(opt);
                        });
                    } else {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = 'No users available';
                        bbsObserverSelect.appendChild(opt);
                    }
                } catch (err) {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'Error loading users';
                    bbsObserverSelect.appendChild(opt);
                }
                addBbsObserverModal.classList.remove('hidden');
            };
        }
        if (cancelAddBbsObserver) {
            cancelAddBbsObserver.onclick = function() {
                addBbsObserverModal.classList.add('hidden');
            };
        }
        if (addBbsObserverForm) {
            addBbsObserverForm.onsubmit = async function(e) {
                e.preventDefault();
                const observerName = bbsObserverSelect.value.trim();
                if (!observerName) {
                    alert('Observer name is required');
                    return;
                }
                // Call API to add observer
                try {
                    const res = await fetch('api.php?action=add_bbs_observer', {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: `observer=${encodeURIComponent(observerName)}`
                    });
                    const data = await res.json();
                    if (data.success) {
                        addBbsObserverModal.classList.add('hidden');
                        loadBBSData();
                    } else {
                        alert(data.message || 'Failed to add observer');
                    }
                } catch (err) {
                    alert('Failed to add observer: ' + err.message);
                }
            };
        }
    </script>
</body>
</html> 