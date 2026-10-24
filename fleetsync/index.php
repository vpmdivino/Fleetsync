<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FleetSync - Authentication</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">

    <div class="w-full max-w-md bg-slate-800 rounded-2xl shadow-2xl border border-slate-700 overflow-hidden transition-all duration-300">
        
        <div class="p-6 bg-slate-850 text-center border-b border-slate-700">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-blue-600 text-white text-2xl mb-3 shadow-lg shadow-blue-500/30">
                <i class="fa-solid fa-truck-fast"></i>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">FleetSync</h1>
            <p class="text-sm text-slate-400 mt-1">Logistics & Fleet Management Portal</p>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="p-4 bg-red-500/10 text-red-400 text-sm border-b border-t border-red-500/20">
                <p class="font-bold">Error:</p>
                <p><?= $_SESSION['error_message']; ?></p>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="p-4 bg-emerald-500/10 text-emerald-400 text-sm border-b border-t border-emerald-500/20">
                 <p class="font-bold">Success:</p>
                 <p><?= $_SESSION['success_message']; ?></p>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div id="login-form" class="p-6 space-y-5">
            <h2 class="text-xl font-semibold text-white">Welcome back</h2>
            
            <form action="backend/login.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Email Address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" name="email" placeholder="Angeleneguzman@gmail.com" class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" placeholder="••••••••" class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" required>
                    </div>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center text-slate-400 cursor-pointer select-none">
                        <input type="checkbox" class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-blue-600 focus:ring-blue-500 focus:ring-offset-slate-800 mr-2">
                        Remember me
                    </label>
                    <a href="#" class="text-blue-500 hover:text-blue-400 font-medium transition-colors">Forgot password?</a>
                </div>

                <button type="submit" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg shadow-lg shadow-blue-500/20 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-800">
                    Sign In
                </button>
            </form>

            <div class="text-center text-sm text-slate-400 mt-4">
                Don't have an account? 
                <button onclick="toggleForms()" class="text-blue-500 hover:text-blue-400 font-semibold focus:outline-none ml-1">Register here</button>
            </div>
        </div>

        <div id="register-form" class="p-6 space-y-5 hidden">
            <h2 class="text-xl font-semibold text-white">Create an account</h2>
            
            <form action="backend/register.php" method="POST" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">First Name</label>
                        <input type="text" name="first_name" placeholder="Angelen" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Last Name</label>
                        <input type="text" name="last_name" placeholder="Guzman" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Email Address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" name="email" placeholder="Angeleneguzman@gmail.com" class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">System Role</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500 pointer-events-none">
                            <i class="fa-solid fa-user-gear"></i>
                        </span>
                        <select name="role" class="w-full pl-10 pr-10 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all appearance-none cursor-pointer" required>
                            <option value="" disabled selected hidden>Select your role</option>
                            <option value="Admin">Admin</option>
                            <option value="Fleet Manager">Fleet Manager</option>
                            <option value="Accountant">Accountant</option>
                        </select>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 pointer-events-none">
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" placeholder="Minimum 8 characters" class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" required>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg shadow-lg shadow-blue-500/20 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-800">
                    Register Account
                </button>
            </form>

            <div class="text-center text-sm text-slate-400 mt-4">
                Already have an account? 
                <button onclick="toggleForms()" class="text-blue-500 hover:text-blue-400 font-semibold focus:outline-none ml-1">Sign in</button>
            </div>
        </div>

    </div>

    <script>
        function toggleForms() {
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');
            
            loginForm.classList.toggle('hidden');
            registerForm.classList.toggle('hidden');
        }
    </script>
</body>
</html>