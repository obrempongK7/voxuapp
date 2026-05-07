<?php
/**
 * Voxu — Shared Admin Sidebar Partial
 * @author  Jcode | ObrempongK
 * Include this in every admin page: require_once __DIR__ . '/../includes/admin_sidebar.php';
 * Requires $admin (array) and $activeMenu (string) to be set before include.
 */
if (!defined('APP_NAME')) die();

try { $pendingWithdrawals = DB::count('withdrawals', 'status="pending"'); } catch (Throwable) { $pendingWithdrawals = 0; }
try { $openReports        = DB::count('reports', 'status="open"'); }          catch (Throwable) { $openReports = 0; }
try { $unreadNotifs       = DB::count('notifications', 'user_id=0 AND is_read=0'); } catch (Throwable) { $unreadNotifs = 0; }
?>
<aside class="admin-sidebar" id="sidebar">
  <div class="admin-logo">
    <div class="logo-text">Vo<span>xu</span></div>
    <div class="admin-tag">Admin Panel</div>
  </div>
  <nav class="sidebar-nav">

    <div class="sidebar-section">Main</div>
    <a href="/admin/"              class="sidebar-link <?= $activeMenu==='dashboard'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard</a>

    <div class="sidebar-section">Users</div>
    <a href="/admin/users.php"     class="sidebar-link <?= $activeMenu==='users'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Users</a>
    <a href="/admin/reports.php"   class="sidebar-link <?= $activeMenu==='reports'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Reports
      <?php if ($openReports > 0): ?><span class="badge-count"><?= $openReports ?></span><?php endif; ?></a>

    <div class="sidebar-section">Content</div>
    <a href="/admin/posts.php"     class="sidebar-link <?= $activeMenu==='posts'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>Voice Posts</a>
    <a href="/admin/statuses.php"  class="sidebar-link <?= $activeMenu==='statuses'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>Statuses</a>
    <a href="/admin/campaigns.php" class="sidebar-link <?= $activeMenu==='campaigns'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Campaigns</a>

    <div class="sidebar-section">Finance</div>
    <a href="/admin/finance.php"      class="sidebar-link <?= $activeMenu==='finance'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Transactions</a>
       <a href="/admin/subscriptions.php"      class="sidebar-link <?= $activeMenu==='subscription'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>Subscription</a>
    <a href="/admin/withdrawals.php"  class="sidebar-link <?= $activeMenu==='withdrawals'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>Withdrawals
      <?php if ($pendingWithdrawals > 0): ?><span class="badge-count"><?= $pendingWithdrawals ?></span><?php endif; ?></a>

    <div class="sidebar-section">Content</div>
    <a href="/admin/advertising.php" class="sidebar-link <?= $activeMenu==='advertising'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/><path d="M6 8h.01M9 8h.01M12 8h.01"/></svg>Advertising</a>

    <div class="sidebar-section">Comms</div>
    <a href="/admin/notifications.php" class="sidebar-link <?= $activeMenu==='notifications'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>Notifications
      <?php if ($unreadNotifs > 0): ?><span class="badge-count"><?= $unreadNotifs ?></span><?php endif; ?></a>
    <a href="/admin/emails.php"        class="sidebar-link <?= $activeMenu==='emails'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Email Users</a>

    <div class="sidebar-section">System</div>
    <a href="/admin/settings.php"  class="sidebar-link <?= $activeMenu==='settings'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Settings</a>
    <a href="/admin/admins.php"    class="sidebar-link <?= $activeMenu==='admins'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Admins &amp; Roles</a>
    <a href="/admin/logs.php"      class="sidebar-link <?= $activeMenu==='logs'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Audit Logs</a>
      <a href="/admin/pages.php"      class="sidebar-link <?= $activeMenu==='pages'?'active':'' ?>">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Page Editor</a>

  </nav>
  <div class="sidebar-footer">
    <div style="font-size:11px;color:var(--text3)">Logged in as</div>
    <div style="font-size:13px;font-weight:600;color:var(--text)"><?= clean($admin['name']) ?></div>
    <div style="font-size:11px;color:var(--purple);margin-top:2px"><?= ucfirst($admin['role']) ?></div>
    <a href="/admin/logout.php" style="display:block;margin-top:12px;font-size:12px;color:var(--danger);text-decoration:none">← Logout</a>
  </div>
</aside>
