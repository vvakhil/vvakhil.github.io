<?php
/**
 * Akhil V V — Senior Software Engineer | Backend & Systems Architecture
 * Main Portfolio Application & Contact Mailer Controller
 */

define('APP_INIT', true);

// Start session securely for CSRF and flash messaging
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Load configuration and SMTP mailer
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

// Generate CSRF Token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Flash messages
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Handle Form Submission (POST)
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = [];

    // Parse JSON or POST payload
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $inputData = json_decode($raw, true) ?: [];
    } else {
        $inputData = $_POST;
    }

    $errors = [];
    $name = trim($inputData['name'] ?? '');
    $email = trim($inputData['email'] ?? $inputData['_replyto'] ?? '');
    $subject = trim($inputData['subject'] ?? 'Portfolio Inquiry');
    $message = trim($inputData['message'] ?? '');
    $submittedCsrf = $inputData['csrf_token'] ?? $inputData['_csrf_token'] ?? '';
    $honeypot = trim($inputData['hp_confirm_code_val'] ?? '');

    // 1. Anti-Spam Honeypot check (only if bots deliberately filled hidden trap)
    if (!empty($honeypot)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Thank you! Your message has been sent successfully.']);
            exit;
        } else {
            $_SESSION['flash_message'] = 'Thank you! Your message has been sent.';
            $_SESSION['flash_type'] = 'success';
            header('Location: index.php#contact');
            exit;
        }
    }

    // 2. CSRF Verification
    if (empty($submittedCsrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $submittedCsrf)) {
        $errors['csrf'] = 'Invalid or expired session security token. Please refresh the page.';
    }

    // 3. Rate Limiting Check (min 10 seconds between submissions per session)
    $lastSubmitTime = $_SESSION['last_submit_time'] ?? 0;
    if (time() - $lastSubmitTime < 10) {
        $errors['rate_limit'] = 'Please wait a moment before sending another message.';
    }

    // 4. Field Validation
    if (empty($name)) {
        $errors['name'] = 'Please enter your name or company.';
    } elseif (mb_strlen($name) < 2) {
        $errors['name'] = 'Name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Name cannot exceed 100 characters.';
    }

    if (empty($email)) {
        $errors['email'] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($message)) {
        $errors['message'] = 'Please enter your message or project requirements.';
    } elseif (mb_strlen($message) < 10) {
        $errors['message'] = 'Message must be at least 10 characters.';
    } elseif (mb_strlen($message) > 5000) {
        $errors['message'] = 'Message cannot exceed 5000 characters.';
    }

    // Process mail if no validation errors
    if (empty($errors)) {
        $mailer = new SmtpMailer($config['mail']);
        $sent = $mailer->send([
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
        ]);

        if ($sent) {
            $_SESSION['last_submit_time'] = time();
            // Regenerate CSRF token after successful submission
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Thank you! Your message has been sent successfully. I will get back to you shortly.',
                    'csrf_token' => $_SESSION['csrf_token']
                ]);
                exit;
            } else {
                $_SESSION['flash_message'] = 'Thank you! Your message has been sent successfully.';
                $_SESSION['flash_type'] = 'success';
                header('Location: index.php#contact');
                exit;
            }
        } else {
            $errorMsg = $mailer->getLastError() ?: 'Failed to deliver message. Please try again or reach out via direct email.';
            if ($isAjax) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errorMsg]);
                exit;
            } else {
                $_SESSION['flash_message'] = $errorMsg;
                $_SESSION['flash_type'] = 'error';
                header('Location: index.php#contact');
                exit;
            }
        }
    } else {
        if ($isAjax) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        } else {
            $_SESSION['flash_message'] = implode(' ', $errors);
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php#contact');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Akhil V V — Senior Software Engineer | Backend &amp; Distributed Systems</title>
<meta name="description" content="Akhil V V — Senior Software Engineer & Technical Lead specializing in backend architecture, distributed systems, logistics platforms, ZATCA Phase 2 compliance, and high-throughput APIs.">
<meta name="author" content="Akhil V V">
<meta name="keywords" content="Senior Software Engineer, Backend Engineer, Technical Lead, PHP, Laravel, Node.js, Distributed Systems, ZATCA Phase 2, Redis, PostgreSQL, MySQL">
<link rel="canonical" href="https://akhilvv.github.io/">

<!-- Favicon & Icons -->
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="alternate icon" href="favicon.svg">
<link rel="apple-touch-icon" href="favicon.svg">
<meta name="theme-color" content="#080D0F">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="https://akhilvv.github.io/">
<meta property="og:title" content="Akhil V V — Senior Software Engineer | Backend &amp; Systems">
<meta property="og:description" content="Senior Software Engineer specializing in backend architecture, distributed logistics systems, APIs, and enterprise cloud software.">
<meta property="og:site_name" content="Akhil V V Portfolio">
<meta property="og:image" content="https://akhilvv.github.io/img1.png">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Akhil V V — Senior Software Engineer | Backend &amp; Systems">
<meta name="twitter:description" content="Senior Software Engineer specializing in backend architecture, distributed logistics systems, APIs, and enterprise platforms.">
<meta name="twitter:image" content="https://akhilvv.github.io/img1.png">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<!-- Schema.org JSON-LD Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Akhil V V",
  "jobTitle": "Senior Software Engineer & Technical Lead",
  "url": "https://akhilvv.github.io",
  "email": "mailto:vvakhilkarun@gmail.com",
  "telephone": "+918590449417",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Kozhikode",
    "addressRegion": "Kerala",
    "addressCountry": "India"
  },
  "sameAs": [
    "https://linkedin.com/in/akhilvv",
    "https://github.com/akhilvv"
  ],
  "knowsAbout": [
    "Backend Architecture",
    "Distributed Systems",
    "Laravel",
    "PHP",
    "Node.js",
    "PostgreSQL",
    "MySQL",
    "Redis",
    "ZATCA Phase 2 E-Invoicing",
    "RESTful APIs",
    "Docker"
  ]
}
</script>

