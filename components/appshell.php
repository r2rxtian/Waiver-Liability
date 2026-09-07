<?php
declare(strict_types=1);
function page_start(string $title, string $active='', string $subtitle='', string $eyebrow=''): void { $u=user(); $flash=take_flash(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?> · <?=APP_NAME?></title><meta name="description" content="Internal employee medical waiver management">
<link rel="stylesheet" href="/Waiver-Liability/styles/app.css"><link rel="stylesheet" href="/Waiver-Liability/styles/route-guard.css"><link rel="stylesheet" href="/Waiver-Liability/styles/activity-logs.css"><link rel="stylesheet" href="/Waiver-Liability/styles/tables.css"><link rel="stylesheet" href="/Waiver-Liability/styles/dashboard-v2.css"><link rel="stylesheet" href="/Waiver-Liability/styles/login.css"><link rel="stylesheet" href="/Waiver-Liability/styles/signing.css"><link rel="stylesheet" href="/Waiver-Liability/styles/signing-desktop.css"><link rel="stylesheet" href="/Waiver-Liability/styles/new-waiver.css"><link rel="stylesheet" href="/Waiver-Liability/styles/settings.css"></head><body class="page-<?=e($active)?>"><a class="skip" href="#content">Skip to content</a>
<?php if($flash): ?>
<?php if($flash[0] === 'success'): ?>
<div class="app-toast-container" id="toast-container" aria-live="polite" aria-atomic="true">
    <div class="app-toast toast-success" id="app-toast" role="status">
        <div class="toast-icon-wrap" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="4 10.5 8.5 15 16 6"></polyline>
            </svg>
        </div>
        <div class="toast-content">
            <div class="toast-title">Success</div>
            <div class="toast-message"><?=e($flash[1])?></div>
        </div>
        <button type="button" class="toast-close" aria-label="Dismiss notification" title="Close notification">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="2" y1="2" x2="12" y2="12"></line>
                <line x1="12" y1="2" x2="2" y2="12"></line>
            </svg>
        </button>
        <div class="toast-progress" aria-hidden="true">
            <div class="toast-progress-bar"></div>
        </div>
    </div>
</div>
<?php else: ?>
<dialog class="app-modal alert-modal" data-auto-open>
    <div class="app-modal-icon <?=e($flash[0])?>"><?= $flash[0] === 'error' ? '!' : '✓' ?></div>
    <h2><?= $flash[0] === 'error' ? 'Notice' : 'Success' ?></h2>
    <p><?=e($flash[1])?></p>
    <div class="app-modal-actions">
        <button type="button" class="btn" onclick="this.closest('dialog').close()">OK</button>
    </div>
</dialog>
<?php endif; ?>
<?php endif; ?>
<?php if($u): ?><div class="shell"><aside class="sidebar"><div class="brand"><span class="brand-mark">W</span><div><strong>Waiver Desk</strong><small>Waiver Management</small></div></div>
<nav><?php
$items=[['dashboard','Dashboard','&#8962;'],['new-waiver','New waiver','+'],['waivers','Waivers','&#9638;'],['approvals','Pending approvals','&#10003;'],['audit','Activity logs','&#9716;'],['users','Users','&#9823;'],['settings','Settings','&#9881;']];
foreach($items as [$p,$label,$icon]){ $allowed=match($p){'users','audit','settings'=>in_array($u['role_name'],['SYSTEM ADMIN'],true),'approvals'=>in_array($u['role_name'],['SUPERVISOR','SYSTEM ADMIN'],true),default=>true}; if($allowed) echo '<a class="'.($active===$p?'active':'').'" href="index.php?page='.$p.'"><span>'.$icon.'</span>'.e($label).'</a>'; }
?></nav><div class="sidebar-foot"><div class="hills"></div><a href="index.php?page=logout">Sign out</a></div></aside>
<main><header class="topbar"><button class="menu" aria-label="Open menu">☰</button><div class="topbar-brand"><span class="brand-mark">W</span><div><strong>Waiver Desk</strong><small>Waiver Management</small></div></div><div class="topbar-title"><span class="eyebrow"><?=e($eyebrow !== '' ? $eyebrow : 'Waiver management')?></span><h1><?=e($title)?></h1><?php if($subtitle !== ''): ?><p class="topbar-desc"><?=e($subtitle)?></p><?php endif; ?></div><div class="top-user"><span><?=e(date('M j, Y'))?></span><span><?=e(date('g:i A'))?></span><span class="avatar small"><?=e(strtoupper(substr($u['full_name'],0,1)))?></span></div></header><div id="content" class="content">
<?php if($u && $u['role_name']==='SYSTEM ADMIN'): ?>
<dialog id="delete-waiver-modal" class="app-modal confirm-modal">
    <div class="app-modal-icon danger">!</div>
    <h2>Delete waiver record?</h2>
    <p id="delete-waiver-message">Are you sure you want to permanently delete this waiver?</p>
    <form method="post" id="delete-waiver-form">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="delete_waiver">
        <input type="hidden" name="waiver_id" id="delete-waiver-id" value="">
        <div class="app-modal-actions">
            <button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Cancel</button>
            <button type="submit" class="btn danger" id="delete-waiver-confirm-btn">Yes, delete waiver</button>
        </div>
    </form>
</dialog>
<dialog id="bulk-delete-waiver-modal" class="app-modal confirm-modal">
    <div class="app-modal-icon danger">!</div>
    <h2 id="bulk-delete-modal-title">Delete selected waivers?</h2>
    <p id="bulk-delete-modal-message">Are you sure you want to permanently delete the selected waivers?</p>
    <div id="bulk-delete-preview" class="bulk-delete-preview"></div>
    <form method="post" id="bulk-delete-waiver-form">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="delete_bulk_waivers">
        <div id="bulk-delete-hidden-inputs"></div>
        <div class="app-modal-actions">
            <button type="button" class="btn secondary" onclick="this.closest('dialog').close()">Cancel</button>
            <button type="submit" class="btn danger" id="bulk-delete-confirm-btn">Yes, delete waivers</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
<?php else: ?><main id="content" class="auth-main"><?php endif; ?>
<?php }
function page_end(): void { if(user()): ?></div></main></div><?php else: ?></main><?php endif; ?><script src="/Waiver-Liability/scripts/app.js"></script></body></html><?php }
function badge(string $status): string { return '<span class="badge '.strtolower(str_replace('_','-',$status)).'">'.e(str_replace('_',' ',$status)).'</span>'; }
