<?php
// login.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Accounting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h2 {
            color: #333;
            font-weight: 600;
        }
        .login-header p {
            color: #666;
        }
        .form-control:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 0.25rem rgba(118, 75, 162, 0.25);
        }
        .btn-login {
            background:blue;
            border: none;
            color: white;
            padding: 10px 0;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4);
        }
        .alert {
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h2><b>System Login</b></h2>
            <p class="text-muted">Accounting & Loan Management System</p>
        </div>
        
        <form id="loginForm">
            <div class="mb-3">
                
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" placeholder="Enter username" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                    <input type="password" class="form-control" id="password" placeholder="Enter password" required>
                </div>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="rememberMe">
                <label class="form-check-label" for="rememberMe">Remember me</label>
            </div>
            
            <div class="alert alert-danger" id="errorAlert" role="alert">
                <i class="bi bi-exclamation-triangle"></i> Invalid username or password!
            </div>
            
            <button type="submit" class="btn btn-login w-100 mb-3">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </button>
            
        </form>
    </div>

    <script>
        // Hardcoded credentials (you can change these)
        const VALID_CREDENTIALS = {
            'admin@cbfinance.rw': 'newconfig@#2026',
            'user': 'password123',
            'accountant': 'account123'
        };

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('rememberMe').checked;
            
            if (VALID_CREDENTIALS[username] && VALID_CREDENTIALS[username] === password) {
                // Create session
                const sessionData = {
                    username: username,
                    loggedIn: true,
                    timestamp: new Date().getTime(),
                    role: username === 'admin' ? 'Administrator' : 
                          username === 'accountant' ? 'Accountant' : 'User'
                };
                
                // Store in localStorage
                localStorage.setItem('authSession', JSON.stringify(sessionData));
                
                // Set expiry if remember me is checked (7 days), otherwise session only
                if (rememberMe) {
                    localStorage.setItem('authExpiry', (new Date().getTime() + (7 * 24 * 60 * 60 * 1000)).toString());
                } else {
                    // Session expires when browser closes
                    localStorage.setItem('authExpiry', 'session');
                }
                
                // Redirect to main page
                window.location.href = 'index.php';
            } else {
                // Show error
                document.getElementById('errorAlert').style.display = 'block';
                
                // Shake animation for error
                const form = document.getElementById('loginForm');
                form.classList.add('shake');
                setTimeout(() => form.classList.remove('shake'), 400);
            }
        });

        // Check if already logged in
        const authSession = localStorage.getItem('authSession');
        if (authSession) {
            const session = JSON.parse(authSession);
            const expiry = localStorage.getItem('authExpiry');
            
            if (expiry !== 'session' && expiry && new Date().getTime() > parseInt(expiry)) {
                // Session expired
                localStorage.removeItem('authSession');
                localStorage.removeItem('authExpiry');
            } else if (session.loggedIn) {
                // Already logged in, redirect to main page
                window.location.href = 'index.php';
            }
        }
    </script>
</body>
</html>