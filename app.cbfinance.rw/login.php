<?php
// login.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Capital Bridge Finance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --accent: #10b981;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
            background: #0f172a;
        }
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('../images/homepagepic.jpg') no-repeat center center;
            background-size: cover;
            filter: blur(8px) brightness(0.4);
            z-index: -1;
            transform: scale(1.1);
        }
        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 20px;
            z-index: 10;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            padding: 3rem 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .login-header h2 {
            color: #1e293b;
            font-weight: 800;
            font-size: 1.75rem;
            letter-spacing: -0.025em;
        }
        .login-header p {
            color: #64748b;
            font-size: 0.95rem;
        }
        .form-label {
            font-weight: 600;
            color: #475569;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        .input-group {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.2s;
        }
        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            background: white;
        }
        .input-group-text {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding-left: 1.25rem;
        }
        .form-control {
            background: transparent;
            border: none;
            padding: 0.875rem 1.25rem;
            font-weight: 500;
            color: #1e293b;
        }
        .form-control:focus {
            background: transparent;
            box-shadow: none;
            border: none;
        }
        .btn-login {
            background: var(--primary);
            border: none;
            color: white;
            padding: 1rem;
            border-radius: 1rem;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s;
            margin-top: 1rem;
        }
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.4);
        }
        .alert {
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: none;
            border: none;
            background: #fef2f2;
            color: #991b1b;
        }
        .back-to-web {
            display: block;
            text-align: center;
            margin-top: 2rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            opacity: 0.8;
        }
        .back-to-web:hover {
            opacity: 1;
            color: var(--accent);
        }
        .shake {
            animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both;
        }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
    </style>
</head>
<body>
    <div class="bg-overlay"></div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="mb-3">
                    <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
                </div>
                <h2>Portal Login</h2>
                <p>Accounting & Loan Management</p>
            </div>
            
            <form id="loginForm">
                <div class="mb-4">
                    <label for="username" class="form-label uppercase tracking-wider">Username / Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="text" class="form-control" id="username" placeholder="admin@cbfinance.rw" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label uppercase tracking-wider">Security Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                        <input type="password" class="form-control" id="password" placeholder="••••••••" required>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe">
                        <label class="form-check-label small fw-bold text-muted" for="rememberMe">Keep me logged in</label>
                    </div>
                </div>
                
                <div class="alert mb-4" id="errorAlert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i> Access denied. Please check your credentials.
                </div>
                
                <button type="submit" class="btn btn-login w-100">
                    Sign In to Dashboard <i class="bi bi-arrow-right-short ms-1"></i>
                </button>
            </form>
        </div>
        
        <a href="../index.php" class="back-to-web">
            <i class="bi bi-chevron-left me-1"></i> Back to main website
        </a>
    </div>

    <script>
        const VALID_CREDENTIALS = {
            'admin@cbfinance.rw': 'newconfig@#2026'
        };

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('rememberMe').checked;
            
            if (VALID_CREDENTIALS[username] && VALID_CREDENTIALS[username] === password) {
                const sessionData = {
                    username: username,
                    loggedIn: true,
                    timestamp: new Date().getTime(),
                    role: 'Administrator'
                };
                
                localStorage.setItem('authSession', JSON.stringify(sessionData));
                
                if (rememberMe) {
                    localStorage.setItem('authExpiry', (new Date().getTime() + (7 * 24 * 60 * 60 * 1000)).toString());
                } else {
                    localStorage.setItem('authExpiry', 'session');
                }
                
                window.location.href = 'index.php';
            } else {
                const errorAlert = document.getElementById('errorAlert');
                errorAlert.style.display = 'block';
                const card = document.querySelector('.login-card');
                card.classList.add('shake');
                setTimeout(() => card.classList.remove('shake'), 400);
            }
        });

        // Check login state
        const authSession = localStorage.getItem('authSession');
        if (authSession) {
            const session = JSON.parse(authSession);
            const expiry = localStorage.getItem('authExpiry');
            if (!(expiry !== 'session' && expiry && new Date().getTime() > parseInt(expiry)) && session.loggedIn) {
                window.location.href = 'index.php';
            }
        }
    </script>
</body>
</html>