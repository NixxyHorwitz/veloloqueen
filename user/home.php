<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

$user = auth_user($pdo);
$is_guest = false;
if (!$user) {
    $is_guest = true;
    $user = [
        'id' => 0, 'username' => 'Tamu', 'balance_wd' => 0, 'balance_dep' => 0,
        'membership_id' => null, 'membership_expires_at' => null, 'referral_code' => '-', 'is_promotor' => 0,
    ];
}

if (is_maintenance($pdo) && !auth_admin()) {
    $maintenance_msg = setting($pdo, 'maintenance_message', 'Sistem sedang dalam perbaikan.');
    require dirname(__DIR__) . '/user/maintenance.php'; exit;
}

track_pageview($pdo, parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

$watch_limit = $is_guest ? 0 : user_watch_limit($pdo, $user);
$watch_today = $is_guest ? 0 : user_watch_today($pdo, $user);

if ($is_guest) {
    $videos = $pdo->query("SELECT v.* FROM videos v WHERE v.is_active=1 ORDER BY v.sort_order ASC, v.id DESC LIMIT 6")->fetchAll();
    $history = []; $notif_preview = []; $notif_unread = 0;
} else {
    $videos = $pdo->prepare(
        "SELECT v.* FROM videos v WHERE v.is_active=1
           AND v.id NOT IN (SELECT video_id FROM watch_history WHERE user_id=? AND DATE(watched_at)=CURDATE())
         ORDER BY v.sort_order ASC, v.id DESC LIMIT 6"
    );
    $videos->execute([$user['id']]); $videos = $videos->fetchAll();

    $history = $pdo->prepare(
        "SELECT wh.reward_given, wh.watched_at, v.title FROM watch_history wh
         JOIN videos v ON v.id=wh.video_id WHERE wh.user_id=? ORDER BY wh.watched_at DESC LIMIT 4"
    );
    $history->execute([$user['id']]); $history = $history->fetchAll();

    $notif_preview = []; $notif_unread = 0;
    try {
        $uid = $user['id'];
        $np = $pdo->prepare(
            "SELECT n.* FROM notifications n LEFT JOIN notification_reads nr ON nr.notification_id=n.id AND nr.user_id=?
             WHERE nr.id IS NULL AND (n.target_type='all' OR (n.target_user_ids IS NOT NULL AND JSON_CONTAINS(n.target_user_ids, JSON_QUOTE(?))))
               AND (n.expires_at IS NULL OR n.expires_at > NOW()) ORDER BY n.created_at DESC LIMIT 3"
        );
        $np->execute([$uid, (string)$uid]); $notif_preview = $np->fetchAll();
        $nc = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications n LEFT JOIN notification_reads nr ON nr.notification_id=n.id AND nr.user_id=?
             WHERE nr.id IS NULL AND (n.target_type='all' OR (n.target_user_ids IS NOT NULL AND JSON_CONTAINS(n.target_user_ids, JSON_QUOTE(?))))
               AND (n.expires_at IS NULL OR n.expires_at > NOW())"
        );
        $nc->execute([$uid, (string)$uid]); $notif_unread = (int)$nc->fetchColumn();
    } catch (\Throwable) {}
}

$membership_name = 'Free';
if ($user['membership_id'] && $user['membership_expires_at'] && strtotime($user['membership_expires_at']) > time()) {
    $ms = $pdo->prepare("SELECT name FROM memberships WHERE id=?");
    $ms->execute([$user['membership_id']]);
    $membership_name = $ms->fetchColumn() ?: 'Free';
}

$showcase_memberships = $pdo->query("SELECT * FROM memberships WHERE is_active=1 AND price > 0 ORDER BY sort_order ASC")->fetchAll();

$wd_require_level = setting($pdo, 'wd_require_level', '0') === '1';
$wd_min_level  = (int) setting($pdo, 'wd_min_level', '0');
$user_level    = user_membership_level($pdo, $user);
$level_blocked = $wd_require_level && $wd_min_level > 0 && $user_level < $wd_min_level;

$pageTitle  = 'Beranda';
$activePage = 'home';
require dirname(__DIR__) . '/partials/header.php';
?>

<style>
/* ══════════════════════════════════════════════════════
   CASUAL GAME UI — HYPER-CASUAL STYLE HOME PAGE
   ══════════════════════════════════════════════════════ */

