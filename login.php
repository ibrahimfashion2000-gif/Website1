<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// আগে থেকে লগইন থাকলে সরাসরি ড্যাশবোর্ডে পাঠিয়ে দিন
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'ইউজারনেম ও পাসওয়ার্ড দুটোই দিতে হবে।';
    } else {
        try {
            // ধরে নেওয়া হয়েছে: users(id, username, password) — password column এ hashed পাসওয়ার্ড আছে
            $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $user['username'];
                header("Location: index.php");
                exit;
            } else {
                $error = 'ইউজারনেম অথবা পাসওয়ার্ড ভুল হয়েছে।';
            }
        } catch (PDOException $e) {
            $error = 'সিস্টেম সমস্যা হয়েছে, পরে আবার চেষ্টা করুন।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Admin Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  :root{
    --purple:#6c5ce7;
    --purple-dark:#5540d8;
    --purple-soft:#efeafe;
    --text:#1f2430;
    --muted:#8a90a3;
    --border:#e7e5f7;
    --error-bg:#fde8e8;
    --error-text:#d33a3a;
  }
  *{box-sizing:border-box;}
  html,body{height:100%; margin:0;}
  body{
    font-family:"Segoe UI","Noto Sans Bengali",sans-serif;
    background:linear-gradient(135deg, #f3f0ff 0%, #eef2ff 45%, #fdf2f8 100%);
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:100vh;
    padding:20px;
    position:relative;
    overflow:hidden;
  }
  /* হালকা ব্যাকগ্রাউন্ড শেপ, খুবই সাবটল */
  body::before, body::after{
    content:"";
    position:absolute;
    border-radius:50%;
    filter:blur(60px);
    opacity:0.35;
    z-index:0;
  }
  body::before{
    width:340px; height:340px;
    background:var(--purple);
    top:-120px; left:-100px;
  }
  body::after{
    width:300px; height:300px;
    background:#ff8fc6;
    bottom:-100px; right:-80px;
  }

  .login-card{
    position:relative;
    z-index:1;
    background:#ffffff;
    width:100%;
    max-width:400px;
    border-radius:20px;
    padding:40px 36px 34px;
    box-shadow:0 20px 50px rgba(76,60,160,0.15);
    animation:riseIn 0.5s ease;
  }
  @keyframes riseIn{
    from{ opacity:0; transform:translateY(14px); }
    to{ opacity:1; transform:translateY(0); }
  }

  .logo{
    width:56px; height:56px;
    border-radius:14px;
    background:linear-gradient(135deg, var(--purple), #9b6bff);
    display:flex; align-items:center; justify-content:center;
    font-size:26px;
    margin:0 auto 18px;
    box-shadow:0 8px 20px rgba(108,92,231,0.35);
  }

  h1{
    text-align:center;
    font-size:22px;
    margin:0 0 4px;
    color:var(--text);
  }
  .subtitle{
    text-align:center;
    color:var(--muted);
    font-size:13.5px;
    margin:0 0 26px;
  }

  .error-box{
    background:var(--error-bg);
    color:var(--error-text);
    font-size:13px;
    padding:10px 14px;
    border-radius:10px;
    margin-bottom:18px;
    text-align:center;
  }

  .field{
    margin-bottom:16px;
  }
  .field label{
    display:block;
    font-size:12.5px;
    font-weight:600;
    color:#5b6070;
    margin-bottom:6px;
  }
  .input-wrap{
    position:relative;
    display:flex;
    align-items:center;
  }
  .input-wrap .icon{
    position:absolute;
    left:14px;
    font-size:15px;
    color:var(--muted);
    pointer-events:none;
  }
  .input-wrap input{
    width:100%;
    padding:12px 14px 12px 40px;
    border:1.5px solid var(--border);
    border-radius:11px;
    font-size:14px;
    outline:none;
    background:#faf9ff;
    color:var(--text);
    transition:border-color .15s, box-shadow .15s, background .15s;
  }
  .input-wrap input:focus{
    border-color:var(--purple);
    background:#fff;
    box-shadow:0 0 0 4px rgba(108,92,231,0.12);
  }
  .toggle-eye{
    position:absolute;
    right:14px;
    background:none;
    border:none;
    cursor:pointer;
    font-size:15px;
    color:var(--muted);
    padding:0;
  }

  .row-between{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin:2px 0 22px;
    font-size:12.5px;
  }
  .remember{
    display:flex;
    align-items:center;
    gap:6px;
    color:#5b6070;
  }
  .remember input{ accent-color:var(--purple); }
  .forgot{
    color:var(--purple);
    text-decoration:none;
    font-weight:600;
  }
  .forgot:hover{ text-decoration:underline; }

  .btn-login{
    width:100%;
    padding:13px;
    border:none;
    border-radius:11px;
    background:linear-gradient(135deg, var(--purple), var(--purple-dark));
    color:#fff;
    font-size:14.5px;
    font-weight:700;
    cursor:pointer;
    transition:transform .12s, box-shadow .12s;
    box-shadow:0 10px 20px rgba(108,92,231,0.3);
  }
  .btn-login:hover{ transform:translateY(-1px); box-shadow:0 12px 24px rgba(108,92,231,0.38); }
  .btn-login:active{ transform:translateY(0); }

  .footer-note{
    text-align:center;
    color:var(--muted);
    font-size:11.5px;
    margin-top:22px;
  }
</style>
</head>
<body>

  <div class="login-card">
    <div class="logo">🔐</div>
    <h1>Admin Login</h1>
    <p class="subtitle">আপনার এডমিন প্যানেলে প্রবেশ করুন</p>

    <?php if ($error): ?>
      <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="field">
        <label for="username">Username</label>
        <div class="input-wrap">
          <span class="icon">👤</span>
          <input type="text" id="username" name="username" placeholder="admin" required autofocus>
        </div>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="input-wrap">
          <span class="icon">🔒</span>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
          <button type="button" class="toggle-eye" onclick="togglePassword()">👁️</button>
        </div>
      </div>

      <div class="row-between">
        <label class="remember">
          <input type="checkbox" name="remember">
          Remember me
        </label>
        <a href="forgot_password.php" class="forgot">Forgot Password?</a>
      </div>

      <button type="submit" class="btn-login">Login</button>
    </form>

    <p class="footer-note">© <?php echo date('Y'); ?> YourBrand — All rights reserved</p>
  </div>

<script>
function togglePassword(){
  const input = document.getElementById('password');
  const btn = document.querySelector('.toggle-eye');
  if (input.type === 'password') {
    input.type = 'text';
    btn.textContent = '🙈';
  } else {
    input.type = 'password';
    btn.textContent = '👁️';
  }
}
</script>
</body>
</html>