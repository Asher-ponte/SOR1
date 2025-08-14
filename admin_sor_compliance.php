<?php
session_start();
// Enhanced session validation
function validateAdminSession() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: index.html');
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        session_unset();
        session_destroy();
        header('Location: index.html');
        exit;
    }
    $_SESSION['last_activity'] = time();
}
validateAdminSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOR Compliance Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <nav class="w-full bg-white shadow-lg flex items-center justify-between px-8 py-3">
        <div class="flex items-center space-x-4">
            <a href="admin.php" class="sidebar-icon text-blue-700 hover:text-orange-600" title="Dashboard">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </a>
            <span class="font-bold text-lg text-cyan-700">SOR Compliance Tracker</span>
        </div>
    </nav>
    <div class="max-w-7xl mx-auto p-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-4">
            <div>
                <label class="font-medium text-gray-700">Select Week:</label>
                <input id="weekPicker" class="input-field border rounded px-2 py-1" placeholder="Pick a week" readonly>
            </div>
            <div>
                <label class="font-medium text-gray-700">Filter by User:</label>
                <select id="userFilter" class="input-field border rounded px-2 py-1">
                    <option value="all">All Users</option>
                </select>
            </div>
            <button id="addUserBtn" class="btn-primary bg-cyan-600 text-white px-4 py-2 rounded">Add User</button>
        </div>
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
    <!-- User Edit Modal (hidden by default) -->
    <div id="userModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4" id="userModalTitle">Edit User</h3>
            <form id="userForm" class="space-y-4">
                <input type="hidden" id="userId">
                <div>
                    <label class="block text-gray-700">Username</label>
                    <input type="text" id="usernameInput" class="input-field w-full" required>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeUserModal()" class="btn-secondary px-4 py-2 rounded">Cancel</button>
                    <button type="submit" class="btn-primary bg-cyan-600 text-white px-4 py-2 rounded">Save</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    // Flatpickr week picker
    flatpickr("#weekPicker", {
        weekNumbers: true,
        dateFormat: "Y-m-d",
        defaultDate: new Date(),
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length > 0) {
                const monday = getMonday(selectedDates[0]);
                document.getElementById('weekPicker').value = monday.toISOString().slice(0,10);
                loadSORCompliance(monday);
            }
        },
        onReady: function(selectedDates, dateStr, instance) {
            // Set initial date to current week's Monday
            const monday = getMonday(new Date());
            instance.setDate(monday);
        }
    });
    function getMonday(d) {
        d = new Date(d);
        const day = d.getDay();
        // If Sunday (0), subtract 6 days; else subtract (day-1)
        if (day === 0) {
            d.setDate(d.getDate() - 6);
        } else {
            d.setDate(d.getDate() - (day - 1));
        }
        d.setHours(0,0,0,0);
        return d;
    }
    // Set week dates in header with validation
    function setWeekDates(monday) {
        // Validate that we have a Monday
        if (monday.getDay() !== 1) {
            console.warn('setWeekDates: Input is not a Monday, correcting...');
            monday = getMonday(monday);
        }
        
        const days = [0,1,2,3,4,5];
        const ids = ["date-mon","date-tue","date-wed","date-thu","date-fri","date-sat"];
        const dayNames = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        
        for (let i = 0; i < 6; i++) {
            const date = new Date(monday);
            date.setDate(date.getDate() + i);
            const dateElement = document.getElementById(ids[i]);
            if (dateElement) {
                dateElement.textContent = date.toISOString().slice(5,10);
                dateElement.title = `${dayNames[i]} ${date.toISOString().slice(0,10)}`;
            }
        }
        
        // Update week picker display to show the week range
        const saturday = new Date(monday);
        saturday.setDate(saturday.getDate() + 5);
        const weekRange = `${monday.toISOString().slice(0,10)} to ${saturday.toISOString().slice(0,10)}`;
        document.getElementById('weekPicker').title = `Week: ${weekRange}`;
    }
    // Fetch and render SOR Compliance data
    async function loadSORCompliance(monday) {
        // Validate input
        if (!monday || !(monday instanceof Date)) {
            console.error('loadSORCompliance: Invalid date input');
            return;
        }
        
        setWeekDates(monday);
        const weekStart = monday.toISOString().slice(0,10);
        
        try {
            const res = await fetch(`api.php?action=get_sor_compliance_tracker&week_start=${weekStart}`, {credentials:'include'});
            const data = await res.json();
            if (!data.success) { 
                console.error('SOR Compliance API error:', data.message);
                alert(data.message || 'Failed to load data'); 
                return; 
            }
            renderSORTable(data.users, data.compliance, data.leadership_compliance, data.daily_target);
        } catch (err) {
            console.error('Failed to load SOR compliance data:', err);
            alert('Failed to load SOR compliance data: ' + err.message);
        }
    }
    function renderSORTable(users, compliance, leadershipCompliance, dailyTarget) {
        const userFilter = document.getElementById('userFilter');
        if (userFilter) {
            // Populate user filter dropdown
            userFilter.innerHTML = '<option value="all">All Users</option>';
            users.forEach(user => {
                const opt = document.createElement('option');
                opt.value = user.username;
                opt.textContent = user.username;
                userFilter.appendChild(opt);
            });
        }
        // Filter users if filter is set
        let filteredUsers = users;
        if (userFilter && userFilter.value !== 'all') {
            filteredUsers = users.filter(u => u.username === userFilter.value);
        }
        const tbody = document.getElementById('sor-table-body');
        tbody.innerHTML = '';
        let totalCompliance = 0;
        let observerCount = 0;
        filteredUsers.forEach((user, idx) => {
            const c = compliance[user.username];
            const days = c.days || Array(6).fill({ hit: false, count: 0 });
            const compliancePercent = c.compliance || 0;
            const daysMetTarget = days.filter(day => day.hit).length;
            const totalSubmissions = days.reduce((sum, day) => sum + day.count, 0);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="p-2">${idx + 1}</td>
                <td class="p-2 font-medium">${user.username}</td>
                ${days.map(day => `<td class="p-2 text-center ${day.hit ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${day.count}</td>`).join('')}
                <td class="p-2 text-center font-medium">${daysMetTarget}/6</td>
                <td class="p-2 text-center">${totalSubmissions}</td>
                <td class="p-2 text-center ${compliancePercent >= 80 ? 'text-green-600' : 'text-red-600'}">${compliancePercent}%</td>
                <td class="p-2">
                    <button onclick="editUser('${user.id}','${user.username}')" class="text-blue-600 hover:underline">Edit</button>
                    <button onclick="deleteUser('${user.id}')" class="text-red-600 hover:underline ml-2">Delete</button>
                </td>
            `;
            tbody.appendChild(tr);
            totalCompliance += compliancePercent;
            observerCount++;
        });
        // After the loop, update average compliance
        const avgCompliance = observerCount > 0 ? Math.round(totalCompliance / observerCount) : 0;
        document.getElementById('sor-average-compliance').textContent = avgCompliance + '%';
        document.getElementById('sor-average-compliance').className = avgCompliance >= 80 ? 'text-green-600 font-bold' : 'text-red-600 font-bold';
        document.getElementById('leadership-compliance').textContent = leadershipCompliance + '%';
        document.getElementById('leadership-compliance').className = leadershipCompliance >= 80 ? 'text-green-600' : 'text-red-500';
        // Show daily target in the UI
        let targetDiv = document.getElementById('daily-target-info');
        if (!targetDiv) {
            targetDiv = document.createElement('div');
            targetDiv.id = 'daily-target-info';
            targetDiv.className = 'text-center text-sm text-gray-600 my-2';
            const parent = document.querySelector('.max-w-7xl');
            if (parent) parent.prepend(targetDiv);
        }
        targetDiv.textContent = `Daily Target: ${dailyTarget} SORs per day (cells green if met)`;
    }
    // User modal logic
    function editUser(id, username) {
        document.getElementById('userId').value = id;
        document.getElementById('usernameInput').value = username;
        document.getElementById('userModalTitle').textContent = 'Edit User';
        document.getElementById('userModal').classList.remove('hidden');
    }
    function closeUserModal() {
        document.getElementById('userModal').classList.add('hidden');
    }
    document.getElementById('userForm').onsubmit = async function(e) {
        e.preventDefault();
        const id = document.getElementById('userId').value;
        const username = document.getElementById('usernameInput').value;
        if (!username) {
            alert('Username is required');
            return;
        }
        let url, body;
        if (id) {
            url = 'api.php?action=edit_user';
            body = `id=${encodeURIComponent(id)}&username=${encodeURIComponent(username)}`;
        } else {
            url = 'api.php?action=add_user';
            body = `username=${encodeURIComponent(username)}`;
        }
        const res = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: body,
            credentials: 'include'
        });
        const data = await res.json();
        if (data.success) {
            closeUserModal();
            document.querySelector('#weekPicker')._flatpickr.setDate(new Date());
        } else {
            alert(data.message || 'Failed to save user');
        }
    };
    async function deleteUser(id) {
        if (!confirm('Delete this user?')) return;
        const res = await fetch('api.php?action=delete_user', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `id=${encodeURIComponent(id)}`,
            credentials: 'include'
        });
        const data = await res.json();
        if (data.success) {
            document.querySelector('#weekPicker')._flatpickr.setDate(new Date());
        } else {
            alert(data.message || 'Failed to delete user');
        }
    }
    document.getElementById('addUserBtn').onclick = function() {
        document.getElementById('userId').value = '';
        document.getElementById('usernameInput').value = '';
        document.getElementById('userModalTitle').textContent = 'Add User';
        document.getElementById('userModal').classList.remove('hidden');
    };
    // Initial load
    document.addEventListener('DOMContentLoaded', function() {
        const monday = getMonday(new Date());
        document.getElementById('weekPicker').value = monday.toISOString().slice(0,10);
        loadSORCompliance(monday);
    });
    // Add global variable for chart instance
    let sorSubmissionChartInstance = null;
    </script>
</body>
</html> 