/* ── Base page background ── */
body { background: #f97316 !important; }

.cg-page {
  padding: 0 0 24px;
  min-height: 100vh;
  background: #f97316;
  font-family: 'Nunito', 'Inter', sans-serif;
}

/* ── Flash ── */
.flash-alert {
  margin: 10px 14px;
  padding: 10px 14px;
  border-radius: 14px;
  font-size: 12px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 8px;
  border: 2.5px solid;
}
.flash-alert--err { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }

/* ══ 1. HERO BANNER ══ */
.hero-banner {
  background: linear-gradient(180deg, #fbbf24 0%, #f97316 100%);
  border-radius: 0 0 32px 32px;
  padding: 16px 16px 24px;
  position: relative;
  overflow: hidden;
  border-bottom: 4px solid #ea580c;
}
.hero-banner::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 6px;
  background: repeating-linear-gradient(90deg, rgba(255,255,255,0.25) 0px, rgba(255,255,255,0.25) 8px, transparent 8px, transparent 16px);
}
.hero-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}
.hero-avatar-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}
.hero-avatar {
  width: 46px; height: 46px;
  background: linear-gradient(135deg, #fff, #fde68a);
  border: 3px solid #fff;
  border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; font-weight: 900; color: #92400e;
  box-shadow: 0 4px 0 rgba(0,0,0,0.15);
  flex-shrink: 0;
}
.hero-name {
  font-size: 15px; font-weight: 900; color: #fff;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
  line-height: 1.2;
}
.hero-tier {
  display: inline-flex; align-items: center; gap: 3px;
  background: rgba(255,255,255,0.25);
  border: 1.5px solid rgba(255,255,255,0.4);
  border-radius: 20px;
  padding: 2px 8px;
  font-size: 10px; font-weight: 900; color: #fff;
  margin-top: 2px;
}
.hero-cta {
  display: flex; align-items: center; gap: 5px;
  background: #fff;
  border: 3px solid #fde68a;
  border-radius: 14px;
  padding: 8px 14px;
  color: #ea580c; font-size: 12px; font-weight: 900;
  text-decoration: none;
  box-shadow: 0 4px 0 #d97706;
  transition: transform 0.1s;
  animation: bounce-cta 2s ease infinite;
}
.hero-cta:active { transform: translateY(3px); box-shadow: none; }
@keyframes bounce-cta {
  0%,100% { transform: translateY(0); }
  50% { transform: translateY(-3px); }
}

/* ── Mascot area ── */
.hero-mascot-area {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 10px;
  margin-bottom: 10px;
  min-height: 70px;
}
.hero-mascot-emoji {
  font-size: 56px;
  filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
  animation: mascot-float 3s ease-in-out infinite;
}
@keyframes mascot-float {
  0%,100% { transform: translateY(0) rotate(-2deg); }
  50% { transform: translateY(-8px) rotate(2deg); }
}
.hero-coins {
  display: flex; flex-direction: column; gap: 4px;
}
.hero-coin-pile {
  font-size: 28px;
  animation: coin-spin 2s ease-in-out infinite;
}
@keyframes coin-spin {
  0%,100% { transform: scale(1); }
  50% { transform: scale(1.15); }
}

/* ── Balance card on hero ── */
.hero-balance-card {
  background: rgba(255,255,255,0.2);
  border: 2.5px solid rgba(255,255,255,0.5);
  border-radius: 18px;
  padding: 12px 14px;
  backdrop-filter: blur(4px);
}
.hero-balance-label {
  font-size: 11px; font-weight: 800; color: rgba(255,255,255,0.8);
  text-transform: uppercase; letter-spacing: 0.5px;
  margin-bottom: 4px;
}
.hero-balance-value {
  font-size: 28px; font-weight: 900; color: #fff;
  text-shadow: 0 2px 6px rgba(0,0,0,0.2);
  line-height: 1;
}
.hero-balance-sub {
  font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.75);
  margin-top: 3px;
}

/* ── Watch progress bar ── */
.hero-progress {
  background: rgba(255,255,255,0.15);
  border: 2px solid rgba(255,255,255,0.3);
  border-radius: 14px;
  padding: 10px 12px;
  margin-top: 12px;
}
.hero-progress-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 6px;
}
.hero-progress-label {
  font-size: 10px; font-weight: 800; color: rgba(255,255,255,0.85);
  display: flex; align-items: center; gap: 4px;
}
.hero-progress-count {
  font-size: 12px; font-weight: 900; color: #fff;
}
.hero-progress-track {
  height: 10px;
  background: rgba(0,0,0,0.2);
  border-radius: 20px;
  overflow: hidden;
}
.hero-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #fff 0%, #fde68a 100%);
  border-radius: 20px;
  transition: width 0.5s ease;
  position: relative;
}
.hero-progress-fill::after {
  content: '';
  position: absolute; top: 0; left: 0; right: 0;
  height: 50%;
  background: rgba(255,255,255,0.4);
  border-radius: 20px;
}

/* ══ 2. CONTENT AREA ══ */
.cg-content {
  padding: 20px 14px 0;
}

