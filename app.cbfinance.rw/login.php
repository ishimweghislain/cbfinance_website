<?php
// login.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity Hub | Capital Bridge Finance</title>
    <link rel="icon" type="image/png" href="../images/CAPITAL BRIDGE LOGO.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --accent: #10b981;
        }
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow: hidden;
            background: #fff;
        }
        .login-wrapper {
            display: flex;
            height: 100vh;
            width: 100%;
        }
        .login-side-image {
            flex: 1.2;
            background: url('https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&q=80&w=2000') no-repeat center center;
            background-size: cover;
            position: relative;
            display: none; 
        }
        @media (min-width: 992px) {
            .login-side-image { display: block; }
        }
        .login-side-image::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, rgba(30, 64, 175, 0.4), rgba(30, 64, 175, 0.1));
        }
        .login-side-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            background: #fff;
            position: relative;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        .logo-box {
            width: 50px;
            height: 50px;
            background: var(--primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 10px 20px rgba(30, 64, 175, 0.2);
        }
        .login-header h2 {
            font-weight: 800;
            color: #111827;
            font-size: 1.6rem;
            margin-bottom: 4px;
            letter-spacing: -0.025em;
        }
        .login-header p {
            color: #6b7280;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .form-label {
            font-weight: 700;
            color: #374151;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        .input-group {
            border: 2px solid #f3f4f6;
            border-radius: 14px;
            transition: all 0.2s;
            background: #f9fafb;
            overflow: hidden;
        }
        .input-group:focus-within {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.05);
        }
        .input-group-text {
            background: transparent;
            border: none;
            color: #9ca3af;
            padding-left: 16px;
        }
        .form-control {
            border: none;
            padding: 12px 16px;
            font-weight: 600;
            font-size: 0.95rem;
            background: transparent;
        }
        .form-control:focus {
            box-shadow: none;
            background: transparent;
        }
        .btn-login {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 14px;
            font-weight: 700;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.2);
        }
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.3);
        }
        .back-link {
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            color: #6b7280;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .back-link:hover {
            color: var(--primary);
        }
        .back-link i {
            margin-right: 8px;
            transition: transform 0.2s;
        }
        .back-link:hover i {
            transform: translateX(-4px);
        }
        .alert {
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 12px;
            padding: 12px;
            border: none;
            background: #fee2e2;
            color: #991b1b;
            display: none;
            margin-top: 20px;
        }
        .shake { animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-side-image"></div>
        <div class="login-side-content">
            <div class="login-container">
                <div class="logo-box">
                    <i class="bi bi-shield-lock-fill text-white fs-3"></i>
                </div>
                <div class="login-header">
                    <h2>Welcome back</h2>
                    <p>Enter your details to access the portal</p>
                </div>
                
                <form id="loginForm">
                    <div class="mb-3">
                        <label class="form-label">Username / Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" class="form-control" id="username" placeholder="Input your username" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" class="form-control" id="password" placeholder="I hope you remember your password !" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe">
                            <label class="form-check-label small fw-bold text-muted" for="rememberMe">Stay logged in</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        Sign In Now <i class="bi bi-arrow-right-short ms-1"></i>
                    </button>
                    
                    <div class="alert" id="errorAlert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i> Invalid credentials.
                    </div>
                </form>

                <a href="https://cbfinance.rw/index.php" class="back-link">
                    <i class="bi bi-arrow-left"></i> Back to main website
                </a>
            </div>
        </div>
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
                const container = document.querySelector('.login-container');
                container.classList.add('shake');
                setTimeout(() => container.classList.remove('shake'), 400);
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