<style>
  :root {
    --bg: #080D0F;
    --bg-soft: #0E1619;
    --panel: rgba(255, 255, 255, 0.03);
    --panel-solid: #101A1D;
    --panel-alt: rgba(255, 255, 255, 0.06);
    --border: rgba(255, 255, 255, 0.1);
    --border-soft: rgba(255, 255, 255, 0.06);
    --text: #F4F2EB;
    --text-dim: #93A3AA;
    --text-faint: #57676D;
    --accent: #F2A93C;
    --accent-rgb: 242, 169, 60;
    --accent-soft: rgba(242, 169, 60, 0.14);
    --accent2: #5FD4E0;
    --accent2-rgb: 95, 212, 224;
    --accent2-soft: rgba(95, 212, 224, 0.14);
    --accent3: #FF7E62;
    --success: #22C55E;
    --error: #EF4444;
    --radius: 8px;
    --radius-lg: 16px;
    --maxw: 1160px;
    --font-display: 'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-body: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: 'IBM Plex Mono', monospace;
  }

  *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
  html { scroll-behavior: smooth; }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--font-body);
    line-height: 1.65;
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
    position: relative;
  }

  /* Selection styling */
  ::selection {
    background: rgba(var(--accent-rgb), 0.35);
    color: #FFFFFF;
  }

  /* ---------- Fullscreen Preloader ---------- */
  #preloader {
    position: fixed; inset: 0; z-index: 9999;
    background: var(--bg);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    transition: opacity 0.5s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.5s ease;
  }
  #preloader.fade-out { opacity: 0; visibility: hidden; pointer-events: none; }

  .loader-box {
    width: min(90vw, 480px);
    background: var(--panel-solid);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 32px 28px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.8);
    position: relative;
  }
  .loader-header {
    display: flex; align-items: center; justify-content: space-between;
    padding-bottom: 16px; margin-bottom: 20px;
    border-bottom: 1px solid var(--border-soft);
  }
  .loader-dots { display: flex; gap: 6px; }
  .loader-dots span { width: 10px; height: 10px; border-radius: 50%; background: var(--border); }
  .loader-dots span:nth-child(1) { background: #FF5F56; }
  .loader-dots span:nth-child(2) { background: #FFBD2E; }
  .loader-dots span:nth-child(3) { background: #27C93F; }
  .loader-title { font-family: var(--font-mono); font-size: 12px; color: var(--text-faint); }

  .loader-logs {
    font-family: var(--font-mono); font-size: 13px; color: var(--text-dim);
    display: flex; flex-direction: column; gap: 8px; min-height: 100px;
  }
  .log-line { opacity: 0; transform: translateX(-6px); animation: logIn 0.3s forwards ease; }
  .log-line.accent { color: var(--accent); }
  .log-line.cyan { color: var(--accent2); }
  @keyframes logIn { to { opacity: 1; transform: translateX(0); } }

  .loader-bar-wrap { margin-top: 24px; background: rgba(255, 255, 255, 0.05); border-radius: 10px; height: 6px; overflow: hidden; position: relative; }
  .loader-bar-fill { height: 100%; width: 0%; background: linear-gradient(90deg, var(--accent), var(--accent2)); border-radius: 10px; transition: width 0.15s linear; box-shadow: 0 0 10px rgba(var(--accent-rgb), 0.8); }

  .loader-skip {
    display: block; margin-top: 14px; text-align: center;
    font-family: var(--font-mono); font-size: 11px; color: var(--text-faint);
    background: none; border: none; cursor: pointer; text-decoration: underline;
  }
  .loader-skip:hover { color: var(--accent); }

  /* ---------- Background & Glow ---------- */
  .bg-grid {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background-image:
      linear-gradient(var(--border-soft) 1px, transparent 1px),
      linear-gradient(90deg, var(--border-soft) 1px, transparent 1px);
    background-size: 56px 56px; opacity: 0.35;
    mask-image: radial-gradient(ellipse 90% 70% at 50% 0%, black 30%, transparent 85%);
  }
  .orb { position: fixed; border-radius: 50%; filter: blur(120px); pointer-events: none; z-index: 0; opacity: 0.22; }
  .orb-1 { width: 500px; height: 500px; top: -120px; left: -80px; background: var(--accent); }
  .orb-2 { width: 460px; height: 460px; top: 25%; right: -100px; background: var(--accent2); }

  .cursor-glow {
    position: fixed; width: 400px; height: 400px; border-radius: 50%;
    background: radial-gradient(circle, rgba(var(--accent-rgb), 0.08), rgba(var(--accent2-rgb), 0.04) 45%, transparent 70%);
    transform: translate(-50%, -50%); pointer-events: none; z-index: 1; opacity: 0; transition: opacity 0.3s ease;
  }

  a { color: inherit; text-decoration: none; }
  .wrap { max-width: var(--maxw); margin: 0 auto; padding: 0 32px; position: relative; z-index: 2; }

  /* ---------- Scroll Progress ---------- */
  .progress-bar {
    position: fixed; top: 0; left: 0; height: 3px; width: 0%;
    background: linear-gradient(90deg, var(--accent), var(--accent2));
    z-index: 1000; transition: width 0.05s linear;
  }

  /* ---------- Header & Navigation ---------- */
  header {
    position: sticky; top: 0; z-index: 500;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    background: rgba(8, 13, 15, 0.82);
    border-bottom: 1px solid var(--border-soft);
    transition: background 0.3s ease;
  }
  nav { display: flex; align-items: center; justify-content: space-between; padding: 18px 32px; max-width: var(--maxw); margin: 0 auto; }
  .nav-mark { display: flex; align-items: center; gap: 10px; }
  .mark-badge {
    width: 34px; height: 34px; border-radius: 8px;
    background: linear-gradient(135deg, var(--accent), var(--accent3));
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-display); font-weight: 700; font-size: 14px; color: #080D0F;
    box-shadow: 0 4px 12px rgba(var(--accent-rgb), 0.3);
  }
  .mark-text { font-family: var(--font-mono); font-size: 13px; color: var(--text-dim); }
  .nav-links { display: flex; gap: 6px; font-size: 13.5px; }
  .nav-links a {
    color: var(--text-dim); padding: 8px 14px; border-radius: 20px;
    transition: color 0.2s, background 0.2s; font-family: var(--font-mono);
  }
  .nav-links a:hover { color: var(--text); background: var(--panel-alt); }
  .nav-links a.active { color: var(--accent); background: var(--accent-soft); font-weight: 600; }

  .nav-toggle {
    display: none; flex-direction: column; justify-content: center; gap: 5px;
    background: none; border: none; cursor: pointer; padding: 8px; width: 36px; height: 36px;
  }
  .nav-toggle span {
    width: 22px; height: 2px; background: var(--text);
    transition: transform 0.3s ease, opacity 0.3s ease; border-radius: 2px;
  }
  .nav-toggle.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
  .nav-toggle.open span:nth-child(2) { opacity: 0; }
  .nav-toggle.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

  @media (max-width: 768px) {
    .nav-links {
      position: fixed; top: 71px; right: 0; left: 0; flex-direction: column; gap: 6px;
      background: rgba(8, 13, 15, 0.98); backdrop-filter: blur(20px);
      padding: 20px 24px; border-bottom: 1px solid var(--border);
      transform: translateY(-15px); opacity: 0; pointer-events: none; transition: 0.3s ease;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
    }
    .nav-links.open { transform: translateY(0); opacity: 1; pointer-events: auto; }
    .nav-toggle { display: flex; }
  }

  /* ---------- Hero Section ---------- */
  .hero { position: relative; padding: 90px 0 76px; }
  .hero-grid {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 48px;
    align-items: center;
  }
  .hero-content {
    position: relative;
    z-index: 2;
  }
  .status-chip {
    display: inline-flex; align-items: center; gap: 8px;
    font-family: var(--font-mono); font-size: 12px; color: var(--accent2);
    background: var(--accent2-soft); border: 1px solid rgba(var(--accent2-rgb), 0.35);
    padding: 6px 14px; border-radius: 20px; margin-bottom: 22px;
  }
  .status-chip .dot {
    width: 7px; height: 7px; border-radius: 50%; background: var(--accent2);
    box-shadow: 0 0 8px var(--accent2); animation: pulseDot 2s infinite ease-in-out;
  }
  @keyframes pulseDot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(0.85); } }

  .hero h1 {
    font-family: var(--font-display); font-weight: 700;
    font-size: clamp(34px, 4.3vw, 56px); line-height: 1.14; letter-spacing: -0.02em;
    margin-bottom: 18px;
  }
  .hero h1 .grad {
    background: linear-gradient(110deg, var(--accent), var(--accent3) 65%, var(--accent) 130%);
    -webkit-background-clip: text; background-clip: text; color: transparent;
  }
  .hero .role { font-family: var(--font-mono); font-size: 16px; color: var(--accent); margin-bottom: 18px; font-weight: 500; }
  .hero p.lede { font-size: 16.5px; color: var(--text-dim); margin-bottom: 34px; line-height: 1.75; }
  .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }

  /* Hero Media / Transparent Cutout Showcase */
  .hero-media {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 2;
  }
  .hero-portrait-stage {
    position: relative;
    width: 100%;
    max-width: 480px;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .hero-portrait-aura {
    position: absolute;
    width: 380px;
    height: 380px;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -55%);
    background: radial-gradient(circle, rgba(var(--accent-rgb), 0.24) 0%, rgba(var(--accent2-rgb), 0.16) 45%, transparent 70%);
    border-radius: 50%;
    filter: blur(36px);
    pointer-events: none;
    z-index: 1;
  }
  .hero-portrait-backdrop {
    position: absolute;
    width: 320px;
    height: 320px;
    top: 45%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: radial-gradient(ellipse at center, rgba(16, 26, 29, 0.9) 0%, rgba(8, 13, 15, 0.4) 65%, transparent 100%);
    border: 1px solid rgba(var(--accent-rgb), 0.15);
    border-radius: 50%;
    pointer-events: none;
    z-index: 1;
  }
  .hero-portrait-img {
    position: relative;
    width: 100%;
    height: auto;
    max-height: 440px;
    object-fit: contain;
    display: block;
    z-index: 2;
    filter: drop-shadow(0 20px 35px rgba(0, 0, 0, 0.9)) drop-shadow(0 0 25px rgba(var(--accent-rgb), 0.15));
    transition: transform 0.4s ease, filter 0.4s ease;
  }
  .hero-portrait-stage:hover .hero-portrait-img {
    transform: translateY(-4px) scale(1.02);
    filter: drop-shadow(0 26px 45px rgba(0, 0, 0, 0.95)) drop-shadow(0 0 35px rgba(var(--accent-rgb), 0.25));
  }
  .hero-portrait-badge {
    position: relative;
    margin-top: -16px;
    background: rgba(16, 26, 29, 0.94);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(var(--accent-rgb), 0.35);
    border-radius: var(--radius);
    padding: 10px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 14px 34px rgba(0, 0, 0, 0.7);
    z-index: 3;
    max-width: 94%;
  }
  .hero-portrait-badge .badge-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--accent);
    box-shadow: 0 0 10px var(--accent);
    flex-shrink: 0;
  }
  .hero-portrait-badge .badge-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    line-height: 1.3;
  }
  .hero-portrait-badge .badge-text strong {
    font-family: var(--font-display);
    font-size: 13px;
    color: var(--text);
    font-weight: 600;
  }
  .hero-portrait-badge .badge-text span {
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--text-dim);
  }

  .btn {
    font-family: var(--font-mono); font-size: 13.5px; padding: 13px 24px; border-radius: var(--radius);
    border: 1px solid var(--border); display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    cursor: pointer; transition: all 0.25s ease; font-weight: 500;
  }
  .btn-primary { background: linear-gradient(120deg, var(--accent), var(--accent3)); color: #080D0F; border-color: transparent; font-weight: 600; }
  .btn-primary:hover { box-shadow: 0 10px 25px -8px rgba(var(--accent-rgb), 0.65); transform: translateY(-2px); }
  .btn-ghost { color: var(--text); background: var(--panel); }
  .btn-ghost:hover { border-color: var(--accent2); color: var(--accent2); background: var(--panel-alt); transform: translateY(-2px); }

  /* ---------- Tech Marquee ---------- */
  .marquee-shell { border-top: 1px solid var(--border-soft); border-bottom: 1px solid var(--border-soft); padding: 18px 0; overflow: hidden; position: relative; }
  .marquee-shell::before, .marquee-shell::after { content: ""; position: absolute; top: 0; bottom: 0; width: 120px; z-index: 2; }
  .marquee-shell::before { left: 0; background: linear-gradient(90deg, var(--bg), transparent); }
  .marquee-shell::after { right: 0; background: linear-gradient(270deg, var(--bg), transparent); }
  .marquee-track { display: flex; gap: 44px; width: max-content; animation: marquee 30s linear infinite; }
  .marquee-track span { font-family: var(--font-mono); font-size: 13px; color: var(--text-faint); display: flex; align-items: center; gap: 8px; }
  .marquee-track span::before { content: "◆"; color: var(--accent); font-size: 7px; }
  @keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }

  /* ---------- Section Components ---------- */
  section { padding: 96px 0; position: relative; }
  .section-head { display: flex; align-items: baseline; gap: 16px; margin-bottom: 44px; }
  .section-num { font-family: var(--font-mono); color: var(--accent); font-size: 13px; font-weight: 600; }
  .section-head h2 { font-family: var(--font-display); font-size: clamp(26px, 3.2vw, 36px); font-weight: 600; letter-spacing: -0.01em; }
  .section-rule { flex: 1; height: 1px; background: linear-gradient(90deg, var(--border), transparent); }

  /* ---------- Pillars (Architecture & Impact) ---------- */
  .pillars-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
  @media (max-width: 960px) { .pillars-grid { grid-template-columns: 1fr 1fr; } }
  @media (max-width: 540px) { .pillars-grid { grid-template-columns: 1fr; } }
  .pillar-card {
    background: var(--panel-solid); border: 1px solid var(--border-soft); border-radius: var(--radius-lg);
    padding: 26px 22px; transition: border-color 0.3s, transform 0.3s, box-shadow 0.3s;
  }
  .pillar-card:hover { border-color: rgba(var(--accent-rgb), 0.4); transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4); }
  .pillar-icon { font-family: var(--font-mono); font-size: 12px; color: var(--accent); margin-bottom: 12px; font-weight: 600; }
  .pillar-title { font-family: var(--font-display); font-size: 17.5px; font-weight: 600; margin-bottom: 8px; color: var(--text); }
  .pillar-desc { font-size: 13.5px; color: var(--text-dim); line-height: 1.6; }

  /* ---------- Experience Timeline ---------- */
  .timeline { position: relative; padding-left: 36px; }
  .timeline::before { content: ""; position: absolute; left: 5px; top: 6px; bottom: 6px; width: 2px; background: var(--border); }
  .job { position: relative; padding-bottom: 48px; }
  .job:last-child { padding-bottom: 0; }
  .job::before {
    content: ""; position: absolute; left: -36px; top: 6px; width: 12px; height: 12px;
    border-radius: 50%; background: var(--accent); border: 2px solid var(--bg);
    box-shadow: 0 0 10px rgba(var(--accent-rgb), 0.7);
  }
  .job-card { background: var(--panel); border: 1px solid var(--border-soft); border-radius: var(--radius-lg); padding: 28px; transition: border-color 0.3s; }
  .job-card:hover { border-color: rgba(255, 255, 255, 0.15); }
  .job-top { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px 20px; margin-bottom: 4px; }
  .job-title { font-family: var(--font-display); font-size: 20px; font-weight: 600; }
  .job-dates { font-family: var(--font-mono); font-size: 12px; color: var(--accent2); background: var(--accent2-soft); padding: 4px 12px; border-radius: 20px; }
  .job-company { font-size: 14px; color: var(--text-dim); margin-bottom: 16px; font-weight: 500; }
  .job ul { list-style: none; display: flex; flex-direction: column; gap: 10px; }
  .job li { position: relative; padding-left: 20px; color: var(--text-dim); font-size: 14.5px; line-height: 1.6; }
  .job li::before { content: "›"; position: absolute; left: 0; color: var(--accent); font-family: var(--font-mono); font-weight: bold; font-size: 16px; }
  .job li strong { color: var(--text); font-weight: 600; }

  /* ---------- Projects Grid ---------- */
  .projects { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
  @media (max-width: 960px) { .projects { grid-template-columns: 1fr; } }
  .project {
    background: var(--panel); border: 1px solid var(--border-soft); border-radius: var(--radius-lg);
    padding: 28px 26px; transition: border-color 0.3s, transform 0.3s, box-shadow 0.3s;
    display: flex; flex-direction: column; justify-content: space-between;
  }
  .project:hover { border-color: rgba(var(--accent-rgb), 0.4); transform: translateY(-4px); box-shadow: 0 16px 36px rgba(0, 0, 0, 0.45); }
  .project-name { font-family: var(--font-display); font-size: 19px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
  .project-loc { font-family: var(--font-mono); font-size: 11.5px; color: var(--text-faint); margin-bottom: 14px; }
  .project-desc { color: var(--text-dim); font-size: 14px; margin-bottom: 20px; flex: 1; line-height: 1.65; }
  .tag-row { display: flex; flex-wrap: wrap; gap: 6px; }
  .tag {
    font-family: var(--font-mono); font-size: 11px; color: var(--accent2);
    background: var(--accent2-soft); border: 1px solid rgba(var(--accent2-rgb), 0.3);
    padding: 3px 9px; border-radius: 16px;
  }

  /* ---------- Skills Grid ---------- */
  .skills-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
  @media (max-width: 860px) { .skills-grid { grid-template-columns: 1fr 1fr; } }
  @media (max-width: 540px) { .skills-grid { grid-template-columns: 1fr; } }
  .skill-card { background: var(--panel); border: 1px solid var(--border-soft); border-radius: var(--radius-lg); padding: 24px 22px; }
  .skill-card h3 { font-family: var(--font-mono); font-size: 12px; color: var(--text-faint); margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.06em; }
  .skill-card .tag { color: var(--text-dim); background: transparent; border-color: var(--border); }
  .skill-card.highlight { background: linear-gradient(160deg, var(--accent-soft), var(--panel) 60%); border-color: rgba(var(--accent-rgb), 0.35); }
  .skill-card.highlight h3 { color: var(--accent); }
  .skill-card.highlight .tag { color: var(--accent); border-color: rgba(var(--accent-rgb), 0.35); background: rgba(var(--accent-rgb), 0.12); }

  /* ---------- Resume Download CTA ---------- */
  .resume-cta { padding: 0 0 32px; }
  .resume-cta-card {
    display: flex; align-items: center; justify-content: space-between; gap: 28px;
    padding: 32px 34px;
    background: linear-gradient(135deg, var(--accent-soft), var(--panel-solid));
    border: 1px solid rgba(var(--accent-rgb), 0.35);
    border-radius: var(--radius-lg);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
  }
  .resume-cta-label {
    font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.08em;
    color: var(--accent); margin-bottom: 8px; font-weight: 600;
  }
  .resume-cta h2 { font-family: var(--font-display); font-size: 24px; margin-bottom: 6px; }
  .resume-cta p { color: var(--text-dim); font-size: 14px; max-width: 65ch; }
  @media (max-width: 768px) {
    .resume-cta-card { flex-direction: column; align-items: stretch; padding: 24px 20px; gap: 18px; }
    .resume-cta .btn { width: 100%; }
  }

  /* ---------- Contact & SMTP Form ---------- */
  .contact-layout { display: grid; grid-template-columns: 1fr 1.25fr; gap: 48px; }
  @media (max-width: 860px) { .contact-layout { grid-template-columns: 1fr; } }

  .contact-info h3 { font-family: var(--font-display); font-size: 28px; margin-bottom: 12px; line-height: 1.3; }
  .contact-info p { color: var(--text-dim); margin-bottom: 28px; font-size: 15px; }
  .quick-links { display: flex; flex-direction: column; gap: 14px; margin-bottom: 28px; }
  .quick-link-item { font-family: var(--font-mono); font-size: 13.5px; color: var(--text-dim); display: flex; align-items: center; gap: 10px; }
  .quick-link-item a { color: var(--text); transition: color 0.2s; }
  .quick-link-item a:hover { color: var(--accent); }

  .contact-form {
    background: var(--panel-solid); border: 1px solid var(--border-soft);
    border-radius: var(--radius-lg); padding: 34px 30px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
  }
  .form-group { margin-bottom: 20px; position: relative; }
  .form-group label {
    display: block; font-family: var(--font-mono); font-size: 12px;
    color: var(--text-dim); margin-bottom: 8px; font-weight: 500;
  }
  .form-group input, .form-group textarea, .form-group select {
    width: 100%; background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 13px 16px; color: var(--text);
    font-family: var(--font-body); font-size: 14.5px; transition: border-color 0.2s, background 0.2s;
  }
  .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    outline: none; border-color: var(--accent); background: rgba(255, 255, 255, 0.07);
    box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.15);
  }
  .form-group input.is-invalid, .form-group textarea.is-invalid {
    border-color: var(--error) !important;
    background: rgba(239, 68, 68, 0.05);
  }
  .field-error {
    display: none; font-family: var(--font-mono); font-size: 11.5px;
    color: #F87171; margin-top: 5px;
  }
  .field-error.show { display: block; }

  /* Honeypot field (hidden from real users) */
  .hp-field { position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; pointer-events: none; }

  /* Alert / Status Banner */
  .alert-banner {
    padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px;
    font-family: var(--font-mono); font-size: 13px; display: none; line-height: 1.5;
  }
  .alert-banner.show { display: block; }
  .alert-banner.success {
    background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.35); color: #86EFAC;
  }
  .alert-banner.error {
    background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #FCA5A5;
  }

  /* Submit button spinner */
  .btn-spinner {
    display: none; width: 16px; height: 16px; border: 2px solid rgba(8, 13, 15, 0.3);
    border-top-color: #080D0F; border-radius: 50%; animation: spin 0.6s linear infinite;
  }
  .btn.is-loading .btn-spinner { display: inline-block; }
  .btn.is-loading .btn-text { opacity: 0.8; }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* ---------- Footer ---------- */
  footer { padding: 44px 0; text-align: center; border-top: 1px solid var(--border-soft); }
  .footer-meta { font-family: var(--font-mono); font-size: 12.5px; color: var(--text-faint); }

  /* ---------- Responsive Polish ---------- */
  @media (max-width: 980px) {
    .hero { padding: 72px 0 52px; }
    .hero-grid {
      grid-template-columns: 1fr;
      gap: 36px;
    }
    .hero-media {
      max-width: 440px;
      margin: 0 auto 6px;
      width: 100%;
    }
    .hero-portrait-img {
      max-height: 390px;
    }
  }

  @media (max-width: 760px) {
    .wrap { padding: 0 20px; }
    nav { padding: 14px 20px; }
    .hero { padding: 56px 0 42px; }
    .hero h1 { font-size: clamp(32px, 8.5vw, 44px); max-width: 100%; }
    .hero .role { font-size: 14.5px; }
    .hero p.lede { font-size: 15.5px; margin-bottom: 24px; }
    .hero-actions { gap: 10px; }
    .hero-portrait-aura { width: 300px; height: 300px; }
    .hero-portrait-backdrop { width: 260px; height: 260px; }
    .hero-portrait-img { max-height: 330px; }
    .hero-portrait-badge {
      padding: 9px 14px;
      margin-top: -10px;
    }
    .btn { width: 100%; justify-content: center; }
    .marquee-shell { padding: 14px 0; }
    .marquee-track { gap: 28px; }
    section { padding: 70px 0; }
    .section-head { gap: 10px; margin-bottom: 30px; }
    .section-head h2 { font-size: 27px; }
    .timeline { padding-left: 24px; }
    .timeline::before { left: 3px; }
    .job::before { left: -27px; width: 10px; height: 10px; }
    .job-card { padding: 22px 18px; }
    .job-title { font-size: 18px; }
    .job li { font-size: 14px; }
    .project { padding: 24px 20px; }
    .contact-form { padding: 26px 20px; }
  }

  @media (max-width: 420px) {
    .wrap { padding: 0 16px; }
    .loader-box { width: calc(100vw - 28px); padding: 24px 18px; }
    .loader-logs { font-size: 11.5px; }
    .hero { padding-top: 60px; }
    .status-chip { font-size: 10.5px; padding: 6px 11px; }
    .hero h1 { font-size: 32px; }
    .section-head h2 { font-size: 24px; }
  }

  @media (prefers-reduced-motion: reduce) {
    html { scroll-behavior: auto; }
    *, *::before, *::after {
      animation-duration: 0.01ms !important;
      animation-iteration-count: 1 !important;
      transition-duration: 0.01ms !important;
    }
    .marquee-track { animation: none; }
  }

  :focus-visible {
    outline: 2px solid var(--accent2);
    outline-offset: 3px;
  }