/* ══ 3. BALANCE TILES ══ */
.bal-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 16px;
}
.bal-tile {
  background: #fff;
  border: 3px solid #0f172a;
  border-radius: 20px;
  padding: 14px 12px;
  box-shadow: 0 6px 0 #0f172a;
  text-decoration: none;
  color: inherit;
  display: block;
  transition: transform 0.1s;
  position: relative;
  overflow: hidden;
}
.bal-tile:active { transform: translateY(4px); box-shadow: 0 2px 0 #0f172a; }
.bal-tile--wd  { border-color: #064e3b; box-shadow: 0 6px 0 #064e3b; }
.bal-tile--dep { border-color: #1e3a8a; box-shadow: 0 6px 0 #1e3a8a; }
.bal-tile__bg-deco {
  position: absolute; bottom: -12px; right: -12px;
  font-size: 52px; opacity: 0.08;
  pointer-events: none;
}
.bal-tile__header {
  display: flex; align-items: center; gap: 6px;
  margin-bottom: 6px;
}
.bal-tile__icon {
  width: 30px; height: 30px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
  border: 2px solid rgba(0,0,0,0.1);
}
.bal-tile--wd  .bal-tile__icon { background: #d1fae5; }
.bal-tile--dep .bal-tile__icon { background: #dbeafe; }
.bal-tile__label { font-size: 10px; font-weight: 800; color: #64748b; }
.bal-tile__val {
  font-size: 16px; font-weight: 900; color: #0f172a;
  line-height: 1; margin-bottom: 8px;
}
.bal-tile__btn {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 11px; font-weight: 900;
  padding: 5px 12px;
  border-radius: 20px;
  border: 2px solid;
  text-decoration: none;
}
.bal-tile--wd  .bal-tile__btn { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
.bal-tile--dep .bal-tile__btn { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }

/* ══ 4. QUICK ACTIONS ══ */
.section-title {
  font-size: 14px; font-weight: 900; color: #fff;
  text-shadow: 0 2px 4px rgba(0,0,0,0.15);
  margin-bottom: 10px;
  display: flex; align-items: center; gap: 6px;
}
.section-title-pill {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 10px;
}
.section-title-pill .st { font-size: 14px; font-weight: 900; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 6px; }
.section-title-pill .sl {
  font-size: 10px; font-weight: 900;
  background: rgba(255,255,255,0.2);
  border: 2px solid rgba(255,255,255,0.4);
  color: #fff;
  padding: 3px 10px;
  border-radius: 20px;
  text-decoration: none;
}
.section-title-pill .sl-badge {
  background: #ef4444; color: #fff;
  font-size: 9px; font-weight: 900;
  padding: 1px 6px; border-radius: 10px;
  margin-left: 4px;
}

.qa-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin-bottom: 16px;
}
.qa-item {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  text-decoration: none;
}
.qa-item__icon {
  width: 52px; height: 52px;
  border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  font-size: 24px;
  border: 3px solid rgba(0,0,0,0.12);
  transition: transform 0.1s;
  box-shadow: 0 5px 0 rgba(0,0,0,0.15);
}
.qa-item:active .qa-item__icon { transform: translateY(4px); box-shadow: none; }
.qa-item__label {
  font-size: 10px; font-weight: 900; color: #fff;
  text-align: center;
  text-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

/* ══ 5. CONTENT CARD WRAPPER (white bg) ══ */
.cg-card-section {
  background: #fff8f0;
  border: 3px solid #ea580c;
  border-radius: 24px;
  padding: 14px;
  margin-bottom: 14px;
  box-shadow: 0 6px 0 #ea580c;
}
.cg-card-section--white {
  background: #fff;
  border-color: #0f172a;
  box-shadow: 0 6px 0 #0f172a;
}
.cg-card-section--green {
  background: #f0fdf4;
  border-color: #064e3b;
  box-shadow: 0 6px 0 #064e3b;
}
.cs-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 12px;
}
.cs-title {
  display: flex; align-items: center; gap: 6px;
  font-size: 13px; font-weight: 900; color: #0f172a;
}
.cs-title i { font-size: 17px; }
.cs-link {
  font-size: 10px; font-weight: 900;
  background: #fff3e0; color: #ea580c;
  border: 2px solid #fb923c;
  padding: 3px 10px;
  border-radius: 20px;
  text-decoration: none;
}

/* ══ 6. VIDEO SCROLL ══ */
.vid-scroll {
  display: flex; gap: 10px;
  overflow-x: auto; padding-bottom: 6px;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
  margin: 0 -14px;
  padding-left: 14px;
  padding-right: 14px;
}
.vid-scroll::-webkit-scrollbar { display: none; }
.vid-card {
  flex: 0 0 155px;
  scroll-snap-align: start;
  text-decoration: none;
  display: flex; flex-direction: column;
  background: #fff;
  border: 2.5px solid #0f172a;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 5px 0 #0f172a;
  transition: transform 0.1s;
}
.vid-card:active { transform: translateY(4px); box-shadow: none; }
.vid-card__thumb {
  position: relative; aspect-ratio: 16/9; background: #000; overflow: hidden;
}
.vid-card__thumb img { width: 100%; height: 100%; object-fit: cover; opacity: 0.9; }
.vid-card__badge {
  position: absolute; bottom: 5px; left: 5px;
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: #fff; font-size: 9px; font-weight: 900;
  padding: 3px 7px; border-radius: 8px;
  border: 1.5px solid rgba(255,255,255,0.4);
  box-shadow: 0 2px 0 #15803d;
}
.vid-card__play {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  background: rgba(0,0,0,0.15);
}
.vid-card__play i { font-size: 30px; color: #fff; filter: drop-shadow(0 2px 6px rgba(0,0,0,0.5)); }
.vid-card__body { padding: 8px; }
.vid-card__title {
  font-size: 11px; font-weight: 800; color: #0f172a;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  overflow: hidden; line-height: 1.3; margin-bottom: 5px;
}
.vid-card__meta {
  display: flex; align-items: center; justify-content: space-between;
  font-size: 10px; font-weight: 800; color: #64748b;
}

/* ── Empty video ── */
.vid-done {
  background: linear-gradient(135deg, #d1fae5, #a7f3d0);
  border: 2.5px solid #6ee7b7;
  border-radius: 18px;
  padding: 16px;
  display: flex; align-items: center; gap: 12px;
  box-shadow: 0 4px 0 #6ee7b7;
}
.vid-done i { font-size: 30px; color: #059669; flex-shrink: 0; }

/* ══ 7. NOTIFICATIONS ══ */
.notif-list { display: flex; flex-direction: column; gap: 0; }
.notif-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 0;
  border-bottom: 1.5px dashed #fed7aa;
}
.notif-item:last-child { border-bottom: none; padding-bottom: 0; }
.notif-dot {
  width: 10px; height: 10px; border-radius: 50%;
  flex-shrink: 0; margin-top: 3px;
  border: 2px solid rgba(0,0,0,0.1);
}
.notif-body { flex: 1; min-width: 0; }
.notif-title {
  font-size: 11px; font-weight: 900; color: #0f172a;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.notif-msg { font-size: 10px; color: #64748b; font-weight: 700; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ══ 8. ACTIVITY FEED ══ */
.activity-list { display: flex; flex-direction: column; gap: 0; }
.activity-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 0;
  border-bottom: 1.5px dashed #fed7aa;
}
.activity-item:last-child { border-bottom: none; padding-bottom: 0; }
.activity-icon {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, #fef3c7, #fde68a);
  border: 2px solid #fbbf24;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; color: #92400e;
  flex-shrink: 0;
  box-shadow: 0 3px 0 #d97706;
}
.activity-text { flex: 1; min-width: 0; }
.activity-title { font-size: 11px; font-weight: 800; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.activity-date { font-size: 10px; color: #94a3b8; font-weight: 700; }
.activity-amount { font-size: 13px; font-weight: 900; color: #059669; white-space: nowrap; }

/* ══ 9. REFERRAL ══ */
.ref-card {
  display: flex; align-items: center; gap: 10px;
}
.ref-code {
  flex: 1; min-width: 0;
}
.ref-code__label { font-size: 10px; font-weight: 700; color: #64748b; }
.ref-code__val { font-size: 16px; font-weight: 900; color: #0f172a; letter-spacing: 2px; }
.ref-btn {
  background: linear-gradient(135deg, #fde047, #f59e0b);
  border: 2.5px solid #d97706;
  border-radius: 14px; padding: 8px 14px;
  font-size: 11px; font-weight: 900; color: #78350f;
  cursor: pointer; display: flex; align-items: center; gap: 5px;
  flex-shrink: 0;
  box-shadow: 0 4px 0 #b45309;
  transition: transform 0.1s;
}
.ref-btn:active { transform: translateY(3px); box-shadow: none; }

/* ══ 10. MEMBERSHIP CARDS ══ */
.m-card {
  background: #fff;
  border: 3px solid #0f172a;
  border-radius: 20px;
  box-shadow: 0 6px 0 #0f172a;
  padding: 14px;
  margin-bottom: 12px;
  position: relative;
  text-decoration: none;
  display: block;
  transition: transform 0.1s;
}
.m-card:active { transform: translateY(4px); box-shadow: 0 2px 0 #0f172a; }
.m-card--0 { border-color: #64748b; box-shadow: 0 6px 0 #64748b; }
.m-card--1 { border-color: #0ea5e9; box-shadow: 0 6px 0 #0369a1; }
.m-card--2 { border-color: #f59e0b; box-shadow: 0 6px 0 #d97706; }
.m-card--3 { border-color: #8b5cf6; box-shadow: 0 6px 0 #6d28d9; }
.m-card--4 { border-color: #ef4444; box-shadow: 0 6px 0 #b91c1c; }
.m-badge-hot {
  position: absolute; top: -12px; right: -8px;
  background: linear-gradient(135deg, #ef4444, #b91c1c);
  color: #fff; font-size: 9px; font-weight: 900;
  padding: 4px 10px; border-radius: 14px; border: 2.5px solid #fff;
  box-shadow: 0 3px 0 #7f1d1d; transform: rotate(5deg);
  z-index: 2;
}
.m-badge-promo {
  position: absolute; top: -12px; left: -8px;
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: #fff; font-size: 9px; font-weight: 900;
  padding: 4px 10px; border-radius: 14px; border: 2.5px solid #fff;
  box-shadow: 0 3px 0 #15803d; transform: rotate(-5deg);
  z-index: 2;
}
.m-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.m-ico {
  width: 42px; height: 42px; border-radius: 14px;
  border: 2px solid; display: flex; align-items: center; justify-content: center;
  font-size: 22px; box-shadow: 0 3px 0 rgba(0,0,0,0.1);
}
.m-name { font-size: 14px; font-weight: 900; line-height: 1.1; margin-bottom: 2px; }
.m-dur { font-size: 10px; font-weight: 800; color: #64748b; display: flex; align-items: center; gap: 4px; }
.m-price-old { font-size: 10px; font-weight: 800; color: #94a3b8; text-decoration: line-through; }
.m-price { font-size: 17px; font-weight: 900; }
.m-specs {
  background: #f8fafc; border: 2px dashed #cbd5e1;
  border-radius: 12px; padding: 10px;
  display: grid; grid-template-columns: 1fr 1fr; gap: 6px;
  font-size: 10px; font-weight: 800; color: #475569;
}

/* ══ GUIDE BANNER ══ */
.guide-banner {
  display: flex; align-items: center; gap: 10px;
  background: rgba(255,255,255,0.15);
  border: 2px solid rgba(255,255,255,0.3);
  border-radius: 16px;
  padding: 11px 12px;
  margin-bottom: 14px;
}
.guide-banner i { font-size: 22px; color: #fff; flex-shrink: 0; }
.guide-banner__text { flex: 1; }
.guide-banner__text strong { font-size: 11px; font-weight: 900; color: #fff; display: block; }
.guide-banner__text span { font-size: 10px; color: rgba(255,255,255,0.8); font-weight: 700; }
.guide-banner__btn {
  font-size: 10px; font-weight: 900;
  background: #fff; color: #ea580c;
  padding: 6px 12px; border-radius: 20px;
  text-decoration: none; flex-shrink: 0;
  border: 2px solid #fde68a;
  box-shadow: 0 3px 0 #fbbf24;
  transition: transform 0.1s;
}
.guide-banner__btn:active { transform: translateY(2px); box-shadow: none; }

/* ══ LIMIT WARN ══ */
.limit-warn {
  display: flex; align-items: center; gap: 8px;
  background: rgba(255,255,255,0.15);
  border: 2px solid rgba(255,255,255,0.3);
  border-radius: 14px;
  padding: 10px 12px;
  margin-bottom: 12px;
  font-size: 11px; font-weight: 800; color: #fff;
}
.limit-warn a { color: #fde68a; font-weight: 900; text-decoration: none; margin-left: 2px; }

/* ══ REF TOAST ══ */
#ref-toast {
  text-align:center; font-size:11px; font-weight:800;
  color: #065f46; margin-bottom:8px; padding:8px;
  background: linear-gradient(135deg, #d1fae5, #a7f3d0);
  border: 2px solid #6ee7b7;
  border-radius:12px; box-shadow: 0 3px 0 #6ee7b7;
}
</style>

<?php if (!empty($_SESSION['flash_home_err'])): ?>
<div class="flash-alert flash-alert--err">
  <i class="ph-bold ph-warning-circle"></i>
  <?= htmlspecialchars($_SESSION['flash_home_err']) ?>
</div>
<?php unset($_SESSION['flash_home_err']); endif; ?>

<div class="cg-page">

  <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
  <!-- 1. HERO BANNER                 -->
  <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
  <div class="hero-banner">
    <!-- Top row: avatar + CTA -->
    <div class="hero-top">
      <div class="hero-avatar-wrap">
        <div class="hero-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
        <div>
          <div class="hero-name">Halo, <?= htmlspecialchars($user['username']) ?>! 👋</div>
          <div class="hero-tier">
            <i class="ph-fill ph-star" style="font-size:9px"></i>
            <?= htmlspecialchars($membership_name) ?>
          </div>
        </div>
      </div>
      <a href="<?= $is_guest ? '/login' : '/upgrade' ?>" class="hero-cta">
        <i class="ph-bold <?= $is_guest ? 'ph-sign-in' : 'ph-rocket-launch' ?>" style="font-size:13px"></i>
        <?= $is_guest ? 'MASUK' : 'UPGRADE' ?>
      </a>
    </div>

    <!-- Mascot + balance -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
      <div class="hero-mascot-area" style="flex-shrink:0">
        <div class="hero-mascot-emoji">🐱</div>
        <div class="hero-coins">
          <div class="hero-coin-pile">💰</div>
          <div style="font-size:18px">💵</div>
        </div>
      </div>
      <div style="flex:1">
        <div class="hero-balance-card">
          <div class="hero-balance-label">💎 Saldo Dapat Dicairkan</div>
          <div class="hero-balance-value"><?= format_rp((float)$user['balance_wd']) ?></div>
          <div class="hero-balance-sub">🛒 Saldo Beli: <?= format_rp((float)$user['balance_dep']) ?></div>
        </div>
      </div>
    </div>

    <!-- Watch progress -->
    <?php if (!$is_guest): ?>
    <?php $pct = $watch_limit > 0 ? min(100, round(($watch_today / $watch_limit) * 100)) : 0; ?>
    <div class="hero-progress">
      <div class="hero-progress-header">
        <div class="hero-progress-label">
          <i class="ph-bold ph-video-camera" style="font-size:11px"></i>
          Video Ditonton Hari Ini
        </div>
        <div class="hero-progress-count"><?= $watch_today ?>/<?= $watch_limit ?></div>
      </div>
      <div class="hero-progress-track">
        <div class="hero-progress-fill" style="width:<?= $pct ?>%"></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
  <!-- CONTENT                        -->
  <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
  <div class="cg-content">

    <!-- Limit warn -->
    <?php if (!$is_guest && $watch_today >= $watch_limit): ?>
    <div class="limit-warn">
      <i class="ph-bold ph-warning-circle" style="font-size:18px;flex-shrink:0"></i>
      Limit tonton hari ini sudah penuh!
      <a href="/upgrade">Upgrade →</a>
    </div>
    <?php endif; ?>

    <!-- Newcomer guide -->
    <?php
    $is_newcomer = !$is_guest && (empty($history) || (isset($user['created_at']) && strtotime($user['created_at']) > time() - 3 * 86400) || ($user['balance_wd'] == 0 && $user['balance_dep'] == 0));
    if ($is_newcomer): ?>
    <div class="guide-banner">
      <i class="ph-fill ph-book-open-text"></i>
      <div class="guide-banner__text">
        <strong>Baru gabung? Selamat datang! 🎉</strong>
        <span>Baca panduan cara dapetin reward dulu yuk!</span>
      </div>
      <a href="/panduan" class="guide-banner__btn">Panduan</a>
    </div>
    <?php endif; ?>

    <!-- ── Balance tiles ── -->
    <div class="bal-row">
      <a href="/withdraw" class="bal-tile bal-tile--wd">
        <div class="bal-tile__bg-deco">💚</div>
        <div class="bal-tile__header">
          <div class="bal-tile__icon"><i class="ph-fill ph-arrow-circle-up" style="color:#059669"></i></div>
          <div class="bal-tile__label">Saldo Cair</div>
        </div>
        <div class="bal-tile__val"><?= format_rp((float)$user['balance_wd']) ?></div>
        <div class="bal-tile__btn"><i class="ph-bold ph-upload-simple" style="font-size:10px"></i> Cairkan</div>
      </a>
      <a href="/deposit" class="bal-tile bal-tile--dep">
        <div class="bal-tile__bg-deco">💙</div>
        <div class="bal-tile__header">
          <div class="bal-tile__icon"><i class="ph-fill ph-bank" style="color:#3b82f6"></i></div>
          <div class="bal-tile__label">Saldo Beli</div>
        </div>
        <div class="bal-tile__val"><?= format_rp((float)$user['balance_dep']) ?></div>
        <div class="bal-tile__btn"><i class="ph-bold ph-plus-circle" style="font-size:10px"></i> Top Up</div>
      </a>
    </div>

    <!-- ── Quick Actions ── -->
    <div class="section-title">🎮 Menu Utama</div>
    <div class="qa-grid" style="margin-bottom:16px">
      <a href="/history" class="qa-item">
        <div class="qa-item__icon" style="background:linear-gradient(135deg,#a78bfa,#7c3aed);box-shadow:0 5px 0 #5b21b6;color:#fff">
          <i class="ph-fill ph-receipt"></i>
        </div>
        <span class="qa-item__label">Riwayat</span>
      </a>
      <a href="/missions" class="qa-item">
        <div class="qa-item__icon" style="background:linear-gradient(135deg,#f97316,#ea580c);box-shadow:0 5px 0 #c2410c;color:#fff">
          <i class="ph-fill ph-target"></i>
        </div>
        <span class="qa-item__label">Misi</span>
      </a>
      <a href="/checkin" class="qa-item">
        <div class="qa-item__icon" style="background:linear-gradient(135deg,#f472b6,#db2777);box-shadow:0 5px 0 #9d174d;color:#fff">
          <i class="ph-fill ph-calendar-check"></i>
        </div>
        <span class="qa-item__label">Absen</span>
      </a>
      <a href="/referral" class="qa-item">
        <div class="qa-item__icon" style="background:linear-gradient(135deg,#34d399,#059669);box-shadow:0 5px 0 #047857;color:#fff">
          <i class="ph-fill ph-users"></i>
        </div>
        <span class="qa-item__label">Referral</span>
      </a>
      <a href="/redeem" class="qa-item">
        <div class="qa-item__icon" style="background:linear-gradient(135deg,#60a5fa,#1d4ed8);box-shadow:0 5px 0 #1e3a8a;color:#fff">
          <i class="ph-fill ph-gift"></i>
        </div>
        <span class="qa-item__label">Redeem</span>
      </a>
      <?php if (setting($pdo, 'investment_enabled', '1') === '1'): ?>
      <a href="/invest" class="qa-item">
        <div class="qa-item__icon" style="background:linear-gradient(135deg,#fbbf24,#d97706);box-shadow:0 5px 0 #b45309;color:#fff">
          <i class="ph-fill ph-trend-up"></i>
        </div>
        <span class="qa-item__label">Investasi</span>
      </a>
      <?php endif; ?>
      <a href="/upgrade" class="qa-item">
        <div class="qa-item__icon" style="background:linear-gradient(135deg,#fb923c,#dc2626);box-shadow:0 5px 0 #991b1b;color:#fff">
          <i class="ph-fill ph-crown"></i>
        </div>
        <span class="qa-item__label">Upgrade</span>
      </a>
      <a href="/panduan" class="qa-item">
        <div class="qa-item__icon" style="background:linear-gradient(135deg,#6ee7b7,#0891b2);box-shadow:0 5px 0 #0e7490;color:#fff">
          <i class="ph-fill ph-book-open"></i>
        </div>
        <span class="qa-item__label">Panduan</span>
      </a>
    </div>

    <!-- ── Notifications ── -->
    <?php if (!empty($notif_preview)):
    $notif_dot_colors = [
      'info' => '#0284c7', 'success' => '#16a34a',
      'warning' => '#d97706', 'alert' => '#e11d48', 'congrats' => '#ca8a04',
    ]; ?>
    <div class="cg-card-section">
      <div class="cs-header">
        <div class="cs-title">
          <i class="ph-fill ph-bell-ringing" style="color:#e11d48"></i>
          Notifikasi
          <?php if ($notif_unread > 0): ?>
            <span style="background:#ef4444;color:#fff;font-size:9px;font-weight:900;padding:1px 7px;border-radius:10px"><?= $notif_unread > 9 ? '9+' : $notif_unread ?></span>
          <?php endif; ?>
        </div>
        <a href="/notifications" class="cs-link">Lihat →</a>
      </div>
      <div class="notif-list">
        <?php foreach ($notif_preview as $nf):
          $dot_color = $notif_dot_colors[$nf['type']] ?? '#0284c7'; ?>
        <div class="notif-item">
          <div class="notif-dot" style="background:<?= $dot_color ?>"></div>
          <div class="notif-body">
            <div class="notif-title"><?= htmlspecialchars($nf['title']) ?></div>
            <div class="notif-msg"><?= htmlspecialchars($nf['message']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Videos ── -->
    <?php if (!empty($videos)): ?>
    <div class="cg-card-section">
      <div class="cs-header">
        <div class="cs-title">
          <i class="ph-fill ph-video-camera" style="color:#7c3aed"></i>
          Video Tersedia 🎬
        </div>
        <a href="/videos" class="cs-link">Semua →</a>
      </div>
      <div class="vid-scroll">
        <?php foreach ($videos as $v): ?>
        <a href="/watch?id=<?= $v['id'] ?>" class="vid-card">
          <div class="vid-card__thumb">
            <img src="<?= yt_thumb($v['youtube_id']) ?>" alt="<?= htmlspecialchars($v['title']) ?>" loading="lazy"
                 onerror="this.src='https://img.youtube.com/vi/<?= $v['youtube_id'] ?>/hqdefault.jpg'">
            <div class="vid-card__play"><i class="ph-fill ph-play-circle"></i></div>
            <div class="vid-card__badge">+<?= format_rp((float)$v['reward_amount']) ?></div>
          </div>
          <div class="vid-card__body">
            <div class="vid-card__title"><?= htmlspecialchars($v['title']) ?></div>
            <div class="vid-card__meta">
              <span style="color:#059669;display:flex;align-items:center;gap:2px"><i class="ph-bold ph-coins"></i> <?= format_rp((float)$v['reward_amount']) ?></span>
              <span style="display:flex;align-items:center;gap:2px"><i class="ph-bold ph-clock"></i> <?= $v['watch_duration'] ?>s</span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php elseif (!$is_guest): ?>
    <div class="vid-done" style="margin-bottom:14px">
      <i class="ph-fill ph-check-circle"></i>
      <div>
        <div style="font-size:13px;font-weight:900;color:#065f46">Keren! Semua video sudah ditonton 🎉</div>
        <div style="font-size:11px;color:#059669;font-weight:700;margin-top:2px">Kembali lagi besok untuk video baru!</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Membership Showcase ── -->
    <?php if (!empty($showcase_memberships)): ?>
    <div class="cg-card-section">
      <div class="cs-header">
        <div class="cs-title">
          <i class="ph-fill ph-crown" style="color:#f59e0b"></i>
          Upgrade Level 👑
        </div>
        <a href="/upgrade" class="cs-link">Semua →</a>
      </div>
      <?php
      foreach ($showcase_memberships as $i => $m):
        $m_class = "m-card--" . ($i % 5);
        $bg_color = ['#f8fafc','#f0f9ff','#fefce8','#faf5ff','#fef2f2'][$i % 5];
        $txt_color = ['#0f172a','#0369a1','#b45309','#6b21a8','#b91c1c'][$i % 5];
      ?>
      <a href="/upgrade" class="m-card <?= $m_class ?>">
        <?php if ($i === 2): ?>
          <div class="m-badge-hot">🔥 TERPOPULER</div>
        <?php elseif ((float)$m['original_price'] > 0): ?>
          <div class="m-badge-promo">🎉 PROMO!</div>
        <?php endif; ?>
        <div class="m-hdr">
          <div style="display:flex;align-items:center;gap:10px">
            <div class="m-ico" style="background:<?= $bg_color ?>;color:<?= $txt_color ?>;border-color:<?= $txt_color ?>">
              <?= htmlspecialchars($m['icon'] ?: '⭐') ?>
            </div>
            <div>
              <div class="m-name" style="color:<?= $txt_color ?>"><?= htmlspecialchars($m['name']) ?></div>
              <div class="m-dur"><i class="ph-bold ph-hourglass"></i> <?= $m['duration_days'] ?> Hari</div>
            </div>
          </div>
          <div style="text-align:right">
            <?php if ((float)$m['original_price'] > 0): ?>
            <div class="m-price-old"><?= format_rp((float)$m['original_price']) ?></div>
            <?php endif; ?>
            <div class="m-price" style="color:<?= $txt_color ?>"><?= format_rp((float)$m['price']) ?></div>
          </div>
        </div>
        <div class="m-specs">
          <div><i class="ph-bold ph-video-camera"></i> <?= $m['watch_limit'] ?>× Tonton/hari</div>
          <div><i class="ph-bold ph-trend-up"></i> Maks Narik <?= (float)$m['max_wd'] > 0 ? format_rp((float)$m['max_wd']) : '<span style="color:#059669">Tanpa batas</span>' ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Referral ── -->
    <?php if (!$is_guest): ?>
    <div class="cg-card-section cg-card-section--green">
      <div class="cs-header" style="margin-bottom:8px">
        <div class="cs-title" style="color:#064e3b">
          <i class="ph-fill ph-share-network" style="color:#059669"></i>
          Kode Referral 🔗
        </div>
      </div>
      <div class="ref-card">
        <i class="ph-fill ph-gift" style="font-size:24px;color:#059669;flex-shrink:0"></i>
        <div class="ref-code">
          <div class="ref-code__label">Kode kamu</div>
          <div class="ref-code__val"><?= htmlspecialchars($user['referral_code']) ?></div>
        </div>
        <button type="button" class="ref-btn" onclick="copyRef('<?= htmlspecialchars($user['referral_code']) ?>')">
          <i class="ph-bold ph-copy"></i> Salin
        </button>
      </div>
      <div id="ref-toast" style="display:none;margin-top:8px">
        ✓ Kode berhasil disalin!
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Recent Activity ── -->
    <?php if (!empty($history)): ?>
    <div class="cg-card-section">
      <div class="cs-header">
        <div class="cs-title">
          <i class="ph-fill ph-clock-counter-clockwise" style="color:#059669"></i>
          Aktivitas Terakhir ⚡
        </div>
      </div>
      <div class="activity-list">
        <?php foreach ($history as $h): ?>
        <div class="activity-item">
          <div class="activity-icon"><i class="ph-fill ph-monitor-play"></i></div>
          <div class="activity-text">
            <div class="activity-title"><?= htmlspecialchars($h['title']) ?></div>
            <div class="activity-date"><i class="ph-bold ph-calendar-blank" style="font-size:9px"></i> <?= date('d M H:i', strtotime($h['watched_at'])) ?></div>
          </div>
          <div class="activity-amount">+<?= format_rp((float)$h['reward_given']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /cg-content -->
</div><!-- /cg-page -->

<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<!-- POPUP PANDUAN                  -->
<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<?php
$popup_enabled     = setting($pdo, 'popup_enabled', '1') === '1';
$popup_title       = setting($pdo, 'popup_title', 'Hei, sudah baca panduan?');
$popup_body        = setting($pdo, 'popup_body', 'Biar makin lancar dapat reward, yuk baca dulu cara kerja Meloton!');
$popup_cta_text    = setting($pdo, 'popup_cta_text', 'Baca Panduan');
$popup_cta_url     = setting($pdo, 'popup_cta_url', '/panduan');
$popup_delay       = max(0, (int) setting($pdo, 'popup_delay', '1500'));
$popup_reset_hours = max(0, (int) setting($pdo, 'popup_reset_hours', '0'));
?>
<?php if ($popup_enabled): ?>
<div id="guide-popup" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(4px); z-index:100000; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#fff; border-radius:28px; padding:24px 20px 20px; max-width:320px; width:100%; transform:scale(0.8); opacity:0; transition:all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position:relative; border:4px solid #f97316; box-shadow:0 10px 0 #ea580c, 0 16px 32px rgba(0,0,0,0.3);">
    <button onclick="closePopup()" style="position:absolute; top:-14px; right:-14px; background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border:3px solid #fff; width:38px; height:38px; border-radius:50%; font-size:16px; font-weight:900; display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 4px 0 #b91c1c; transition:transform 0.1s;">
      <i class="ph-bold ph-x"></i>
    </button>
    <div style="width:68px; height:68px; background:linear-gradient(135deg, #fbbf24, #f97316); border:3px solid #ea580c; box-shadow:0 5px 0 #c2410c; border-radius:22px; display:flex; align-items:center; justify-content:center; font-size:34px; margin:-50px auto 16px;">
      📖
    </div>
    <h3 style="font-size:18px; font-weight:900; text-align:center; margin:0 0 8px; color:#0f172a; line-height:1.2;"><?= htmlspecialchars($popup_title) ?></h3>
    <p style="font-size:13px; line-height:1.5; color:#475569; font-weight:700; text-align:center; margin:0 0 20px"><?= nl2br(htmlspecialchars($popup_body)) ?></p>
    <div style="display:flex; flex-direction:column; gap:8px;">
      <a href="<?= htmlspecialchars($popup_cta_url) ?>" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; font-size:14px; font-weight:900; padding:14px; border-radius:18px; background:linear-gradient(135deg, #f97316, #ea580c); border:3px solid #fde68a; box-shadow:0 6px 0 #c2410c; color:#fff; text-decoration:none; transition:transform 0.1s;">
        <i class="ph-bold ph-book-bookmark"></i> <?= htmlspecialchars($popup_cta_text) ?>
      </a>
      <button type="button" onclick="closePopup()" style="width:100%; padding:10px; background:transparent; border:none; font-size:12px; font-weight:800; color:#94a3b8; cursor:pointer;">Nanti Saja</button>
    </div>
  </div>
</div>
<script>
function closePopup() {
  const p = document.getElementById('guide-popup');
  const c = p.querySelector('div');
  c.style.transform = 'scale(0.8)';
  c.style.opacity = '0';
  setTimeout(() => p.style.display = 'none', 300);
  try { localStorage.setItem('tonton_popup_seen', JSON.stringify({ts: Date.now()})); } catch(e){}
}
document.addEventListener('DOMContentLoaded', () => {
  const p = document.getElementById('guide-popup');
  if(!p) return;
  const c = p.querySelector('div');
  const resetMs = <?= $popup_reset_hours ?> * 3600000;
  try {
    const raw = localStorage.getItem('tonton_popup_seen');
    if (raw) {
      const data = JSON.parse(raw);
      if (resetMs <= 0 || (Date.now() - data.ts) < resetMs) return;
    }
  } catch(e){}
  setTimeout(() => {
    p.style.display = 'flex';
    p.offsetHeight;
    c.style.transform = 'scale(1)';
    c.style.opacity = '1';
  }, <?= $popup_delay ?>);
});
</script>
<?php endif; ?>

<script>
function copyRef(code) {
  navigator.clipboard.writeText(code).then(() => {
    const toast = document.getElementById('ref-toast');
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 2000);
  }).catch(() => {});
}
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
