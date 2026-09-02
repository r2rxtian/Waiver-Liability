<?php
declare(strict_types=1);
function page_start(string $title, string $active=''): void { $u=user(); $flash=take_flash(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?> · <?=APP_NAME?></title><meta name="description" content="Internal employee medical waiver management">
<link rel="stylesheet" href="/Waiver-Liability/styles/app.css"><link rel="stylesheet" href="/Waiver-Liability/styles/route-guard.css"><link rel="stylesheet" href="/Waiver-Liability/styles/activity-logs.css"><link rel="stylesheet" href="/Waiver-Liability/styles/tables.css"><link rel="stylesheet" href="/Waiver-Liability/styles/dashboard-v2.css"><link rel="stylesheet" href="/Waiver-Liability/styles/login.css"><link rel="stylesheet" href="/Waiver-Liability/styles/signing.css"><link rel="stylesheet" href="/Waiver-Liability/styles/signing-desktop.css"></head><body class="page-<?=e($active)?>"><a class="skip" href="#content">Skip to content</a>
<?php if($u): ?><div class="shell"><aside class="sidebar"><div class="brand"><span class="brand-mark">W</span><div><strong>Waiver Desk</strong><small>Waiver Management</small></div></div>
<nav><?php
$items=[['dashboard','Dashboard','&#8962;'],['new-waiver','New waiver','+'],['waivers','Waivers','&#9638;'],['approvals','Pending approvals','&#10003;'],['templates','Templates','&#9672;'],['audit','Activity logs','&#9716;'],['users','Users','&#9823;'],['settings','Settings','&#9881;']];
foreach($items as [$p,$label,$icon]){ $allowed=match($p){'users','audit','settings'=>in_array($u['role_name'],['SYSTEM ADMIN'],true),'templates'=>in_array($u['role_name'],['SYSTEM ADMIN','HR / CLINIC ADMIN'],true),'approvals'=>in_array($u['role_name'],['SUPERVISOR','SYSTEM ADMIN'],true),default=>true}; if($allowed) echo '<a class="'.($active===$p?'active':'').'" href="index.php?page='.$p.'"><span>'.$icon.'</span>'.e($label).'</a>'; }
?></nav><div class="sidebar-foot"><div class="hills"></div><a href="index.php?page=logout">Sign out</a></div></aside>
<main><header class="topbar"><button class="menu" aria-label="Open menu">☰</button><div><span class="eyebrow">Waiver management</span><h1><?=e($title)?></h1></div><div class="top-user"><span><?=e(date('M j, Y'))?></span><span><?=e(date('g:i A'))?></span><span class="avatar small"><?=e(strtoupper(substr($u['full_name'],0,1)))?></span></div></header><div id="content" class="content">
<?php if($flash): ?><div class="notice <?=e($flash[0])?>"><?=e($flash[1])?></div><?php endif; ?>
<?php else: ?><main id="content" class="auth-main"><?php endif; ?>
<?php }
function page_end(): void { if(user()): ?></div></main></div><?php else: ?></main><?php endif; ?><script src="/Waiver-Liability/scripts/app.js"></script></body></html><?php }
function badge(string $status): string { return '<span class="badge '.strtolower(str_replace('_','-',$status)).'">'.e(str_replace('_',' ',$status)).'</span>'; }