</style>
</head>
<body>

<!-- PRELOADER (Senior Engineering Workspace Init) -->
<div id="preloader" role="status" aria-label="Loading portfolio">
  <div class="loader-box">
    <div class="loader-header">
      <div class="loader-dots"><span></span><span></span><span></span></div>
      <div class="loader-title">sys_init.sh — akhil@vv</div>
    </div>
    <div class="loader-logs" id="loaderLogs"></div>
    <div class="loader-bar-wrap">
      <div class="loader-bar-fill" id="loaderBar"></div>
    </div>
    <button class="loader-skip" id="loaderSkip">Skip Intro →</button>
  </div>
</div>

<div class="progress-bar" id="progressBar"></div>
<div class="bg-grid"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="cursor-glow" id="cursorGlow"></div>

<!-- HEADER -->
<header id="mainHeader">
  <nav aria-label="Main Navigation">
    <a href="#" class="nav-mark" aria-label="Akhil V V Home">
      <span class="mark-badge">A</span>
      <span class="mark-text">akhil@vv:~$</span>
    </a>
    <div class="nav-links" id="navLinks">
      <a href="#about">About</a>
      <a href="#experience">Experience</a>
      <a href="#projects">Projects</a>
      <a href="#skills">Skills</a>
      <a href="#contact">Contact</a>
    </div>
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="navLinks">
      <span></span><span></span><span></span>
    </button>
  </nav>
