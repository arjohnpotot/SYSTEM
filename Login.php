<?php
session_start(); // Start session to store login data

// Redirect to dashboard if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: Dashboard.php');
    exit;
}

// Handle login logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Hardcoded credentials
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['loggedin'] = true; // Store session data
        $_SESSION['username'] = $username; // Store username for use in dashboard
        header('Location: Dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DENR System</title>
    
    <!-- Font Awesome 6 (updated) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            width: 2000px;
            height: 2000px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
            top: -10%;
            right: -20%;
            animation: float 20s infinite ease-in-out;
        }

        body::after {
            content: '';
            position: absolute;
            width: 1500px;
            height: 1500px;
            border-radius: 50%;
            background: linear-gradient(135deg, #764ba220 0%, #667eea20 100%);
            bottom: -10%;
            left: -20%;
            animation: float 15s infinite ease-in-out reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-30px, 30px) rotate(240deg); }
        }

        .login-wrapper {
            width: 100%;
            max-width: 450px;
            padding: 20px;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px 35px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
        }

        .logo-wrapper {
            margin-bottom: 20px;
            position: relative;
        }

        .logo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3); }
            50% { box-shadow: 0 10px 40px rgba(102, 126, 234, 0.6); }
            100% { box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3); }
        }

        .title {
            font-size: 16px;
            font-weight: 500;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 30px;
            padding: 0 10px;
            position: relative;
        }

        .title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 3px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 5px;
            margin-left: 5px;
        }

        .input-container {
            position: relative;
            width: 100%;
        }

        .input-container i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 16px;
            transition: color 0.3s ease;
            z-index: 1;
        }

        .input-container input {
            width: 100%;
            padding: 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: white;
            outline: none;
        }

        .input-container input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .input-container input:focus + i {
            color: #667eea;
        }

        .input-container input::placeholder {
            color: #cbd5e0;
            font-size: 14px;
        }

        /* Toggle password visibility */
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            color: #a0aec0;
            cursor: pointer;
            transition: color 0.3s ease;
            z-index: 1;
        }

        .toggle-password:hover {
            color: #667eea;
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            margin-top: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fff5f5;
            color: #e53e3e;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 14px;
            margin-top: 15px;
            border-left: 4px solid #e53e3e;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .error-message i {
            font-size: 16px;
        }

        .footer-text {
            margin-top: 20px;
            color: #718096;
            font-size: 13px;
        }

        .footer-text a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .footer-text a:hover {
            color: #764ba2;
        }

        /* Loading state */
        .login-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .login-btn.loading .btn-text {
            opacity: 0;
        }

        .login-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s infinite linear;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .login-wrapper {
                padding: 10px;
            }

            .login-container {
                padding: 30px 20px;
            }

            .logo {
                width: 100px;
                height: 100px;
            }

            .title {
                font-size: 14px;
            }

            .input-container input {
                padding: 12px 40px;
            }
        }

        /* Password strength indicator (optional enhancement) */
        .password-strength {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .strength-bar.weak {
            width: 33.33%;
            background: #e53e3e;
        }

        .strength-bar.medium {
            width: 66.66%;
            background: #ecc94b;
        }

        .strength-bar.strong {
            width: 100%;
            background: #48bb78;
        }

        /* Remember me checkbox */
        .remember-me {
            display: flex;
            align-items: center;
            margin: 15px 0;
            color: #718096;
            font-size: 14px;
            cursor: pointer;
        }

        .remember-me input {
            margin-right: 8px;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <!-- DENR Logo -->
            <div class="logo-wrapper">
                <img src="/DENR-SYSTEM/SYSTEM/image/DENR.jpg" alt="DENR Logo" class="logo">
            </div>
            
            <!-- Title Below Logo -->
            <div class="title">
                Department of Environment<br>and Natural Resources
            </div>

            <form action="Login.php" method="post" id="loginForm">
                <!-- Username Field -->
                <div class="input-group">
                    <label class="input-label" for="username">
                        <i class="fas fa-user" style="margin-right: 5px;"></i> Username
                    </label>
                    <div class="input-container">
                        <i class="fas fa-user"></i>
                        <input 
                            type="text" 
                            id="username"
                            name="username" 
                            placeholder="Enter your username" 
                            required
                            autocomplete="username"
                        >
                    </div>
                </div>

                <!-- Password Field -->
                <div class="input-group">
                    <label class="input-label" for="password">
                        <i class="fas fa-lock" style="margin-right: 5px;"></i> Password
                    </label>
                    <div class="input-container">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="password"
                            name="password" 
                            placeholder="Enter your password" 
                            required
                            autocomplete="current-password"
                        >
                        <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility()"></i>
                    </div>
                    <!-- Optional password strength indicator (can be enabled if needed) -->
                    <!-- <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div> -->
                </div>

                <!-- Remember Me (optional) -->
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>

                <!-- Login Button -->
                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="btn-text">Login to Dashboard</span>
                </button>

                <!-- Error Message -->
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Footer Links -->
                <div class="footer-text">
                   <!-- <a href="#" onclick="alert('Please contact system administrator for password reset.')">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a> -->
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Add loading state to form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.classList.add('loading');
        });

        // Optional: Password strength indicator (can be enabled)
        /*
        document.getElementById('password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthBar = document.getElementById('strengthBar');
            
            // Remove existing classes
            strengthBar.classList.remove('weak', 'medium', 'strong');
            
            if (password.length === 0) {
                strengthBar.style.width = '0';
                return;
            }
            
            // Calculate strength
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            // Update bar
            if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        });
        */

        // Add floating label effect
        const inputs = document.querySelectorAll('.input-container input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('i').style.color = '#667eea';
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.querySelector('i').style.color = '#a0aec0';
                }
            });
        });

        // Auto-hide error message after 5 seconds
        const errorMessage = document.querySelector('.error-message');
        if (errorMessage) {
            setTimeout(() => {
                errorMessage.style.transition = 'opacity 0.5s ease';
                errorMessage.style.opacity = '0';
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 500);
            }, 5000);
        }

        // Add input validation styling
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('invalid', function(e) {
                e.preventDefault();
                this.classList.add('error-input');
            });
        });
    </script>

    <!-- Optional: Add subtle animation on page load -->
    <style>
        .login-wrapper {
            opacity: 0;
            animation: pageLoad 0.8s ease-out forwards;
        }

        @keyframes pageLoad {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Error input styling */
        .error-input {
            border-color: #e53e3e !important;
        }

        .error-input:focus {
            box-shadow: 0 0 0 4px rgba(229, 62, 62, 0.1) !important;
        }

        /* Success animation for demo purposes */
        .login-success {
            animation: successPulse 0.5s ease;
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); background: rgba(72, 187, 120, 0.1); }
        }
    </style>
</body>
</html>