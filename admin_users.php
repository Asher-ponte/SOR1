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
    <title>Admin Dashboard - User Management</title>
    
    <!-- Core Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
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
        
        .table-container {
            overflow-x: auto;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-height: 600px; /* Show up to 10 rows before scrolling */
            overflow-y: auto;
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
        </div>
        <button onclick="window.location.href='index.html'" class="sidebar-icon text-blue-500" title="Main App">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
        </button>
    </nav>

    <!-- Main Content -->
    <div class="min-h-screen bg-gray-50 p-2">
        <main class="flex-1 p-4 overflow-y-auto min-h-screen">
            <div class="card">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <a href="#" id="user-tab" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600">User Management</a>
                        <a href="#" id="department-tab" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Department Management</a>
                    </nav>
                </div>

                <div id="user-management-content">
                    <div class="flex justify-between items-center mb-4 mt-4">
                        <h3 class="text-lg font-medium text-gray-800">User Management</h3>
                        <button id="create-user-btn" class="btn-primary">Create User</button>
                    </div>
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="user-table-body" class="bg-white divide-y divide-gray-200">
                                <!-- User rows will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="department-management-content" class="hidden">
                    <div class="flex justify-between items-center mb-4 mt-4">
                        <h3 class="text-lg font-medium text-gray-800">Department Management</h3>
                        <button id="create-department-btn" class="btn-primary">Create Department</button>
                    </div>
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department Name</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="department-table-body" class="bg-white divide-y divide-gray-200">
                                <!-- Department rows will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit User Modal -->
    <div id="user-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
            <div class="bg-white rounded-xl p-6">
                <h3 id="user-modal-title" class="text-xl font-bold text-gray-900 mb-4"></h3>
                <form id="user-form" class="space-y-4">
                    <input type="hidden" id="user-id">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" id="username" class="input-field w-full mt-1">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password (leave blank to keep current password)</label>
                        <input type="password" id="password" class="input-field w-full mt-1">
                    </div>
                    <div>
                        <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                        <select id="department" class="input-field w-full mt-1">
                            <!-- Department options will be populated by JavaScript -->
                        </select>
                    </div>
                    <div class="flex justify-end space-x-4">
                        <button type="submit" class="btn-primary">Save</button>
                        <button type="button" id="cancel-btn" class="btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Confirmation Modal -->
    <div id="delete-user-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
            <div class="bg-white rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">Confirm Deletion</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to delete this user?</p>
                <div class="flex justify-end space-x-4">
                    <button id="confirm-delete-user-btn" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">Delete</button>
                    <button id="cancel-delete-user-btn" class="btn-secondary">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Department Modal -->
    <div id="department-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
            <div class="bg-white rounded-xl p-6">
                <h3 id="department-modal-title" class="text-xl font-bold text-gray-900 mb-4"></h3>
                <form id="department-form" class="space-y-4">
                    <input type="hidden" id="department-id">
                    <div>
                        <label for="department-name" class="block text-sm font-medium text-gray-700">Department Name</label>
                        <input type="text" id="department-name" class="input-field w-full mt-1">
                    </div>
                    <div class="flex justify-end space-x-4">
                        <button type="submit" class="btn-primary">Save</button>
                        <button type="button" id="cancel-department-btn" class="btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Department Confirmation Modal -->
    <div id="delete-department-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
            <div class="bg-white rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">Confirm Deletion</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to delete this department?</p>
                <div class="flex justify-end space-x-4">
                    <button id="confirm-delete-department-btn" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">Delete</button>
                    <button id="cancel-delete-department-btn" class="btn-secondary">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allDepartments = [];

        document.addEventListener('DOMContentLoaded', async () => {
            await loadUsersAndDepartments();

            const userTab = document.getElementById('user-tab');
            const departmentTab = document.getElementById('department-tab');
            const userContent = document.getElementById('user-management-content');
            const departmentContent = document.getElementById('department-management-content');

            userTab.addEventListener('click', (e) => {
                e.preventDefault();
                userTab.classList.add('border-indigo-500', 'text-indigo-600');
                userTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                departmentTab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                departmentTab.classList.remove('border-indigo-500', 'text-indigo-600');
                userContent.classList.remove('hidden');
                departmentContent.classList.add('hidden');
            });

            departmentTab.addEventListener('click', (e) => {
                e.preventDefault();
                departmentTab.classList.add('border-indigo-500', 'text-indigo-600');
                departmentTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                userTab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                userTab.classList.remove('border-indigo-500', 'text-indigo-600');
                departmentContent.classList.remove('hidden');
                userContent.classList.add('hidden');
            });

            document.getElementById('create-user-btn').addEventListener('click', openCreateUserModal);
            document.getElementById('cancel-btn').addEventListener('click', closeUserModal);
            document.getElementById('user-form').addEventListener('submit', saveUser);
            document.getElementById('cancel-delete-user-btn').addEventListener('click', closeDeleteUserModal);

            document.getElementById('create-department-btn').addEventListener('click', openCreateDepartmentModal);
            document.getElementById('cancel-department-btn').addEventListener('click', closeDepartmentModal);
            document.getElementById('department-form').addEventListener('submit', saveDepartment);
            document.getElementById('cancel-delete-department-btn').addEventListener('click', closeDeleteDepartmentModal);
        });

        async function loadUsersAndDepartments() {
            try {
                const [usersResponse, departmentsResponse] = await Promise.all([
                    fetch('api.php?action=get_all_users', { credentials: 'include' }),
                    fetch('get_departments.php', { credentials: 'include' })
                ]);

                const usersResult = await usersResponse.json();
                allDepartments = await departmentsResponse.json();

                if (usersResult.success) {
                    populateUserTable(usersResult.users, allDepartments);
                } else {
                    console.error('Failed to load users:', usersResult.message);
                }
                populateDepartmentTable(allDepartments);
            } catch (error) {
                console.error('Error loading data:', error);
            }
        }

        function populateUserTable(users, departments) {
            const tableBody = document.getElementById('user-table-body');
            tableBody.innerHTML = ''; // Clear existing rows

            users.forEach(user => {
                const row = document.createElement('tr');

                const usernameCell = document.createElement('td');
                usernameCell.className = 'px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900';
                usernameCell.textContent = user.username;
                row.appendChild(usernameCell);

                const departmentCell = document.createElement('td');
                departmentCell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-500';
                const department = departments.find(d => d.id == user.department_id);
                departmentCell.textContent = department ? department.name : 'N/A';
                row.appendChild(departmentCell);

                const actionsCell = document.createElement('td');
                actionsCell.className = 'px-6 py-4 whitespace-nowrap text-right text-sm font-medium';
                
                const editButton = document.createElement('button');
                editButton.className = 'text-indigo-600 hover:text-indigo-900 mr-4';
                editButton.textContent = 'Edit';
                editButton.onclick = () => openEditUserModal(user);
                actionsCell.appendChild(editButton);

                const deleteButton = document.createElement('button');
                deleteButton.className = 'text-red-600 hover:text-red-900';
                deleteButton.textContent = 'Delete';
                deleteButton.onclick = () => openDeleteModal(user.id);
                actionsCell.appendChild(deleteButton);

                row.appendChild(actionsCell);

                tableBody.appendChild(row);
            });
        }

        function populateDepartmentTable(departments) {
            const tableBody = document.getElementById('department-table-body');
            tableBody.innerHTML = ''; // Clear existing rows

            departments.forEach(department => {
                const row = document.createElement('tr');

                const nameCell = document.createElement('td');
                nameCell.className = 'px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900';
                nameCell.textContent = department.name;
                row.appendChild(nameCell);

                const actionsCell = document.createElement('td');
                actionsCell.className = 'px-6 py-4 whitespace-nowrap text-right text-sm font-medium';
                
                const editButton = document.createElement('button');
                editButton.className = 'text-indigo-600 hover:text-indigo-900 mr-4';
                editButton.textContent = 'Edit';
                editButton.onclick = () => openEditDepartmentModal(department);
                actionsCell.appendChild(editButton);

                const deleteButton = document.createElement('button');
                deleteButton.className = 'text-red-600 hover:text-red-900';
                deleteButton.textContent = 'Delete';
                deleteButton.onclick = () => openDeleteDepartmentModal(department.id);
                actionsCell.appendChild(deleteButton);

                row.appendChild(actionsCell);

                tableBody.appendChild(row);
            });
        }

        function openCreateUserModal() {
            document.getElementById('user-modal-title').textContent = 'Create User';
            document.getElementById('user-id').value = '';
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
            populateDepartmentDropdown();
            document.getElementById('user-modal').classList.remove('hidden');
        }

        function openEditUserModal(user) {
            document.getElementById('user-modal-title').textContent = 'Edit User';
            document.getElementById('user-id').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('password').value = '';
            populateDepartmentDropdown(user.department_id);
            document.getElementById('user-modal').classList.remove('hidden');
        }

        function closeUserModal() {
            document.getElementById('user-modal').classList.add('hidden');
        }

        function populateDepartmentDropdown(selectedDepartmentId = null) {
            const select = document.getElementById('department');
            select.innerHTML = '';
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select Department';
            select.appendChild(defaultOption);

            allDepartments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                if (selectedDepartmentId && dept.id == selectedDepartmentId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }

        async function saveUser(event) {
            event.preventDefault();
            const userId = document.getElementById('user-id').value;
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const departmentId = document.getElementById('department').value;

            const action = userId ? 'edit_user' : 'add_user';
            const formData = new FormData();
            formData.append('action', action);
            if (userId) {
                formData.append('id', userId);
            }
            formData.append('username', username);
            if (password) {
                formData.append('password', password);
            }
            formData.append('department_id', departmentId);

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });
                const result = await response.json();
                if (result.success) {
                    closeUserModal();
                    loadUsersAndDepartments();
                } else {
                    alert('Failed to save user: ' + result.message);
                }
            } catch (error) {
                console.error('Error saving user:', error);
                alert('An error occurred while saving the user.');
            }
        }

        function openDeleteUserModal(userId) {
            document.getElementById('delete-user-modal').classList.remove('hidden');
            document.getElementById('confirm-delete-user-btn').onclick = () => deleteUser(userId);
        }

        function closeDeleteUserModal() {
            document.getElementById('delete-user-modal').classList.add('hidden');
        }

        async function deleteUser(userId) {
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('id', userId);

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });
                const result = await response.json();
                if (result.success) {
                    closeDeleteUserModal();
                    loadUsersAndDepartments();
                } else {
                    alert('Failed to delete user: ' + result.message);
                }
            } catch (error) {
                console.error('Error deleting user:', error);
                alert('An error occurred while deleting the user.');
            }
        }

        function openCreateDepartmentModal() {
            document.getElementById('department-modal-title').textContent = 'Create Department';
            document.getElementById('department-id').value = '';
            document.getElementById('department-name').value = '';
            document.getElementById('department-modal').classList.remove('hidden');
        }

        function openEditDepartmentModal(department) {
            document.getElementById('department-modal-title').textContent = 'Edit Department';
            document.getElementById('department-id').value = department.id;
            document.getElementById('department-name').value = department.name;
            document.getElementById('department-modal').classList.remove('hidden');
        }

        function closeDepartmentModal() {
            document.getElementById('department-modal').classList.add('hidden');
        }

        async function saveDepartment(event) {
            event.preventDefault();
            const departmentId = document.getElementById('department-id').value;
            const departmentName = document.getElementById('department-name').value;

            const action = departmentId ? 'edit_department' : 'add_department';
            const formData = new FormData();
            formData.append('action', action);
            if (departmentId) {
                formData.append('id', departmentId);
            }
            formData.append('name', departmentName);

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });
                const result = await response.json();
                if (result.success) {
                    closeDepartmentModal();
                    loadUsersAndDepartments();
                } else {
                    alert('Failed to save department: ' + result.message);
                }
            } catch (error) {
                console.error('Error saving department:', error);
                alert('An error occurred while saving the department.');
            }
        }

        function openDeleteDepartmentModal(departmentId) {
            document.getElementById('delete-department-modal').classList.remove('hidden');
            document.getElementById('confirm-delete-department-btn').onclick = () => deleteDepartment(departmentId);
        }

        function closeDeleteDepartmentModal() {
            document.getElementById('delete-department-modal').classList.add('hidden');
        }

        async function deleteDepartment(departmentId) {
            const formData = new FormData();
            formData.append('action', 'delete_department');
            formData.append('id', departmentId);

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });
                const result = await response.json();
                if (result.success) {
                    closeDeleteDepartmentModal();
                    loadUsersAndDepartments();
                } else {
                    alert('Failed to delete department: ' + result.message);
                }
            } catch (error) {
                console.error('Error deleting department:', error);
                alert('An error occurred while deleting the department.');
            }
        }
    </script>

</body>
</html>