</header>

<main>

  <!-- HERO -->
  <section class="hero" id="hero">
    <div class="wrap">
      <div class="hero-grid">
        <div class="hero-content">
          <span class="status-chip"><span class="dot"></span>Senior Software Engineer · Backend &amp; Distributed Systems</span>
          <h1>Building scalable backends &amp; <span class="grad">production-grade systems.</span></h1>
          <div class="role"><span id="roleText">Senior Software Engineer &amp; Technical Lead</span></div>
          <p class="lede">Senior software engineer focused on backend architecture, distributed systems, real-time logistics, high-throughput APIs, and production-ready enterprise platforms across India &amp; the Middle East.</p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="resume.pdf" download="Akhil_VV_Senior_Software_Engineer_Resume.pdf" aria-label="Download Akhil V V resume as PDF">Download Resume ↓</a>
            <a class="btn btn-ghost" href="#contact">Send a message</a>
            <a class="btn btn-ghost" href="https://linkedin.com/in/akhilvv" target="_blank" rel="noopener noreferrer">LinkedIn Profile ↗</a>
            <a class="btn btn-ghost" href="mailto:vvakhilkarun@gmail.com">Direct Email</a>
          </div>
        </div>

        <div class="hero-media">
          <div class="hero-portrait-stage">
            <div class="hero-portrait-aura" aria-hidden="true"></div>
            <div class="hero-portrait-backdrop" aria-hidden="true"></div>
            <img src="img1.png" alt="Akhil V V — Senior Software Engineer" class="hero-portrait-img" width="480" height="420" fetchpriority="high">
            <div class="hero-portrait-badge">
              <div class="badge-dot"></div>
              <div class="badge-text">
                <strong>Senior Backend &amp; Distributed Systems</strong>
                <span>Technical Team Lead · 20+ Production Deliverables</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- MARQUEE TECH TICKER -->
  <div class="marquee-shell" aria-hidden="true">
    <div class="marquee-track">
      <span>Laravel</span><span>Node.js</span><span>PHP</span><span>MySQL</span><span>PostgreSQL</span>
      <span>Redis Pub/Sub</span><span>Microservices</span><span>ZATCA Phase 2</span><span>REST APIs</span><span>Docker</span><span>Mapbox</span>
      <span>Laravel</span><span>Node.js</span><span>PHP</span><span>MySQL</span><span>PostgreSQL</span>
      <span>Redis Pub/Sub</span><span>Microservices</span><span>ZATCA Phase 2</span><span>REST APIs</span><span>Docker</span><span>Mapbox</span>
    </div>
  </div>

  <!-- 01 ABOUT / SYSTEM HIGHLIGHTS -->
  <section id="about">
    <div class="wrap">
      <div class="section-head"><span class="section-num">01</span><h2>System Highlights &amp; Approach</h2><span class="section-rule"></span></div>
      
      <div class="pillars-grid">
        <div class="pillar-card">
          <div class="pillar-icon">[01] ARCHITECTURE</div>
          <div class="pillar-title">Scalable Backends</div>
          <div class="pillar-desc">Clean modular architectures utilizing Laravel, Node.js, and Redis caching layers for high concurrent request volumes.</div>
        </div>
        <div class="pillar-card">
          <div class="pillar-icon">[02] DISPATCH</div>
          <div class="pillar-title">Real-Time Logistics</div>
          <div class="pillar-desc">Live driver location streaming, automated order routing, and event-driven background queues using Mapbox and WebSockets.</div>
        </div>
        <div class="pillar-card">
          <div class="pillar-icon">[03] REGULATORY</div>
          <div class="pillar-title">ZATCA Phase 2</div>
          <div class="pillar-desc">End-to-end implementation of Saudi Arabia e-invoicing: cryptographic XML signing, CSR generation, and reporting API integration.</div>
        </div>
        <div class="pillar-card">
          <div class="pillar-icon">[04] PRODUCTION</div>
          <div class="pillar-title">20+ Production Deliverables</div>
          <div class="pillar-desc">Proven track record delivering 20+ applications spanning enterprise logistics, multi-branch POS, retail ERPs, and ad management.</div>
        </div>
      </div>
    </div>
  </section>

  <!-- 02 EXPERIENCE -->
  <section id="experience">
    <div class="wrap">
      <div class="section-head"><span class="section-num">02</span><h2>Work Experience</h2><span class="section-rule"></span></div>

      <div class="timeline">
        <div class="job">
          <div class="job-card">
            <div class="job-top"><span class="job-title">Technical Team Lead &amp; Senior Backend Engineer</span><span class="job-dates">2021 — Present</span></div>
            <div class="job-company">ZakySoft Solutions / 4U Logistics · Kozhikode, Kerala</div>
            <ul>
              <li>Architect and oversee backend engineering for <strong>Leajlak</strong>, a high-volume logistics dispatch engine managing parcel creation, live GPS driver tracking, and route allocation.</li>
              <li>Engineered <strong>ZATCA Phase 2 e-invoicing integration</strong> for enterprise clients: handled cryptographic signatures, QR code generation, and automated compliance syncing.</li>
              <li>Spearheaded backend architecture for <strong>Farawlah POS</strong> and <strong>Filter Garage Management</strong>, handling real-time job cards, inventory controls, and payment workflows.</li>
              <li>Lead engineering teams, establish code quality standards, and design high-performance MySQL/PostgreSQL schemas with Redis-backed queues.</li>
            </ul>
          </div>
        </div>

        <div class="job">
          <div class="job-card">
            <div class="job-top"><span class="job-title">Software Developer</span><span class="job-dates">2019 — 2021</span></div>
            <div class="job-company">Hashwide Pvt. Ltd · Kochi, Kerala</div>
            <ul>
              <li>Developed backend systems for scalable real-estate platforms, including advanced search, mapping APIs, and secure payment integrations.</li>
              <li>Engineered multi-tenant role-based access control (RBAC) and optimized relational database schemas.</li>
            </ul>
          </div>
        </div>

        <div class="job">
          <div class="job-card">
            <div class="job-top"><span class="job-title">Software Development Engineer</span><span class="job-dates">2018 — 2019</span></div>
            <div class="job-company">Vandalay Business Solutions · Kochi, Kerala</div>
            <ul>
              <li>Engineered <strong>Adtopia</strong> ad campaign management platform with Angular and Laravel, integrating Meta and Google Ads performance APIs.</li>
            </ul>
          </div>
        </div>

        <div class="job">
          <div class="job-card">
            <div class="job-top"><span class="job-title">PHP Developer</span><span class="job-dates">2018 — 2019</span></div>
            <div class="job-company">ISPG Technologies India Pvt Ltd · Kochi, Kerala</div>
            <ul>
              <li>Developed RESTful API endpoints powering e-commerce and mobile clients with secure token authentication.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 03 PROJECTS -->
  <section id="projects">
    <div class="wrap">
      <div class="section-head"><span class="section-num">03</span><h2>Featured Engineering Projects</h2><span class="section-rule"></span></div>

      <div class="projects">
        <div class="project">
          <div>
            <span class="project-name">Leajlak Logistics Engine</span>
            <span class="project-loc">Logistics &amp; Dispatch · Saudi Arabia</span>
            <p class="project-desc">Complete last-mile delivery and dispatch platform featuring real-time driver tracking, automated order assignment, route maps, and automated merchant billing.</p>
          </div>
          <div class="tag-row">
            <span class="tag">Laravel</span><span class="tag">PostgreSQL</span><span class="tag">Redis</span><span class="tag">Mapbox</span><span class="tag">REST API</span>
          </div>
        </div>

        <div class="project">
          <div>
            <span class="project-name">Filter Garage Management</span>
            <span class="project-loc">Automotive ERP · Saudi Arabia</span>
            <p class="project-desc">Workshop management ERP handling digital job cards, parts inventory, multi-branch service scheduling, and native ZATCA Phase 2 tax invoicing.</p>
          </div>
          <div class="tag-row">
            <span class="tag">Laravel</span><span class="tag">MySQL</span><span class="tag">ZATCA Phase 2</span><span class="tag">Redis</span><span class="tag">RBAC</span>
          </div>
        </div>

        <div class="project">
          <div>
            <span class="project-name">Farawlah POS &amp; Commerce</span>
            <span class="project-loc">Retail &amp; Point of Sale · Saudi Arabia</span>
            <p class="project-desc">Omnichannel POS platform integrating in-store checkout, inventory reconciliation, order dispatch, and financial reporting dashboards.</p>
          </div>
          <div class="tag-row">
            <span class="tag">Laravel</span><span class="tag">Angular</span><span class="tag">MySQL</span><span class="tag">Leaflet</span><span class="tag">Redis</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 04 SKILLS -->
  <section id="skills">
    <div class="wrap">
      <div class="section-head"><span class="section-num">04</span><h2>Technical Expertise</h2><span class="section-rule"></span></div>

      <div class="skills-grid">
        <div class="skill-card"><h3>Core Backend</h3><div class="tag-row"><span class="tag">PHP</span><span class="tag">Laravel</span><span class="tag">Node.js</span><span class="tag">Express</span><span class="tag">RESTful APIs</span></div></div>
        <div class="skill-card"><h3>Databases &amp; Caching</h3><div class="tag-row"><span class="tag">MySQL</span><span class="tag">PostgreSQL</span><span class="tag">Redis (Queues &amp; Pub/Sub)</span><span class="tag">Query Tuning</span></div></div>
        <div class="skill-card"><h3>Architecture &amp; Design</h3><div class="tag-row"><span class="tag">Microservices</span><span class="tag">Event-Driven Design</span><span class="tag">System Design</span><span class="tag">RBAC</span></div></div>
        <div class="skill-card"><h3>Frontend &amp; Integration</h3><div class="tag-row"><span class="tag">JavaScript (ES6+)</span><span class="tag">React.js</span><span class="tag">Angular</span><span class="tag">Mapbox / Leaflet</span></div></div>
        <div class="skill-card"><h3>Compliance &amp; Tooling</h3><div class="tag-row"><span class="tag">ZATCA Phase 2</span><span class="tag">Docker</span><span class="tag">Git / GitHub Actions</span><span class="tag">Nginx</span><span class="tag">Linux</span></div></div>
        <div class="skill-card highlight"><h3>Engineering Leadership</h3><div class="tag-row"><span class="tag">Sprint Planning</span><span class="tag">Code Reviews</span><span class="tag">System Audits</span><span class="tag">Mentorship</span></div></div>
      </div>
    </div>
  </section>

  <!-- RESUME CTA -->
  <section class="resume-cta" aria-label="Employer resume download">
    <div class="wrap">
      <div class="resume-cta-card">
        <div>
          <div class="resume-cta-label">FOR EMPLOYERS &amp; RECRUITERS</div>
          <h2>Looking for the full resume?</h2>
          <p>Download the latest PDF resume for a concise overview of experience, technical expertise, and engineering leadership.</p>
        </div>
        <a class="btn btn-primary" href="resume.pdf" download="Akhil_VV_Senior_Software_Engineer_Resume.pdf">Download PDF Resume ↓</a>
      </div>
    </div>
  </section>

  <!-- 05 CONTACT & MAILER -->
  <section id="contact">
    <div class="wrap">
      <div class="section-head"><span class="section-num">05</span><h2>Get In Touch</h2><span class="section-rule"></span></div>

      <div class="contact-layout">
        <div class="contact-info">
          <h3>Let's discuss your next engineering project.</h3>
          <p>Available for Senior Backend Engineering, Technical Lead, and Architecture consulting opportunities.</p>
          
          <div class="quick-links">
            <div class="quick-link-item"><span>Email:</span> <a href="mailto:vvakhilkarun@gmail.com">vvakhilkarun@gmail.com</a></div>
            <div class="quick-link-item"><span>Phone:</span> <a href="tel:+918590449417">+91 8590449417</a></div>
            <div class="quick-link-item"><span>Location:</span> <span>Kozhikode, Kerala, India</span></div>
          </div>
        </div>

        <div class="contact-form">
          <!-- Server Flash Message Banner -->
          <div id="formAlert" class="alert-banner <?= !empty($flashMessage) ? 'show ' . htmlspecialchars($flashType) : '' ?>" role="alert">
            <?= htmlspecialchars($flashMessage ?? '') ?>
          </div>

          <form id="contactMailer" action="index.php#contact" method="POST" novalidate>
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <!-- Anti-Spam Honeypot (hidden from human visitors) -->
            <div style="position:absolute;left:-9999px;opacity:0;height:0;width:0;overflow:hidden;" aria-hidden="true">
              <input type="text" name="hp_confirm_code_val" id="hpHiddenVal" tabindex="-1" autocomplete="new-password" value="">
            </div>

            <div class="form-group">
              <label for="name">Your Name / Company <span style="color:var(--accent)">*</span></label>
              <input type="text" id="name" name="name" required minlength="2" maxlength="100" placeholder="e.g. John Doe / Acme Corp" autocomplete="name">
              <div class="field-error" id="nameError">Please enter your name (at least 2 characters).</div>
            </div>

            <div class="form-group">
              <label for="email">Your Email Address <span style="color:var(--accent)">*</span></label>
              <input type="email" id="email" name="email" required placeholder="name@company.com" autocomplete="email">
              <div class="field-error" id="emailError">Please enter a valid email address.</div>
            </div>

            <div class="form-group">
              <label for="subject">Opportunity / Project Topic</label>
              <input type="text" id="subject" name="subject" maxlength="150" placeholder="e.g. Senior Backend Role / Architecture Consulting">
              <div class="field-error" id="subjectError"></div>
            </div>

            <div class="form-group">
              <label for="message">Project Overview / Message <span style="color:var(--accent)">*</span></label>
              <textarea id="message" name="message" rows="5" required minlength="10" maxlength="5000" placeholder="Tell me about the role, stack, or platform requirements..."></textarea>
              <div class="field-error" id="messageError">Please enter a message (at least 10 characters).</div>
            </div>

            <button type="submit" id="submitBtn" class="btn btn-primary" style="width:100%; justify-content:center;">
              <span class="btn-spinner"></span>
              <span class="btn-text">Send Message</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </section>

