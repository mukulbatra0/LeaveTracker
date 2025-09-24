<?php
// Database initialization script

// Display all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'db.php';

// Function to execute SQL from a file
function executeSQLFile($conn, $filename) {
    try {
        // Read the SQL file
        $sql = file_get_contents($filename);
        
        // Execute the SQL commands
        $conn->exec($sql);
        
        echo "<div class='alert alert-success'>SQL file executed successfully: $filename</div>";
        return true;
    } catch(PDOException $e) {
        echo "<div class='alert alert-danger'>Error executing SQL file: " . $e->getMessage() . "</div>";
        return false;
    }
}

// Function to create admin user
function createAdminUser($conn, $employee_id, $first_name, $last_name, $email, $password, $department_id) {
    try {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Prepare SQL statement
        $sql = "INSERT INTO users (employee_id, first_name, last_name, email, password, role, department_id, position, status) 
                VALUES (:employee_id, :first_name, :last_name, :email, :password, 'hr_admin', :department_id, 'HR Administrator', 'active')";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':department_id', $department_id);
        
        // Execute statement
        $stmt->execute();
        
        echo "<div class='alert alert-success'>Admin user created successfully!</div>";
        return true;
    } catch(PDOException $e) {
        echo "<div class='alert alert-danger'>Error creating admin user: " . $e->getMessage() . "</div>";
        return false;
    }
}

// Function to create default department
function createDefaultDepartment($conn) {
    try {
        // Check if HR department already exists
        $stmt = $conn->prepare("SELECT id FROM departments WHERE code = 'HR';");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            echo "<div class='alert alert-info'>HR department already exists.</div>";
            return $row['id'];
        }
        
        // Create HR department
        $sql = "INSERT INTO departments (name, code, description) VALUES ('Human Resources', 'HR', 'Human Resources Department')";
        $conn->exec($sql);
        
        $department_id = $conn->lastInsertId();
        
        echo "<div class='alert alert-success'>Default HR department created successfully!</div>";
        return $department_id;
    } catch(PDOException $e) {
        echo "<div class='alert alert-danger'>Error creating default department: " . $e->getMessage() . "</div>";
        return false;
    }
}

// Process form submission
$success = false;
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Check if database exists, if not create it
        $pdo = new PDO("mysql:host=" . DB_SERVER, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        
        echo "<div class='alert alert-success'>Database created or already exists.</div>";
        
        // Execute SQL file
        $sql_file = __DIR__ . '/elms_db.sql';
        if (file_exists($sql_file)) {
            executeSQLFile($conn, $sql_file);
            
            // Create default department
            $department_id = createDefaultDepartment($conn);
            
            if ($department_id) {
                // Create admin user
                $employee_id = $_POST['employee_id'];
                $first_name = $_POST['first_name'];
                $last_name = $_POST['last_name'];
                $email = $_POST['email'];
                $password = $_POST['password'];
                
                if (createAdminUser($conn, $employee_id, $first_name, $last_name, $email, $password, $department_id)) {
                    $success = true;
                }
            }
        } else {
            $error = "SQL file not found: $sql_file";
        }
    } catch(PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialize Database - ELMS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .setup-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .setup-header {
            text-align: center;
            padding: 20px 0;
        }
        .setup-logo {
            font-size: 2.5rem;
            color: #0d6efd;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="card setup-card">
            <div class="card-body">
                <div class="setup-header">
                    <div class="setup-logo">
                        <i class="fas fa-database"></i>
                    </div>
                    <h2>ELMS Database Setup</h2>
                    <p class="text-muted">Initialize the database for Employee Leave Management System</p>
                </div>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">Setup Complete!</h4>
                        <p>The database has been initialized successfully. You can now <a href="/login.php" class="alert-link">login</a> using the admin credentials you provided.</p>
                    </div>
                <?php elseif(!empty($error)): ?>
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">Setup Failed!</h4>
                        <p><?php echo $error; ?></p>
                    </div>
                <?php else: ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> This script will initialize the database and create an admin user.
                        </div>
                        
                        <h4 class="mb-3">Admin User Information</h4>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="employee_id" class="form-label">Employee ID</label>
                                <input type="text" name="employee_id" id="employee_id" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" name="first_name" id="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" name="last_name" id="last_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Initialize Database</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-3 text-muted">
            <small>&copy; <?php echo date('Y'); ?> ELMS - Employee Leave Management System</small>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>