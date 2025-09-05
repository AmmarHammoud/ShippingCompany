<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Secure Access</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        .login-header {
            background: #4f46e5;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .login-header h1 {
            font-weight: 600;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .login-form {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #374151;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
        }

        .remember-me input {
            margin-right: 8px;
        }

        .forgot-password {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .login-button {
            width: 100%;
            padding: 14px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .login-button:hover {
            background: #4338ca;
        }

        .login-button:active {
            background: #3730a3;
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e5e7eb;
        }

        .divider span {
            background: white;
            position: relative;
            padding: 0 15px;
            color: #6b7280;
            font-size: 14px;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .social-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e7eb;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .social-btn:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .social-icon {
            width: 24px;
            height: 24px;
        }

        .gmail { color: #DB4437; }
        .youtube { color: #FF0000; }
        .maps { color: #4285F4; }
        .new-tab { color: #0f9d58; }

        .register-link {
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }

        .register-link a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .error-message.active {
            display: block;
        }

        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }

            .login-form {
                padding: 20px;
            }

            .remember-forgot {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <h1>Secure Access</h1>
        <p>Sign in to your account to continue</p>
    </div>

    <div class="login-form">
        <div class="error-message" id="errorMessage">
            Unauthorized access. Please check your credentials.
        </div>

        <form id="loginForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" class="form-input" placeholder="name@company.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" id="password" class="form-input" placeholder="••••••••" required>
                    <button type="button" class="toggle-password" id="togglePassword">👁️</button>
                </div>
            </div>

            <div class="remember-forgot">
                <div class="remember-me">
                    <input type="checkbox" id="remember">
                    <label for="remember">Remember me</label>
                </div>
                <a href="#" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" class="login-button">Sign in</button>
        </form>

        <div class="divider">
            <span>Or continue with</span>
        </div>

        <div class="social-login">
            <button class="social-btn">
                <svg class="social-icon gmail" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 12.5l6-4.5H6l6 4.5zm0 1.5l-6-4.5v9h12v-9l-6 4.5zm6-10.5H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-12c0-1.1-.9-2-2-2z"/>
                </svg>
            </button>

            <button class="social-btn">
                <svg class="social-icon youtube" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M10 15l5.19-3L10 9v6zm11.56-7.83c.13.47.22 1.1.28 1.9.07.8.1 1.49.1 2.09L22 12c0 2.19-.16 3.8-.44 4.83-.25.9-.83 1.48-1.73 1.73-.47.13-1.33.22-2.65.28-1.3.07-2.49.1-3.59.1L12 19c-4.19 0-6.8-.16-7.83-.44-.9-.25-1.48-.83-1.73-1.73-.13-.47-.22-1.1-.28-1.9-.07-.8-.1-1.49-.1-2.09L2 12c0-2.19.16-3.8.44-4.83.25-.9.83-1.48 1.73-1.73.47-.13 1.33-.22 2.65-.28 1.3-.07 2.49-.1 3.59-.1L12 5c4.19 0 6.8.16 7.83.44.9.25 1.48.83 1.73 1.73z"/>
                </svg>
            </button>

            <button class="social-btn">
                <svg class="social-icon maps" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/>
                </svg>
            </button>

            <button class="social-btn">
                <svg class="social-icon new-tab" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
                </svg>
            </button>
        </div>

        <div class="register-link">
            Don't have an account? <a href="#">Contact administrator</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const errorMessage = document.getElementById('errorMessage');

        // Toggle password visibility
        togglePassword.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                togglePassword.textContent = '🔒';
            } else {
                passwordInput.type = 'password';
                togglePassword.textContent = '👁️';
            }
        });

        // Form submission
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const email = document.getElementById('email').value;
            const password = passwordInput.value;

            // Simple validation
            if (!email || !password) {
                showError('Please fill in all fields');
                return;
            }

            // Email validation
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                showError('Please enter a valid email address');
                return;
            }

            // Simulate login process (in a real app, this would be an API call)
            simulateLogin(email, password);
        });

        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.classList.add('active');

            // Hide error after 5 seconds
            setTimeout(() => {
                errorMessage.classList.remove('active');
            }, 5000);
        }

        function simulateLogin(email, password) {
            // Show loading state
            const loginButton = loginForm.querySelector('button[type="submit"]');
            const originalText = loginButton.textContent;
            loginButton.textContent = 'Signing in...';
            loginButton.disabled = true;

            // Simulate API call delay
            setTimeout(() => {
                // This is a simulation - in a real app, you would make a fetch request to your Laravel backend
                if (email === 'admin@example.com' && password === 'password') {
                    // Success - redirect to dashboard or home page
                    alert('Login successful! Redirecting...');
                    // In a real app: window.location.href = '/dashboard';
                } else {
                    // Show error
                    showError('Invalid email or password');
                }

                // Reset button
                loginButton.textContent = originalText;
                loginButton.disabled = false;
            }, 1500);
        }

        // Check if there's an unauthorized access message (simulating Laravel error)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error') === 'unauthorized') {
            showError('Unauthorized access. Please login again.');
        }
    });
</script>
</body>
</html>
