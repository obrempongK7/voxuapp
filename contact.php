<?php
/**
 * @author  Jcode | ObrempongK
 * Voxu — Contact Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$settings  = getPlatformSettings();
$appName   = $settings['app_name']    ?? 'Voxu';
$supportEmail = $settings['support_email'] ?? '';

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = sanitize($_POST['name']    ?? '');
    $email   = sanitize($_POST['email']   ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (!$name || !$email || !$subject || !$message) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($message) < 20) {
        $error = 'Message must be at least 20 characters.';
    } else {
        // Store message in notifications table as an admin notification (type: contact)
        // In production, also send email via mail() or SMTP
        DB::insert('notifications', [
            'user_id'    => 0, // 0 = system/admin
            'type'       => 'contact_form',
            'message'    => "Contact from {$name} ({$email}): [{$subject}] {$message}",
            'data'       => json_encode(['name'=>$name,'email'=>$email,'subject'=>$subject,'message'=>$message]),
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Send email if support email is configured
        if ($supportEmail) {
            $headers  = "From: Voxu Contact <noreply@{$_SERVER['HTTP_HOST']}>\r\n";
            $headers .= "Reply-To: {$email}\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body     = "New contact form submission on Voxu\n\n";
            $body    .= "Name:    {$name}\n";
            $body    .= "Email:   {$email}\n";
            $body    .= "Subject: {$subject}\n\n";
            $body    .= "Message:\n{$message}\n";
            @mail($supportEmail, "[Voxu Contact] {$subject}", $body, $headers);
        }

        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Contact Us — <?= clean($appName) ?></title>
  <meta name="description" content="Get in touch with the <?= clean($appName) ?> team."/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/voxu.css"/>
  <style>
    /* ── Page layout ─────────────────────────────── */
    .contact-page {
      padding-top: 80px;
      min-height: 100vh;
      background: var(--bg);
    }
    .contact-wrap {
      max-width: 1000px;
      margin: 0 auto;
      padding: 48px 24px 80px;
      display: grid;
      grid-template-columns: 1fr 1.6fr;
      gap: 48px;
      align-items: start;
    }
    /* ── Left side info ──────────────────────────── */
    .contact-info {}
    .contact-label {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: var(--purple);
      margin-bottom: 10px;
    }
    .contact-title {
      font-family: 'Poppins', sans-serif;
      font-size: 36px;
      font-weight: 800;
      line-height: 1.15;
      color: #fff;
      margin-bottom: 14px;
    }
    .contact-title span { color: var(--purple); }
    .contact-desc {
      font-size: 15px;
      color: var(--text2);
      line-height: 1.7;
      margin-bottom: 32px;
    }
    .contact-methods {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .contact-method {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 16px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      transition: .2s;
    }
    .contact-method:hover { border-color: rgba(108,59,255,.4); }
    .cm-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }
    .cm-title { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 3px; }
    .cm-val   { font-size: 13px; color: var(--text2); word-break: break-all; }
    .cm-val a { color: var(--blue); text-decoration: none; }
    .cm-val a:hover { text-decoration: underline; }
    /* ── Right side form ─────────────────────────── */
    .contact-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 36px;
    }
    .form-title {
      font-size: 20px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 6px;
    }
    .form-subtitle {
      font-size: 14px;
      color: var(--text2);
      margin-bottom: 24px;
    }
    .contact-form .fg {
      margin-bottom: 18px;
    }
    .contact-form .fl {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: var(--text2);
      margin-bottom: 7px;
    }
    .contact-form .fi {
      width: 100%;
      background: var(--bg2);
      border: 1px solid var(--border);
      color: #fff;
      padding: 12px 14px;
      border-radius: 8px;
      font-size: 14px;
      outline: none;
      font-family: inherit;
      transition: .2s;
      box-sizing: border-box;
    }
    .contact-form .fi:focus {
      border-color: var(--purple);
      box-shadow: 0 0 0 3px var(--purple-l);
    }
    .contact-form textarea.fi {
      resize: vertical;
      min-height: 130px;
    }
    .form-row-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }
    .submit-btn {
      width: 100%;
      padding: 14px;
      background: var(--purple);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      transition: .2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .submit-btn:hover { background: var(--purple-d); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(108,59,255,.4); }
    .submit-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
    /* ── Alerts ──────────────────────────────────── */
    .al {
      padding: 14px 16px;
      border-radius: 10px;
      font-size: 14px;
      margin-bottom: 20px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }
    .al-success {
      background: var(--green-l);
      border: 1px solid var(--green);
      color: var(--green);
    }
    .al-error {
      background: var(--danger-l);
      border: 1px solid var(--danger);
      color: var(--danger);
    }
    .al-icon { font-size: 18px; flex-shrink: 0; }
    /* ── FAQ teaser ──────────────────────────────── */
    .faq-teaser {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 18px;
      margin-top: 28px;
      text-align: center;
    }
    .faq-teaser p { font-size: 13px; color: var(--text2); margin-bottom: 10px; }
    .faq-teaser a {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 18px;
      background: transparent;
      border: 1px solid var(--border2);
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      color: #fff;
      text-decoration: none;
      transition: .2s;
    }
    .faq-teaser a:hover { background: var(--purple-l); border-color: var(--purple); color: var(--purple); }
    /* ── Responsive ──────────────────────────────── */
    @media (max-width: 768px) {
      .contact-wrap {
        grid-template-columns: 1fr;
        gap: 32px;
        padding: 32px 16px 60px;
      }
      .contact-card { padding: 24px 20px; }
      .form-row-2 { grid-template-columns: 1fr; gap: 0; }
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav style="
  position:fixed;top:0;left:0;right:0;height:64px;
  background:rgba(11,11,15,.92);backdrop-filter:blur(16px);
  border-bottom:1px solid var(--border);z-index:200;
  display:flex;align-items:center;padding:0 24px;gap:16px;
">
  <a href="/" style="font-family:'Poppins',sans-serif;font-size:22px;font-weight:800;color:#fff;text-decoration:none">
    Vo<span style="color:var(--purple)">xu</span>
  </a>
  <div style="margin-left:auto;display:flex;gap:10px">
    <?php if (auth()): ?>
      <a href="/dashboard/" class="btn btn-secondary btn-sm">Dashboard</a>
    <?php else: ?>
      <a href="/auth/login.php"    class="btn btn-secondary btn-sm">Log In</a>
      <a href="/auth/register.php" class="btn btn-primary btn-sm">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<div class="contact-page">
  <div class="contact-wrap">

    <!-- LEFT: Info -->
    <div class="contact-info">
      <div class="contact-label">Get in Touch</div>
      <h1 class="contact-title">We'd love to<br/><span>hear from you</span></h1>
      <p class="contact-desc">
        Have a question, feedback, or partnership inquiry? Send us a message and we'll get back to you as soon as possible.
      </p>

      <div class="contact-methods">
        <?php if ($supportEmail): ?>
        <div class="contact-method">
          <div class="cm-icon" style="background:rgba(108,59,255,.12)">📧</div>
          <div>
            <div class="cm-title">Email Support</div>
            <div class="cm-val"><a href="mailto:<?= clean($supportEmail) ?>"><?= clean($supportEmail) ?></a></div>
          </div>
        </div>
        <?php endif; ?>

        <div class="contact-method">
          <div class="cm-icon" style="background:rgba(0,209,255,.1)">⏱</div>
          <div>
            <div class="cm-title">Response Time</div>
            <div class="cm-val">Usually within 24–48 hours on business days</div>
          </div>
        </div>

        <div class="contact-method">
          <div class="cm-icon" style="background:rgba(0,255,156,.1)">🛡</div>
          <div>
            <div class="cm-title">Account Issues</div>
            <div class="cm-val">For urgent account or payment issues, include your username in the message</div>
          </div>
        </div>

        <div class="contact-method">
          <div class="cm-icon" style="background:rgba(255,184,48,.1)">🤝</div>
          <div>
            <div class="cm-title">Partnerships & Business</div>
            <div class="cm-val">For B2B, NGO, or sponsorship inquiries, select "Partnership" as the subject</div>
          </div>
        </div>
      </div>

      <div class="faq-teaser">
        <p>Quick question? Check our FAQ first.</p>
        <a href="/#faq">View FAQ →</a>
      </div>
    </div>

    <!-- RIGHT: Form -->
    <div class="contact-card">
      <div class="form-title">Send a Message</div>
      <div class="form-subtitle">Fill in the form below and we'll respond promptly.</div>

      <?php if ($sent): ?>
        <div class="al al-success">
          <span class="al-icon">✓</span>
          <div>
            <strong>Message sent successfully!</strong><br/>
            Thank you, <?= clean($_POST['name'] ?? 'there') ?>. We'll get back to you within 24–48 hours.
          </div>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="al al-error">
          <span class="al-icon">⚠</span>
          <div><?= clean($error) ?></div>
        </div>
      <?php endif; ?>

      <?php if (!$sent): ?>
      <form method="POST" action="/contact.php" class="contact-form" id="contactForm">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>"/>

        <div class="form-row-2">
          <div class="fg">
            <label class="fl" for="cn">Full Name <span style="color:var(--danger)">*</span></label>
            <input class="fi" type="text" id="cn" name="name"
              value="<?= clean($_POST['name'] ?? '') ?>"
              placeholder="Your name" required maxlength="100"/>
          </div>
          <div class="fg">
            <label class="fl" for="ce">Email Address <span style="color:var(--danger)">*</span></label>
            <input class="fi" type="email" id="ce" name="email"
              value="<?= clean($_POST['email'] ?? '') ?>"
              placeholder="you@example.com" required/>
          </div>
        </div>

        <div class="fg">
          <label class="fl" for="cs">Subject <span style="color:var(--danger)">*</span></label>
          <select class="fi" id="cs" name="subject" required>
            <option value="" disabled <?= empty($_POST['subject'])?'selected':'' ?>>Choose a topic…</option>
            <option value="General Inquiry"    <?= ($_POST['subject']??'')==='General Inquiry'?'selected':'' ?>>General Inquiry</option>
            <option value="Account Issue"      <?= ($_POST['subject']??'')==='Account Issue'?'selected':'' ?>>Account / Login Issue</option>
            <option value="Payment / Withdrawal" <?= ($_POST['subject']??'')==='Payment / Withdrawal'?'selected':'' ?>>Payment / Withdrawal</option>
            <option value="Technical Problem"  <?= ($_POST['subject']??'')==='Technical Problem'?'selected':'' ?>>Technical Problem / Bug</option>
            <option value="Partnership"        <?= ($_POST['subject']??'')==='Partnership'?'selected':'' ?>>Partnership / Business</option>
            <option value="Content Report"     <?= ($_POST['subject']??'')==='Content Report'?'selected':'' ?>>Report Content</option>
            <option value="Feedback"           <?= ($_POST['subject']??'')==='Feedback'?'selected':'' ?>>Feedback / Suggestion</option>
            <option value="Other"              <?= ($_POST['subject']??'')==='Other'?'selected':'' ?>>Other</option>
          </select>
        </div>

        <?php if (auth()): ?>
          <input type="hidden" name="username" value="<?= clean(auth()['username']) ?>"/>
        <?php endif; ?>

        <div class="fg">
          <label class="fl" for="cm">Message <span style="color:var(--danger)">*</span></label>
          <textarea class="fi" id="cm" name="message"
            placeholder="Describe your issue or question in detail…"
            required minlength="20" maxlength="2000"
          ><?= clean($_POST['message'] ?? '') ?></textarea>
          <div style="font-size:11px;color:var(--text3);margin-top:5px" id="charCount">0 / 2000</div>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="22" y1="2" x2="11" y2="13"/>
            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
          Send Message
        </button>
      </form>
      <?php else: ?>
        <div style="text-align:center;padding:20px 0">
          <a href="/" class="btn btn-primary" style="display:inline-flex">← Back to Home</a>
          <a href="/contact.php" class="btn btn-secondary" style="display:inline-flex;margin-top:10px">Send Another Message</a>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- FOOTER -->
<footer style="border-top:1px solid var(--border);padding:28px 24px;text-align:center">
  <div style="font-size:13px;color:var(--text3)">
    © <?= date('Y') ?> <?= clean($appName) ?> · 
    <a href="/privacy.php" style="color:var(--text2);text-decoration:none">Privacy</a> · 
    <a href="/terms.php"   style="color:var(--text2);text-decoration:none">Terms</a>
  </div>
</footer>

<div id="toast-container"></div>
<script src="/assets/js/voxu.js"></script>
<script>
// Character counter for message textarea
const msgArea = document.getElementById('cm');
const charCount = document.getElementById('charCount');
if (msgArea && charCount) {
  const update = () => {
    const n = msgArea.value.length;
    charCount.textContent = n + ' / 2000';
    charCount.style.color = n > 1800 ? 'var(--warning)' : 'var(--text3)';
  };
  msgArea.addEventListener('input', update);
  update();
}

// Loading state on submit
const form = document.getElementById('contactForm');
if (form) {
  form.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0" stroke-dasharray="56" stroke-dashoffset="0"/></svg> Sending…';
    btn.disabled = true;
  });
}
</script>
</body>
</html>