</main>

<footer>
  <div class="wrap">
    <div class="footer-meta">Akhil V V — Senior Software Engineer · Backend &amp; Systems Architecture · Resume available for employers</div>
  </div>
</footer>

<script>
  /* ---------- Preloader with Session Memory ---------- */
  (function() {
    const preloader = document.getElementById('preloader');
    const logContainer = document.getElementById('loaderLogs');
    const bar = document.getElementById('loaderBar');
    const skipBtn = document.getElementById('loaderSkip');

    const hasBooted = sessionStorage.getItem('sys_booted');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (hasBooted || prefersReducedMotion) {
      if (preloader) {
        preloader.style.display = 'none';
        preloader.classList.add('fade-out');
      }
      return;
    }

    const logs = [
      { text: "> booting senior engineering workspace...", delay: 100 },
      { text: "> loading backend architecture [Laravel / Node.js]... OK", delay: 450, cls: "cyan" },
      { text: "> verifying data & event systems [PostgreSQL / Redis]... OK", delay: 900, cls: "cyan" },
      { text: "> checking compliance engine [ZATCA Phase 2]... OK", delay: 1350, cls: "accent" },
      { text: "> engineering profile ready.", delay: 1800, cls: "accent" }
    ];

    logs.forEach(item => {
      setTimeout(() => {
        if (!preloader.classList.contains('fade-out')) {
          const p = document.createElement('div');
          p.className = 'log-line' + (item.cls ? ' ' + item.cls : '');
          p.textContent = item.text;
          logContainer.appendChild(p);
        }
      }, item.delay);
    });

    let progress = 0;
    const timer = setInterval(() => {
      progress += 4;
      bar.style.width = Math.min(progress, 100) + '%';
      if (progress >= 100) {
        clearInterval(timer);
        dismissPreloader();
      }
    }, 60);

    function dismissPreloader() {
      sessionStorage.setItem('sys_booted', 'true');
      preloader.classList.add('fade-out');
      setTimeout(() => { preloader.style.display = 'none'; }, 550);
    }

    skipBtn.addEventListener('click', () => {
      clearInterval(timer);
      dismissPreloader();
    });

    // Safety timeout
    setTimeout(dismissPreloader, 3200);
  })();

  /* ---------- Scroll Progress Bar ---------- */
  const progressBar = document.getElementById('progressBar');
  window.addEventListener('scroll', () => {
    const h = document.documentElement;
    const pct = (h.scrollTop) / (h.scrollHeight - h.clientHeight) * 100;
    progressBar.style.width = pct + '%';
  }, { passive: true });

  /* ---------- Mobile Nav Toggle ---------- */
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', () => {
      const open = navLinks.classList.toggle('open');
      navToggle.classList.toggle('open', open);
      navToggle.setAttribute('aria-expanded', String(open));
    });

    navLinks.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      navLinks.classList.remove('open');
      navToggle.classList.remove('open');
      navToggle.setAttribute('aria-expanded', 'false');
    }));

    // Click outside to close
    document.addEventListener('click', (e) => {
      if (!navToggle.contains(e.target) && !navLinks.contains(e.target) && navLinks.classList.contains('open')) {
        navLinks.classList.remove('open');
        navToggle.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------- Navigation ScrollSpy (Active Link Highlighting) ---------- */
  (function() {
    const sections = document.querySelectorAll('section[id]');
    const navItems = document.querySelectorAll('.nav-links a');

    if ('IntersectionObserver' in window && sections.length > 0) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const id = entry.target.getAttribute('id');
            navItems.forEach(link => {
              const href = link.getAttribute('href');
              if (href === '#' + id) {
                link.classList.add('active');
              } else {
                link.classList.remove('active');
              }
            });
          }
        });
      }, { rootMargin: '-20% 0px -70% 0px', threshold: 0 });

      sections.forEach(sec => observer.observe(sec));
    }
  })();

  /* ---------- Cursor Glow Effect ---------- */
  const cursorGlow = document.getElementById('cursorGlow');
  if (window.matchMedia('(pointer: fine) and (prefers-reduced-motion: no-preference)').matches) {
    window.addEventListener('mousemove', (e) => {
      cursorGlow.style.opacity = '1';
      cursorGlow.style.transform = `translate(${e.clientX}px, ${e.clientY}px) translate(-50%, -50%)`;
    }, { passive: true });
    window.addEventListener('mouseleave', () => cursorGlow.style.opacity = '0');
  }

  /* ---------- Contact Form Handling with Validation & SMTP Integration ---------- */
  (function() {
    const form = document.getElementById('contactMailer');
    if (!form) return;

    const alertBanner = document.getElementById('formAlert');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');

    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const subjectInput = document.getElementById('subject');
    const messageInput = document.getElementById('message');
    const csrfInput = document.getElementById('csrfToken');

    const nameError = document.getElementById('nameError');
    const emailError = document.getElementById('emailError');
    const messageError = document.getElementById('messageError');

    // Email regex validation
    function isValidEmail(val) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    }

    function clearErrors() {
      [nameInput, emailInput, messageInput].forEach(el => el.classList.remove('is-invalid'));
      [nameError, emailError, messageError].forEach(el => el.classList.remove('show'));
    }

    function showAlert(msg, type) {
      alertBanner.textContent = msg;
      alertBanner.className = 'alert-banner show ' + type;
      alertBanner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Real-time input cleanup
    [nameInput, emailInput, messageInput].forEach(input => {
      input.addEventListener('input', () => {
        if (input.classList.contains('is-invalid')) {
          input.classList.remove('is-invalid');
          const errEl = document.getElementById(input.id + 'Error');
          if (errEl) errEl.classList.remove('show');
        }
      });
    });

    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      clearErrors();

      let hasError = false;
      const nameVal = nameInput.value.trim();
      const emailVal = emailInput.value.trim();
      const subjectVal = subjectInput.value.trim();
      const messageVal = messageInput.value.trim();

      // Client-side Validation
      if (!nameVal || nameVal.length < 2) {
        nameInput.classList.add('is-invalid');
        nameError.classList.add('show');
        hasError = true;
      }

      if (!emailVal || !isValidEmail(emailVal)) {
        emailInput.classList.add('is-invalid');
        emailError.classList.add('show');
        hasError = true;
      }

      if (!messageVal || messageVal.length < 10) {
        messageInput.classList.add('is-invalid');
        messageError.classList.add('show');
        hasError = true;
      }

      if (hasError) return;

      // Set Loading State
      submitBtn.classList.add('is-loading');
      submitBtn.disabled = true;
      btnText.textContent = 'Sending Message...';

      const payload = {
        name: nameVal,
        email: emailVal,
        subject: subjectVal || 'Portfolio Inquiry',
        message: messageVal,
        csrf_token: csrfInput.value,
        hp_confirm_code_val: (form.querySelector('[name="hp_confirm_code_val"]') || {}).value || ''
      };

      try {
        const response = await fetch('index.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify(payload)
        });

        const result = await response.json().catch(() => ({}));

        if (response.ok && result.success) {
          showAlert(result.message || 'Thank you! Your message has been sent successfully.', 'success');
          form.reset();
          if (result.csrf_token) {
            csrfInput.value = result.csrf_token;
          }
        } else {
          if (result.errors) {
            if (result.errors.name) { nameInput.classList.add('is-invalid'); nameError.textContent = result.errors.name; nameError.classList.add('show'); }
            if (result.errors.email) { emailInput.classList.add('is-invalid'); emailError.textContent = result.errors.email; emailError.classList.add('show'); }
            if (result.errors.message) { messageInput.classList.add('is-invalid'); messageError.textContent = result.errors.message; messageError.classList.add('show'); }
            showAlert('Please review and correct the highlighted fields.', 'error');
          } else {
            showAlert(result.error || 'Failed to send message. Please try again or reach out directly.', 'error');
          }
        }
      } catch (err) {
        // If fetch fails completely, gracefully fall back to standard form submission
        console.warn('AJAX fetch failed, falling back to standard submission', err);
        form.submit();
        return;
      } finally {
        submitBtn.classList.remove('is-loading');
        submitBtn.disabled = false;
        btnText.textContent = 'Send Message';
      }
    });
  })();
</script>

</body>
</html>