<?php
declare(strict_types=1);
require_once __DIR__.'/../auth/session.php';
require_once __DIR__.'/../components/appshell.php';
require_once __DIR__.'/../rules/constants.php';
require_once __DIR__.'/../rules/validation.php';
require_once __DIR__.'/../rules/signing.php';
require_once __DIR__.'/../components/signing_panel.php';
$page=$_GET['page']??(user()?'dashboard':'login');
if($page==='login'){
 if(user())redirect('index.php?page=dashboard');$error='';
 if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$r=one('SELECT u.*,r.role_name FROM dbo.acd_mw_users u JOIN dbo.acd_mw_roles r ON r.role_id=u.role_id WHERE u.username=? AND u.is_active=1',[post('username')]);if($r&&password_verify(post('password'),$r['password_hash'])){session_regenerate_id(true);$_SESSION['user']=['user_id'=>$r['user_id'],'username'=>$r['username'],'full_name'=>$r['full_name'],'role_name'=>$r['role_name'],'employee_id'=>$r['employee_id']];audit('LOGIN','USER',(int)$r['user_id'],'User signed in');redirect('index.php?page=dashboard');}$error='The username or password is incorrect.';}
 page_start('Sign in','login');?>
 <section class="login-layout">
  <svg class="login-scenery" viewBox="0 0 1600 900" preserveAspectRatio="none" aria-hidden="true"><path class="scenery-back" d="M0 655C180 624 308 792 490 765C650 741 701 635 827 627C948 620 1022 776 1184 748C1321 724 1442 643 1600 672V900H0Z"/><path class="scenery-mid" d="M0 724C151 695 292 836 465 814C611 796 699 708 814 720C942 733 1029 837 1165 817C1332 792 1445 725 1600 755V900H0Z"/><path class="scenery-front" d="M0 782C174 746 319 879 507 852C687 826 784 772 948 801C1116 831 1222 878 1600 815V900H0Z"/><g class="scenery-plant"><path d="M23 828C34 728 50 620 90 514"/><path d="M46 708C9 677 9 635 28 608C58 636 63 671 46 708ZM58 654C88 621 90 583 78 559C53 582 45 615 58 654ZM72 600C43 569 45 535 61 512C84 537 88 570 72 600ZM84 550C111 527 117 495 108 470C84 489 76 519 84 550Z"/></g></svg>
  <div class="login-story" aria-labelledby="login-story-title">
   <span class="login-logo" aria-hidden="true">W</span>
   <h1 id="login-story-title">Waiver desk</h1>
   <p class="login-story-copy">Create, sign, and manage liability waivers in one secure workspace.</p>
   <div class="login-benefits">
    <article><span aria-hidden="true">↯</span><div><b>Fast waiver creation</b><p>Build professional waivers in minutes.</p></div></article>
    <article><span aria-hidden="true">◇</span><div><b>Secure signatures</b><p>Legally valid e-signatures you can trust.</p></div></article>
    <article><span aria-hidden="true">▧</span><div><b>Audit-ready records</b><p>Organized, exportable, and audit-ready.</p></div></article>
   </div>
  </div>
  <section class="login-card" aria-labelledby="login-title">
   <span class="login-logo card-logo" aria-hidden="true">W</span>
   <h2 id="login-title">Welcome back</h2>
   <p class="login-card-copy">Sign in to continue to Waiver desk.</p>
   <?php if($error):?><div class="notice error" role="alert"><?=e($error)?></div><?php endif?>
   <form method="post" class="login-form" data-login-form><?=csrf_field()?>
    <div class="field"><label for="login-username">Username</label><div class="auth-input"><span class="auth-field-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.6 2.9-6 7-6s7 2.4 7 6"/></svg></span><input id="login-username" name="username" value="<?=e($_POST['username']??'')?>" autocomplete="username" placeholder="Enter your username" required></div></div>
    <div class="field"><label for="login-password">Password</label><div class="auth-input"><span aria-hidden="true">▣</span><input id="login-password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required><button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">◉</button></div></div>
    <div class="login-options"><label><input type="checkbox" name="remember_username" value="1" data-remember-username><span>Remember me</span></label><a href="mailto:office@la-rose-noire.com?subject=Waiver%20Desk%20password%20assistance">Forgot password?</a></div>
    <button class="btn login-submit">Sign in</button>
   </form>
   <div class="login-divider"><span>or</span></div>
   <a class="contact-admin" href="mailto:office@la-rose-noire.com?subject=Waiver%20Desk%20assistance"><span aria-hidden="true">◌</span> Contact administrator</a>
   <p class="login-help">Need help? <a href="mailto:office@la-rose-noire.com">Contact your system administrator.</a></p>
  </section>
 </section>
 <?php page_end();exit;
}
if($page==='logout'){if(user())audit('LOGOUT','USER',(int)user()['user_id'],'User signed out');$_SESSION=[];session_destroy();redirect('index.php?page=login');}require_login();
if($page==='reports')redirect('index.php?page=dashboard');
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$a=post('action');
 /* Server-side wizard gates mirror the client affordances. */
 if($a==='start' && !one("SELECT employee_id FROM dbo.acd_mw_employees WHERE employee_id=? AND is_active=1",[(int)post('employee_id')]))throw new RuntimeException('The selected employee is not active.');
 if($a==='type' && empty($_SESSION['waiver_draft']['employee_id']))throw new RuntimeException('Select an employee before choosing a waiver type.');
 if($a==='details' && empty($_SESSION['waiver_draft']['employee_id']))throw new RuntimeException('Select an employee and waiver type before entering details.');
 if($a==='details' && !in_array((string)($_SESSION['waiver_draft']['type_code']??''),['UNFIT_TO_WORK','REFUSED_MEDICAL_TREATMENT'],true))throw new RuntimeException('Choose a valid waiver type before entering details.');
 if($a==='details' && (mb_strlen(post('recommendation'))>1000 || mb_strlen(post('remarks'))>1000))throw new RuntimeException('Recommendation and remarks must be 1,000 characters or fewer.');
 if($a==='review' && post('review_confirm')!=='1'){flash('error','Confirm that the reviewed information is correct before continuing.');redirect('index.php?page=new-waiver&step=4&id='.(int)post('waiver_id'));}
 if($a==='details' && ((int)post('waiver_id')>0 || !empty($_SESSION['editing_waiver_id']))){
  $editId=(int)(post('waiver_id')?:$_SESSION['editing_waiver_id']);$edit=one('SELECT TOP 1 w.*,t.type_code FROM dbo.acd_mw_waivers w JOIN dbo.acd_mw_waiver_types t ON t.waiver_type_id=w.waiver_type_id WHERE w.waiver_id=? AND w.status=\'DRAFT\' AND w.created_by=?',[$editId,(int)user()['user_id']]);
  if(!$edit)throw new RuntimeException('This draft can no longer be edited.');
  $v=one("SELECT TOP 1 waiver_text FROM dbo.acd_mw_waiver_versions WHERE waiver_version_id=?",[(int)$edit['waiver_version_id']]);
  if(!$v)throw new RuntimeException('The waiver template is no longer active.');
  $snapshot=render_waiver_text((string)$v['waiver_text'],(int)$edit['employee_id']);
  run('UPDATE dbo.acd_mw_waivers SET medical_personnel_name=?,recommendation=?,transportation_offered=?,remarks=?,signed_waiver_text=? WHERE waiver_id=? AND status=\'DRAFT\'', [post('medical_personnel'),post('recommendation'),$edit['type_code']==='REFUSED_MEDICAL_TREATMENT'?post('transportation'):'None',post('remarks'),$snapshot,$editId]);
  unset($_SESSION['editing_waiver_id']);audit('UPDATE_WAIVER','WAIVER',$editId,'Waiver draft details updated');
  redirect("index.php?page=new-waiver&step=4&id=$editId");
 }
 if($a==='start'){require_role(['SYSTEM ADMIN','CLINIC NURSE','HR / CLINIC ADMIN']);$id=(int)post('employee_id');if(!exists('acd_mw_employees','employee_id',$id))throw new RuntimeException('Employee not found.');$_SESSION['waiver_draft']=['employee_id'=>$id];redirect('index.php?page=new-waiver&step=2');}
 if($a==='type'){require_role(['SYSTEM ADMIN','CLINIC NURSE','HR / CLINIC ADMIN']);$type=post('waiver_type');if(!in_array($type,['UNFIT_TO_WORK','REFUSED_MEDICAL_TREATMENT'],true))throw new RuntimeException('Invalid type.');$_SESSION['waiver_draft']['type_code']=$type;audit('SELECT_WAIVER_TYPE','WAIVER',null,$type);redirect('index.php?page=new-waiver&step=3');}
 if($a==='details'){require_role(SIGNING_STAFF_ROLES);$d=$_SESSION['waiver_draft']??[];$eid=(int)($d['employee_id']??0);$type=$d['type_code']??'';if(!exists('acd_mw_employees','employee_id',$eid))throw new RuntimeException('Employee not found.');$v=one("SELECT TOP 1 v.*,t.waiver_type_id FROM dbo.acd_mw_waiver_versions v JOIN dbo.acd_mw_waiver_types t ON t.waiver_type_id=v.waiver_type_id WHERE t.type_code=? AND v.status='ACTIVE' ORDER BY v.waiver_version_id DESC",[$type]);if(!$v)throw new RuntimeException('No active waiver template.');$snapshot=render_waiver_text((string)$v['waiver_text'],$eid);run("INSERT INTO dbo.acd_mw_waivers(employee_id,waiver_type_id,waiver_version_id,waiver_date,waiver_time,medical_personnel_name,recommendation,transportation_offered,remarks,signed_waiver_text,status,created_by,created_at) VALUES(?,?,?,CAST(GETDATE() AS date),CAST(GETDATE() AS time),?,?,?,?,?,'DRAFT',?,SYSDATETIME())",[$eid,$v['waiver_type_id'],$v['waiver_version_id'],post('medical_personnel'),post('recommendation'),$type==='REFUSED_MEDICAL_TREATMENT'?post('transportation'):'None',post('remarks'),$snapshot,user()['user_id']]);$id=(int)database()->lastInsertId();unset($_SESSION['waiver_draft']);audit('CREATE_WAIVER','WAIVER',$id,'Waiver draft created');redirect("index.php?page=new-waiver&step=4&id=$id");}
 if(in_array($a,['review','request_employee_signature'],true)){require_role(SIGNING_STAFF_ROLES);$id=(int)post('waiver_id');try{$request=signing_create_request($id,(int)user()['user_id']);$_SESSION['signing_request_tokens'][$id]=$request;audit('REVIEW_WAIVER','WAIVER',$id,'Waiver reviewed and employee signature requested');}catch(RuntimeException $exception){flash('error',$exception->getMessage());redirect("index.php?page=new-waiver&step=4&id=$id");}redirect("index.php?page=new-waiver&step=5&id=$id");}
 if($a==='cancel_signing_request'){require_role(SIGNING_STAFF_ROLES);$id=(int)post('waiver_id');try{signing_cancel_request($id,(int)user()['user_id']);unset($_SESSION['signing_request_tokens'][$id]);flash('success','The signing request was cancelled.');}catch(RuntimeException $exception){flash('error',$exception->getMessage());}redirect("index.php?page=new-waiver&step=5&id=$id");}
 if($a==='employee_sign')throw new RuntimeException('Employee signatures must be completed through a valid one-time mobile signing link.');
 if($a==='save_signing_settings'){require_role(['SYSTEM ADMIN']);$baseUrl=rtrim(post('app_base_url'),'/');$minutes=(int)post('signing_token_minutes');$parts=$baseUrl!==''?parse_url($baseUrl):[];if($baseUrl!==''&&(!filter_var($baseUrl,FILTER_VALIDATE_URL)||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])))throw new RuntimeException('APP_BASE_URL must be a valid HTTP or HTTPS application URL without credentials, query, or fragment.');if($minutes<1||$minutes>60)throw new RuntimeException('Signing expiry must be between 1 and 60 minutes.');foreach(['APP_BASE_URL'=>$baseUrl,'SIGNING_TOKEN_MINUTES'=>(string)$minutes] as $key=>$value){if(one('SELECT TOP 1 setting_id FROM dbo.acd_mw_settings WHERE setting_key=? ORDER BY setting_id DESC',[$key]))run('UPDATE dbo.acd_mw_settings SET setting_value=?,updated_by=?,updated_at=SYSDATETIME() WHERE setting_key=?',[$value,user()['user_id'],$key]);else run('INSERT INTO dbo.acd_mw_settings(setting_key,setting_value,updated_by,updated_at) VALUES(?,?,?,SYSDATETIME())',[$key,$value,user()['user_id']]);}audit('UPDATE_SIGNING_SETTINGS','SETTING',null,'Updated signing URL and token expiry settings');flash('success','Signing settings saved.');redirect('index.php?page=settings');}
 if($a==='supervisor_sign'){require_role(SIGNING_SUPERVISOR_ROLES);$id=(int)post('waiver_id');try{signing_finalize_supervisor($id,(int)user()['user_id'],post('printed_name'),(string)($_POST['signature']??''));}catch(RuntimeException $exception){flash('error',$exception->getMessage());redirect("index.php?page=approval-sign&id=$id");}redirect("index.php?page=waiver-view&id=$id");}
 if($a==='delete_waiver'){require_role(['SYSTEM ADMIN']);$id=(int)post('waiver_id');$w=one('SELECT w.*,e.full_name FROM dbo.acd_mw_waivers w LEFT JOIN dbo.acd_mw_employees e ON e.employee_id=w.employee_id WHERE w.waiver_id=?',[$id]);if(!$w)throw new RuntimeException('Waiver record not found or already deleted.');run('DELETE FROM dbo.acd_mw_signing_tokens WHERE waiver_id=?',[$id]);run('DELETE FROM dbo.acd_mw_waiver_signatures WHERE waiver_id=?',[$id]);run('DELETE FROM dbo.acd_mw_waiver_acknowledgments WHERE waiver_id=?',[$id]);run('DELETE FROM dbo.acd_mw_waivers WHERE waiver_id=?',[$id]);$num=$w['waiver_number']?:'Draft #'.$id;audit('DELETE_WAIVER','WAIVER',$id,"Deleted waiver {$num} for employee {$w['full_name']}");flash('success',"Waiver {$num} was permanently deleted.");redirect('index.php?page=waivers');}
 if($a==='delete_bulk_waivers'){require_role(['SYSTEM ADMIN']);$ids=array_values(array_filter(array_map('intval',(array)($_POST['waiver_ids']??[])),fn($v)=>$v>0));if(empty($ids))throw new RuntimeException('No waivers were selected for deletion.');$inPlaceholders=implode(',',array_fill(0,count($ids),'?'));$items=all("SELECT w.waiver_id,w.waiver_number,e.full_name FROM dbo.acd_mw_waivers w LEFT JOIN dbo.acd_mw_employees e ON e.employee_id=w.employee_id WHERE w.waiver_id IN ($inPlaceholders)",$ids);if(!$items)throw new RuntimeException('Selected waiver records were not found or already deleted.');run("DELETE FROM dbo.acd_mw_signing_tokens WHERE waiver_id IN ($inPlaceholders)",$ids);run("DELETE FROM dbo.acd_mw_waiver_signatures WHERE waiver_id IN ($inPlaceholders)",$ids);run("DELETE FROM dbo.acd_mw_waiver_acknowledgments WHERE waiver_id IN ($inPlaceholders)",$ids);run("DELETE FROM dbo.acd_mw_waivers WHERE waiver_id IN ($inPlaceholders)",$ids);$count=count($items);$sampleNumbers=array_slice(array_map(fn($x)=>$x['waiver_number']?:('Draft #'.$x['waiver_id']),$items),0,5);$sampleStr=implode(', ',$sampleNumbers).($count>5?" and ".($count-5)." more":"");audit('DELETE_WAIVER','WAIVER',null,"Batch deleted {$count} waivers: {$sampleStr}");flash('success',$count===1?"Waiver {$sampleNumbers[0]} was permanently deleted.":"{$count} waivers were permanently deleted.");redirect('index.php?page=waivers');}}
/* The wizard owns its own full-width layout on steps 1–4 and 6. Step 5 is
   rendered above because it contains the live QR/signing status panel. */
if($page==='new-waiver' && (int)($_GET['step']??1)!==5){
 require_role(SIGNING_STAFF_ROLES);
 $wizardStep=max(1,min(6,(int)($_GET['step']??1)));
 $wizardDraft=$_SESSION['waiver_draft']??[];
 if($wizardStep===2 && empty($wizardDraft['employee_id']))redirect('index.php?page=new-waiver&step=1');
 if($wizardStep===3 && empty($_GET['id']) && empty($wizardDraft['employee_id']))redirect('index.php?page=new-waiver&step=1');
 if($wizardStep===3 && empty($_GET['id']) && empty($wizardDraft['type_code']))redirect('index.php?page=new-waiver&step=2');
 page_start('New waiver','new-waiver');
 render_new_waiver_design($wizardStep);
 page_end();exit;
}
function render_waiver_text(string $template,int $employeeId):string{$employee=one("SELECT e.full_name,d.department_name FROM dbo.acd_mw_employees e LEFT JOIN dbo.acd_mw_departments d ON d.department_id=e.department_id WHERE e.employee_id=?",[$employeeId]);if(!$employee)throw new RuntimeException('Employee not found.');return strtr($template,['{{employee_name}}'=>(string)$employee['full_name'],'{{department}}'=>(string)($employee['department_name']??'')]);}
function waiver(int $id):?array{return signing_waiver($id);}
function rows(string $where=''):array{return all("SELECT w.waiver_id,w.waiver_number,w.status,w.created_at,e.employee_number,e.full_name,d.department_name,t.type_name FROM dbo.acd_mw_waivers w JOIN dbo.acd_mw_employees e ON e.employee_id=w.employee_id LEFT JOIN dbo.acd_mw_departments d ON d.department_id=e.department_id JOIN dbo.acd_mw_waiver_types t ON t.waiver_type_id=w.waiver_type_id $where ORDER BY w.created_at DESC");}
function activity_data():array{$where=[];$args=[];$action=trim((string)($_GET['action']??''));$userId=(int)($_GET['user_id']??0);$from=(string)($_GET['from']??'');$to=(string)($_GET['to']??'');$q=trim((string)($_GET['q']??''));if($action!==''){$where[]='a.action=?';$args[]=$action;}if($userId>0){$where[]='a.user_id=?';$args[]=$userId;}if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]='CAST(a.created_at AS date)>=?';$args[]=$from;}if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]='CAST(a.created_at AS date)<=?';$args[]=$to;}if($q!==''){$where[]='(a.description LIKE ? OR a.entity_type LIKE ? OR CAST(a.entity_id AS VARCHAR(30)) LIKE ?)';$like="%$q%";array_push($args,$like,$like,$like);}$sql='SELECT TOP 500 a.created_at,a.user_id,a.action,a.entity_type,a.entity_id,a.description,u.full_name,u.username FROM dbo.acd_mw_activity_logs a LEFT JOIN dbo.acd_mw_users u ON u.user_id=a.user_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY a.created_at DESC';return all($sql,$args);}
function display_datetime(mixed $value):string{$text=trim((string)$value);return preg_replace('/(\d{2}:\d{2}:\d{2})\.\d+/','$1',$text)??$text;}
if($page==='new-waiver'&&(int)($_GET['step']??1)===5){$w=waiver((int)($_GET['id']??0));if(!$w)exit('Waiver not found.');page_start('New waiver','new-waiver');steps6(5);echo '<div class="new-waiver-page signing-step-page">';employee_signing_panel($w);echo '</div>';page_end();exit;}
if($page==='approval-sign'){
    require_role(SIGNING_SUPERVISOR_ROLES);
    $waiverId = (int)($_GET['id'] ?? 0);
    $w = waiver($waiverId);
    if(!$w) exit('Waiver not found.');
    if($w['status'] === 'COMPLETED') redirect("index.php?page=waiver-view&id={$w['waiver_id']}");

    page_start('Supervisor acknowledgment', 'approval-sign');

    if($w['status'] !== 'AWAITING_SUPERVISOR' || empty($w['employee_signature_id'])) {
        ?>
        <div class="new-waiver-page">
            <section class="wizard-panel">
                <div class="empty">
                    <p>The employee signature must be completed before supervisor review.</p>
                    <a class="btn secondary" href="index.php?page=approvals">Back to pending acknowledgments</a>
                </div>
            </section>
        </div>
        <?php
        page_end();
        exit;
    }

    $savedAcks = all('SELECT acknowledgment_text,acknowledged_at FROM dbo.acd_mw_waiver_acknowledgments WHERE waiver_id=? AND is_acknowledged=1 ORDER BY display_order', [$waiverId]);
    $empInitial = strtoupper(substr((string)$w['employee_name'], 0, 1));
    $empPos = e($w['position'] ?? '');
    $empDept = e($w['department_name'] ?? '');
    $typeBadge = ($w['type_code'] === 'REFUSED_MEDICAL_TREATMENT') ? 'REFUSED MEDICAL TREATMENT' : 'UNFIT TO WORK';
    $supervisorName = e(user()['full_name'] ?? '');
    ?>
    <div class="new-waiver-page supervisor-signing-page">
        <div class="signing-step-intro">
            <div>
                <h2>Supervisor acknowledgment &amp; signature</h2>
                <p>Review the employee's submitted waiver details and provide supervisor endorsement.</p>
            </div>
            <div class="signing-header-actions">
                <a class="btn a4-preview-btn-top" target="_blank" href="pages/print.php?id=<?=$waiverId?>" data-a4-preview="pages/print.php?id=<?=$waiverId?>&amp;modal=1">
                    <?=wizard_svg('document')?> Preview A4 document
                </a>
                <span class="status-pill-badge awaiting-pill">AWAITING SUPERVISOR</span>
            </div>
        </div>

        <div class="wizard-layout wizard-layout-supervisor">
            <!-- Left Column: Employee & Waiver Review -->
            <section class="wizard-panel supervisor-review-panel">
                <div class="wizard-panel-heading">
                    <div class="details-heading-row">
                        <h2>Waiver review</h2>
                        <span class="type-pill-badge"><?=$typeBadge?></span>
                    </div>
                    <p>Review the employee's clinical waiver details and submitted signature.</p>
                </div>

                <div class="review-scroll-container">
                    <!-- Employee Card -->
                    <div class="review-card-section">
                        <div class="review-section-header">
                            <h3>Employee information</h3>
                        </div>
                        <div class="review-emp-grid">
                            <span class="avatar employee-avatar"><?=$empInitial?></span>
                            <div class="review-cell"><small>Name</small><b><?=e($w['employee_name'])?></b></div>
                            <div class="review-cell"><small>Employee number</small><b><?=e($w['employee_number'])?></b></div>
                            <div class="review-cell"><small>Department</small><b><?=$empDept?></b></div>
                            <div class="review-cell"><small>Position</small><b><?=$empPos?></b></div>
                        </div>
                    </div>

                    <!-- Clinical Details -->
                    <div class="review-card-section">
                        <div class="review-section-header">
                            <h3>Waiver details</h3>
                        </div>
                        <div class="review-details-grid">
                            <div class="review-cell"><small>Date &amp; time</small><b><?=e($w['waiver_date'].' &bull; '.$w['waiver_time'])?></b></div>
                            <div class="review-cell"><small>Medical / nurse personnel</small><b><?=e($w['medical_personnel_name'])?></b></div>
                            <div class="review-cell"><small>Transportation offered</small><b><?=e($w['transportation_offered'] ?: 'None')?></b></div>
                            <div class="review-cell full-span"><small>Recommendation</small><p class="review-text-val"><?=e($w['recommendation'])?></p></div>
                            <?php if(!empty($w['remarks'])): ?>
                                <div class="review-cell full-span"><small>Remarks</small><p class="review-text-val"><?=e($w['remarks'])?></p></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Waiver Statement -->
                    <div class="review-card-section">
                        <div class="review-section-header">
                            <h3>Waiver statement</h3>
                        </div>
                        <div class="waiver-statement-box">
                            <p><?=nl2br(e($w['signed_waiver_text']))?></p>
                        </div>
                    </div>

                    <!-- Employee Signature & Acknowledgments -->
                    <div class="review-card-section">
                        <div class="review-section-header">
                            <h3>Employee signature &amp; acknowledgments</h3>
                        </div>
                        <div class="review-acks-list">
                            <?php foreach($savedAcks as $ack): ?>
                                <div class="review-ack-item">
                                    <span class="review-check-icon"><?=wizard_svg('check-circle')?></span>
                                    <span><?=e($ack['acknowledgment_text'])?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="employee-signed-summary-box">
                            <div class="signed-sig-img-wrap">
                                <img src="<?=e($w['employee_signature'])?>" alt="Employee signature">
                            </div>
                            <div class="signed-sig-info">
                                <b><?=e($w['employee_printed'])?></b>
                                <small>Signed <?=e(display_datetime($w['employee_signed_at']))?></small>
                                <span class="verified-chip">&#10003; Verified via Company Credentials</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Right Column: Supervisor Signature Form -->
            <aside class="wizard-panel supervisor-sign-panel">
                <div class="wizard-panel-heading">
                    <h2>Supervisor endorsement</h2>
                    <p>Provide your digital signature to acknowledge and record this waiver.</p>
                </div>

                <form id="supervisor-form" method="post" class="supervisor-endorse-form" data-signature-form>
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="supervisor_sign">
                    <input type="hidden" name="waiver_id" value="<?=e($w['waiver_id'])?>">

                    <div class="field">
                        <label for="supervisor-printed-name">Printed legal name <span class="required-mark">*</span></label>
                        <div class="input-with-icon">
                            <span class="field-icon"><?=wizard_svg('personnel')?></span>
                            <input id="supervisor-printed-name" name="printed_name" value="<?=$supervisorName?>" required placeholder="Enter supervisor full name">
                        </div>
                    </div>

                    <div class="field signature-field">
                        <div class="sig-label-row">
                            <label>Digital signature <span class="required-mark">*</span></label>
                            <span class="sig-hint">Draw using mouse or touch</span>
                        </div>
                        <div class="signature-pad-container">
                            <canvas width="600" height="170" data-signature="supervisor_signature" class="sig-canvas"></canvas>
                            <button type="button" class="btn-clear-sig" data-clear aria-label="Clear signature">Clear</button>
                            <div class="signature-baseline-guide">Sign above the line</div>
                        </div>
                        <input type="hidden" id="supervisor_signature" name="signature" required>
                    </div>

                    <div class="supervisor-confirm-box">
                        <label class="review-confirm-checkbox">
                            <input type="checkbox" required name="supervisor_confirm" value="1" checked>
                            <span>I confirm that I have reviewed the employee's waiver record and accept this acknowledgment.</span>
                        </label>
                    </div>
                </form>
            </aside>
        </div>

        <footer class="wizard-universal-footer">
            <div class="footer-nav-left">
                <a class="btn secondary btn-nav-secondary" href="index.php?page=approvals">Back to pending acknowledgments</a>
            </div>
            <div class="footer-nav-center">
                <span class="footer-step-hint">Supervisor review & endorsement</span>
            </div>
            <div class="footer-nav-right">
                <button class="btn btn-nav-primary" type="submit" form="supervisor-form">Acknowledge and sign &rarr;</button>
            </div>
        </footer>
    </div>
    <?php
    page_end();
    exit;
}
if($page==='waiver-view'){
 $w=waiver((int)($_GET['id']??0));if(!$w)exit('Waiver not found.');
 if(user()['role_name']==='EMPLOYEE'&&(int)(user()['employee_id']??0)!==(int)$w['employee_id']){http_response_code(403);exit('Access denied.');}
 if($w['status']==='DRAFT')redirect("index.php?page=new-waiver&step=4&id={$w['waiver_id']}");
 if($w['status']==='AWAITING_EMPLOYEE_SIGNATURE')redirect("index.php?page=new-waiver&step=5&id={$w['waiver_id']}");
 if($w['status']==='AWAITING_SUPERVISOR'&&in_array(user()['role_name'],SIGNING_SUPERVISOR_ROLES,true))redirect("index.php?page=approval-sign&id={$w['waiver_id']}");
 page_start('Waiver record','waivers');document_html($w,true);?><div class="document-actions"><?php if($w['status']==='COMPLETED'):?><a class="btn" target="_blank" href="pages/print.php?id=<?=e($w['waiver_id'])?>">Print</a><a class="btn" href="pages/export-pdf.php?id=<?=e($w['waiver_id'])?>">Export PDF</a><?php endif?><a class="btn secondary" href="index.php?page=waivers">Back</a><?php if(user()['role_name']==='SYSTEM ADMIN'):?><button type="button" class="btn danger btn-delete-waiver" data-waiver-id="<?=(int)$w['waiver_id']?>" data-waiver-number="<?=e($w['waiver_number']??'Draft')?>" data-employee-name="<?=e($w['employee_name'])?>">Delete waiver</button><?php endif?></div><?php page_end();exit;
}
if($page==='settings'){
 require_role(['SYSTEM ADMIN']);
 $baseUrl=signing_setting('APP_BASE_URL','')??'';
 $suggestedUrl=signing_suggested_lan_url();
 $minutes=signing_token_minutes();

 $lastUpdate=one("SELECT TOP 1 s.updated_at, u.full_name, u.username FROM dbo.acd_mw_settings s LEFT JOIN dbo.acd_mw_users u ON u.user_id = s.updated_by WHERE s.updated_at IS NOT NULL ORDER BY s.updated_at DESC");

 $effectiveBaseUrl=$baseUrl!==''?$baseUrl:($suggestedUrl??'http://127.0.0.1/Waiver-Liability');
 $sampleSigningUrl=rtrim($effectiveBaseUrl,'/').'/pages/mobile-sign.php?token=SAMPLE-DEMO-TOKEN';
 $isLocalhost=(strpos($effectiveBaseUrl,'localhost')!==false||strpos($effectiveBaseUrl,'127.0.0.1')!==false);

 $serverIp=signing_detect_lan_address()??($_SERVER['SERVER_ADDR']??'127.0.0.1');

 $sampleQrSvg='';
 try {
  if(class_exists('\\GlobusStudio\\QRCode\\QRCode')){
   $sampleQrSvg=\GlobusStudio\QRCode\QRCode::svg($sampleSigningUrl,[
    'size'=>4,
    'margin'=>4,
    'mask'=>0,
    'foreground'=>'#245e39',
    'background'=>'#ffffff',
   ]);
  }
 } catch(Throwable $t){
  $sampleQrSvg='';
 }

 page_start('System & Signing Configuration','settings','Manage electronic signature network endpoints, mobile QR routing, and token lifetimes.','System administration');?>

 <form method="post" id="settings-form" class="settings-layout">
  <?=csrf_field()?>
  <input type="hidden" name="action" value="save_signing_settings">

  <!-- Main Settings Column -->
  <div class="settings-main-col">
   <!-- Card 1: Mobile E-Signing & QR Network -->
   <section class="settings-card">
    <header class="settings-card-header">
     <div class="settings-card-icon" aria-hidden="true">&#128246;</div>
     <div>
      <h3>Mobile E-Signing Network</h3>
      <p>Configure the network address encoded into generated QR codes so employee phones can reach the signing page.</p>
     </div>
    </header>

    <div class="settings-field">
     <label for="app-base-url">
      <span>Application Base URL</span>
      <span class="label-badge"><?= $baseUrl !== '' ? 'Custom URL active' : 'Automatic LAN detection active' ?></span>
     </label>
     <div class="settings-input-group">
      <span class="settings-input-icon">&#127760;</span>
      <input 
       id="app-base-url" 
       name="app_base_url" 
       type="url" 
       class="settings-input" 
       value="<?=e($baseUrl)?>" 
       placeholder="<?=e($suggestedUrl ?? 'http://10.2.0.25/Waiver-Liability')?>"
       autocomplete="off"
       spellcheck="false"
      >
     </div>
     <div class="settings-field-help">
      Must begin with <code>http://</code> or <code>https://</code> and omit any trailing slash. Do not include query parameters.
     </div>

     <?php if($suggestedUrl): ?>
     <div class="lan-suggestion-card">
      <div class="lan-suggestion-info">
       <span class="lan-suggestion-badge"><span></span> Detected Local Network Address</span>
       <span class="lan-suggestion-url" id="lan-url-display"><?=e($suggestedUrl)?></span>
      </div>
      <button 
       type="button" 
       class="btn-apply-lan" 
       id="btn-use-lan"
       data-url="<?=e($suggestedUrl)?>"
       title="Click to populate the Base URL with this address"
      >
       <span>&#10555;</span> Use this address
      </button>
     </div>
     <?php endif; ?>

     <?php if($isLocalhost && $baseUrl !== ''): ?>
     <div class="settings-notice warning">
      <span class="notice-icon">&#9888;</span>
      <div>
       <strong>Network Notice:</strong> Your base URL is currently set to <em>localhost</em>. Mobile phones connected to company Wi-Fi cannot resolve <code>localhost</code> to this computer. Use your detected IP address (<code><?=e($suggestedUrl ?? 'http://10.x.x.x/Waiver-Liability')?></code>) so smartphone cameras can connect.
      </div>
     </div>
     <?php else: ?>
     <div class="settings-notice info">
      <span class="notice-icon">&#8505;</span>
      <div>
       Employee smartphones and clinic staff devices on the network will open QR codes pointing to this endpoint.
      </div>
     </div>
     <?php endif; ?>
    </div>
   </section>

   <!-- Card 2: Security & Signature Token Expiration -->
   <section class="settings-card">
    <header class="settings-card-header">
     <div class="settings-card-icon" aria-hidden="true">&#128274;</div>
     <div>
      <h3>Token Lifecycle &amp; Security Expiration</h3>
      <p>Determine how long temporary mobile signing QR codes remain valid before expiring automatically.</p>
     </div>
    </header>

    <div class="settings-field">
     <label for="signing-token-minutes">
      <span>Signing Token Lifetime</span>
      <span class="label-badge">Permitted: 1 &ndash; 60 minutes</span>
     </label>

     <div class="expiry-control-container">
      <div class="expiry-input-row">
       <div class="expiry-number-wrap">
        <input 
         id="signing-token-minutes" 
         name="signing_token_minutes" 
         type="number" 
         min="1" 
         max="60" 
         class="expiry-number-input" 
         value="<?=e($minutes)?>" 
         required
        >
        <span class="expiry-unit">min</span>
       </div>
       <div class="expiry-slider-wrap">
        <input 
         type="range" 
         min="1" 
         max="60" 
         value="<?=e($minutes)?>" 
         class="expiry-slider" 
         id="expiry-slider"
         aria-label="Adjust expiry minutes"
        >
       </div>
      </div>

      <div>
       <div style="font-size: 11.5px; font-weight: 700; color: #5a666e; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em;">Quick Presets</div>
       <div class="preset-chip-group">
        <?php foreach([5, 10, 15, 30, 60] as $preset): ?>
        <button 
         type="button" 
         class="preset-chip <?= $minutes === $preset ? 'active' : '' ?>" 
         data-minutes="<?=$preset?>"
        >
         <?=$preset?> minutes <?= $preset === 10 ? ' (Default)' : '' ?>
        </button>
        <?php endforeach; ?>
       </div>
      </div>
     </div>

     <div class="settings-field-help" style="margin-top: 14px;">
      Shorter durations enhance security by limiting exposure if a QR code is left open on an unattended screen. Tokens are immediately invalidated once a signature is submitted.
     </div>
    </div>

    <!-- Action Bar -->
    <div class="settings-actions-footer">
     <div class="actions-note">
      <span>&#128274;</span>
      <span>
       <?php if($lastUpdate && !empty($lastUpdate['updated_at'])): ?>
        Last updated <?=e(display_datetime($lastUpdate['updated_at']))?><?php if(!empty($lastUpdate['full_name'])): ?> by <?=e($lastUpdate['full_name'])?><?php endif; ?>
       <?php else: ?>
        All changes are logged in activity records.
       <?php endif; ?>
      </span>
     </div>
     <div class="actions-buttons">
      <button type="reset" class="btn secondary" id="btn-reset-settings">Reset</button>
      <button type="submit" class="btn btn-save-settings">
       <span>&#10003;</span> Save settings
      </button>
     </div>
    </div>
   </section>
  </div>

  <!-- Side Column -->
  <aside class="settings-side-col">
   <!-- Live Endpoint & QR Preview Card -->
   <section class="settings-card">
    <header class="settings-card-header">
     <div class="settings-card-icon" aria-hidden="true">&#9638;</div>
     <div>
      <h3>Signing Endpoint Preview</h3>
      <p>Live simulation of the QR code generated for waiver signing.</p>
     </div>
    </header>

    <div class="qr-preview-box">
     <div class="qr-mockup-frame" id="qr-preview-frame">
      <?php if($sampleQrSvg): ?>
       <?=$sampleQrSvg?>
      <?php else: ?>
       <div style="display:grid;place-items:center;height:100%;color:#6f7d73;font-size:32px;">&#9638;</div>
      <?php endif; ?>
     </div>
     <div class="qr-preview-url" id="qr-preview-url" title="<?=e($sampleSigningUrl)?>">
      <?=e($sampleSigningUrl)?>
     </div>
     <?php if($isLocalhost): ?>
      <span class="qr-scan-badge warning" id="qr-status-badge">&#9888; Localhost Only (Restricted to PC)</span>
     <?php else: ?>
      <span class="qr-scan-badge ready" id="qr-status-badge">&#10003; Ready for Mobile Camera / LAN</span>
     <?php endif; ?>
    </div>

    <div style="font-size: 12px; color: #6a747b; line-height: 1.45;">
     When staff generates a waiver, this URL format is embedded into the QR code shown during employee and supervisor signing.
    </div>

    <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid #edf0ec;">
     <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #6f7880; margin-bottom: 8px;">Admin Navigation</div>
     <div class="admin-quick-links">
      <a href="index.php?page=audit" class="admin-link-btn">
       <span>&#9716;</span> Activity logs
      </a>
      <a href="index.php?page=users" class="admin-link-btn">
       <span>&#9823;</span> Users
      </a>
     </div>
    </div>
   </section>
  </aside>
 </form>

 <script>
 (function(){
  var urlInput = document.getElementById('app-base-url');
  var btnLan = document.getElementById('btn-use-lan');
  var minutesInput = document.getElementById('signing-token-minutes');
  var slider = document.getElementById('expiry-slider');
  var presetChips = document.querySelectorAll('.preset-chip');
  var qrUrl = document.getElementById('qr-preview-url');
  var qrBadge = document.getElementById('qr-status-badge');
  var form = document.getElementById('settings-form');

  // Apply detected LAN URL
  if (btnLan && urlInput) {
   btnLan.addEventListener('click', function(){
    var newUrl = this.getAttribute('data-url');
    if (newUrl) {
     urlInput.value = newUrl;
     urlInput.dispatchEvent(new Event('input', { bubbles: true }));
     urlInput.focus();
    }
   });
  }

  // Sync number input & slider
  if (minutesInput && slider) {
   minutesInput.addEventListener('input', function(){
    var val = parseInt(this.value, 10);
    if (!isNaN(val)) {
     if (val > 60) val = 60;
     if (val < 1) val = 1;
     slider.value = val;
     updateActivePreset(val);
    }
   });

   slider.addEventListener('input', function(){
    minutesInput.value = this.value;
    updateActivePreset(parseInt(this.value, 10));
   });
  }

  // Preset chips
  presetChips.forEach(function(chip){
   chip.addEventListener('click', function(){
    var mins = parseInt(this.getAttribute('data-minutes'), 10);
    if (!isNaN(mins)) {
     if (minutesInput) minutesInput.value = mins;
     if (slider) slider.value = mins;
     updateActivePreset(mins);
    }
   });
  });

  function updateActivePreset(mins) {
   presetChips.forEach(function(c){
    var chipMin = parseInt(c.getAttribute('data-minutes'), 10);
    if (chipMin === mins) {
     c.classList.add('active');
    } else {
     c.classList.remove('active');
    }
   });
  }

  // Dynamic update of preview URL on input
  if (urlInput && qrUrl) {
   urlInput.addEventListener('input', function(){
    var val = (this.value || this.placeholder || '').replace(/\/+$/, '');
    var fullUrl = val + '/pages/mobile-sign.php?token=SAMPLE-DEMO-TOKEN';
    qrUrl.textContent = fullUrl;
    qrUrl.setAttribute('title', fullUrl);

    if (qrBadge) {
     if (val.indexOf('localhost') !== -1 || val.indexOf('127.0.0.1') !== -1) {
      qrBadge.className = 'qr-scan-badge warning';
      qrBadge.innerHTML = '&#9888; Localhost Only (Restricted to PC)';
     } else {
      qrBadge.className = 'qr-scan-badge ready';
      qrBadge.innerHTML = '&#10003; Ready for Mobile Camera / LAN';
     }
    }
   });
  }

  // Handle form reset
  if (form) {
   form.addEventListener('reset', function(){
    setTimeout(function(){
     if (minutesInput) {
      var val = parseInt(minutesInput.value, 10);
      if (slider) slider.value = val;
      updateActivePreset(val);
     }
     if (urlInput) {
      urlInput.dispatchEvent(new Event('input', { bubbles: true }));
     }
    }, 10);
   });
  }
 })();
 </script>
 <?php page_end();exit;
}
$titles=['dashboard'=>'Dashboard','new-waiver'=>'New waiver','waivers'=>'Waivers','approvals'=>'Pending acknowledgments','approval-sign'=>'Supervisor acknowledgment','waiver-view'=>'Waiver record','templates'=>'Templates','users'=>'Users','audit'=>'Activity logs','settings'=>'Settings'];page_start($titles[$page]??'Waivers',$page);
if($page==='dashboard'){$c=one("SELECT COUNT(*) total,SUM(CASE WHEN status='DRAFT' THEN 1 ELSE 0 END) drafts,SUM(CASE WHEN status='COMPLETED' THEN 1 ELSE 0 END) completed,SUM(CASE WHEN status='AWAITING_EMPLOYEE_SIGNATURE' THEN 1 ELSE 0 END) employee_pending,SUM(CASE WHEN status='AWAITING_SUPERVISOR' THEN 1 ELSE 0 END) supervisor_pending FROM dbo.acd_mw_waivers");$metrics=[['All waivers','total','Total submitted','document','green'],['Employee signature pending','employee_pending','Awaiting employee action','edit','amber'],['Supervisor acknowledgment','supervisor_pending','Awaiting supervisor review','person','orange'],['Completed','completed','Fully completed','check','sage']];?><section class="dashboard-hero"><div class="hero-symbol">&#127793;</div><div class="hero-copy"><h2>Every signature, in one clear record.</h2><p>Create, sign, finalize, and retain the exact waiver presented.</p></div><div class="hero-art" aria-hidden="true"><span></span></div><a class="btn btn-primary hero-action" href="index.php?page=new-waiver&step=1"><span>+</span> Create new waiver</a></section><section class="dashboard-summary"><div class="metric-grid"><?php foreach($metrics as [$label,$key,$note,$icon,$tone]):?><article class="metric-card"><span class="metric-icon <?=$tone?>" aria-hidden="true"><?=match($icon){'document'=>'&#9638;','edit'=>'&#9998;','person'=>'&#9823;',default=>'&#10003;' }?></span><div class="metric-copy"><small><?=e($label)?></small><strong><?=e((int)($c[$key]??0))?></strong><p><?=e($note)?></p></div></article><?php endforeach?></div><aside class="task-card"><header><h2>Today's tasks</h2><span><?=e((int)($c['employee_pending']??0)+(int)($c['supervisor_pending']??0))?></span></header><a href="index.php?page=waivers"><i class="task-icon amber">&#9998;</i><span><b><?=e((int)($c['employee_pending']??0))?> waivers await employee signature</b><small>Take action to keep things moving</small></span><em>&rsaquo;</em></a><a href="index.php?page=approvals"><i class="task-icon orange">&#9823;</i><span><b><?=e((int)($c['supervisor_pending']??0))?> waivers await supervisor review</b><small>Your review is needed</small></span><em>&rsaquo;</em></a><footer><a href="index.php?page=waivers">View all tasks</a></footer></aside></section><section class="card recent-waivers"><header class="recent-header"><h2>Recent waivers</h2><div class="status-tabs" aria-label="Filter waivers by status"><button type="button" class="active" data-quick-status="">All <span><?=e((int)($c['total']??0))?></span></button><button type="button" data-quick-status="DRAFT">Draft <span><?=e((int)($c['drafts']??0))?></span></button><button type="button" data-quick-status="AWAITING EMPLOYEE SIGNATURE">Awaiting employee <span><?=e((int)($c['employee_pending']??0))?></span></button><button type="button" data-quick-status="AWAITING SUPERVISOR">Awaiting supervisor <span><?=e((int)($c['supervisor_pending']??0))?></span></button><button type="button" data-quick-status="COMPLETED">Completed <span><?=e((int)($c['completed']??0))?></span></button></div></header><?=table_html(rows())?></section><?php }
elseif($page==='new-waiver'){require_role(['SYSTEM ADMIN','CLINIC NURSE','HR / CLINIC ADMIN','EMPLOYEE']);$step=max(1,min(6,(int)($_GET['step']??1)));steps6($step);if($step===1){$emps=all("SELECT e.*,d.department_name FROM dbo.acd_mw_employees e LEFT JOIN dbo.acd_mw_departments d ON d.department_id=e.department_id WHERE e.is_active=1 ORDER BY e.full_name");?><section class="card"><h2 class="section-title">Select employee</h2><input id="search" class="search-large" placeholder="Search employee by ID or name"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="start"><div class="employee-results" data-filter><?php foreach($emps as $e):?><label class="employee-choice"><input type="radio" name="employee_id" value="<?=e($e['employee_id'])?>" required><span class="avatar"><?=e(substr($e['full_name'],0,1))?></span><span><b><?=e($e['full_name'])?></b><small><?=e($e['employee_number'].' · '.$e['department_name'].' · '.$e['position'])?></small></span><em>Select</em></label><?php endforeach?></div><button class="btn">Continue →</button></form></section><?php }elseif($step===2){?><section class="card"><h2 class="section-title">Choose waiver type</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="type"><div class="type-grid"><label class="type-card"><input type="radio" name="waiver_type" value="UNFIT_TO_WORK" required><span class="type-icon">↗</span><b>Unfit to work</b><p>Employee continues working despite being advised not to.</p></label><label class="type-card"><input type="radio" name="waiver_type" value="REFUSED_MEDICAL_TREATMENT" required><span class="type-icon">×</span><b>Refused medical treatment</b><p>Employee refuses recommended treatment, evaluation, or transport.</p></label></div><button class="btn">Continue →</button></form></section><?php }elseif($step===3){$d=$_SESSION['waiver_draft']??[];?><section class="card"><div class="document-kicker"><?=e(str_replace('_',' ',$d['type_code']??''))?></div><h2 class="section-title">Waiver details</h2><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="details"><div class="field"><label>Date</label><input disabled value="<?=e(date('F j, Y'))?>"></div><div class="field"><label>Time</label><input disabled value="<?=e(date('g:i A'))?>"></div><div class="field full"><label>Medical / nurse personnel</label><input name="medical_personnel" required></div><div class="field full"><label>Recommendation</label><textarea name="recommendation" required></textarea></div><?php if(($d['type_code']??'')==='REFUSED_MEDICAL_TREATMENT'):?><div class="field"><label>Transportation offered</label><select name="transportation"><?php foreach(TRANSPORTATION_OPTIONS as $x):?><option><?=e($x)?></option><?php endforeach?></select></div><?php endif?><div class="field full"><label>Remarks (optional)</label><textarea name="remarks"></textarea></div><button class="btn">Review waiver →</button></form></section><?php }else{$w=waiver((int)($_GET['id']??0));if(!$w)exit('Waiver not found.');if($step===4){document_html($w,false);?><form method="post" class="document-actions"><?=csrf_field()?><input type="hidden" name="action" value="review"><input type="hidden" name="waiver_id" value="<?=e($w['waiver_id'])?>"><button class="btn">Proceed to sign →</button></form><?php }elseif($step===5){document_html($w,false);?><section class="card sign-panel"><h2>Employee signature</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="employee_sign"><input type="hidden" name="waiver_id" value="<?=e($w['waiver_id'])?>"><div class="checks"><?php foreach(acknowledgments($w['type_code']) as $x):?><label class="check"><input type="checkbox" name="ack[]" value="<?=e($x)?>" required><span><?=e($x)?></span></label><?php endforeach?></div><?=signature_form('employee')?><button class="btn full-btn">Sign waiver →</button></form></section><?php }else{complete_html($w);}}}
elseif($page==='waivers'){?><section class="card"><div class="view-actions"><a class="btn" href="index.php?page=new-waiver&step=1">＋ New waiver</a></div><?=table_html(rows())?></section><?php }
elseif($page==='approvals'){require_role(['SUPERVISOR','SYSTEM ADMIN']);?><section class="card"><?=table_html(rows("WHERE w.status='AWAITING_SUPERVISOR'"),true)?></section><?php }
elseif($page==='waiver-view'){$w=waiver((int)($_GET['id']??0));if(!$w)exit('Not found.');if($w['status']==='DRAFT')redirect("index.php?page=new-waiver&step=4&id={$w['waiver_id']}");if($w['status']==='AWAITING_EMPLOYEE_SIGNATURE')redirect("index.php?page=new-waiver&step=5&id={$w['waiver_id']}");if($w['status']==='AWAITING_SUPERVISOR'&&in_array(user()['role_name'],['SUPERVISOR','SYSTEM ADMIN'],true))redirect("index.php?page=approval-sign&id={$w['waiver_id']}");document_html($w,true);?><div class="document-actions"><a class="btn" target="_blank" href="pages/print.php?id=<?=e($w['waiver_id'])?>" data-a4-preview="pages/print.php?id=<?=e($w['waiver_id'])?>&amp;modal=1">Preview A4</a><a class="btn" target="_blank" href="pages/print.php?id=<?=e($w['waiver_id'])?>">Print</a><a class="btn secondary" href="index.php?page=waivers">Back</a><?php if(user()['role_name']==='SYSTEM ADMIN'):?><button type="button" class="btn danger btn-delete-waiver" data-waiver-id="<?=(int)$w['waiver_id']?>" data-waiver-number="<?=e($w['waiver_number']??'Draft')?>" data-employee-name="<?=e($w['employee_name'])?>">Delete waiver</button><?php endif?></div>
<?php }elseif($page==='audit'){require_role(['SYSTEM ADMIN']);$activityRows=activity_data();$actions=all('SELECT DISTINCT action FROM dbo.acd_mw_activity_logs ORDER BY action');$logUsers=all('SELECT user_id,full_name FROM dbo.acd_mw_users WHERE is_active=1 ORDER BY full_name');$export=$_GET;unset($export['page']);$export['mode']='activity';?><section class="activity-intro compact-intro"><p>Review authentication, waiver lifecycle, printing, and administrative actions.</p><a class="btn secondary" href="export.php?<?=e(http_build_query($export))?>">Export filtered CSV</a></section><section class="card"><form method="get" class="filter-grid"><input type="hidden" name="page" value="audit"><div class="field"><label>Search record</label><input name="q" value="<?=e($_GET['q']??'')?>" placeholder="Description, entity, or ID"></div><div class="field"><label>Action</label><select name="action"><option value="">All actions</option><?php foreach($actions as $a):?><option value="<?=e($a['action'])?>" <?=($_GET['action']??'')===$a['action']?'selected':''?>><?=e(str_replace('_',' ',$a['action']))?></option><?php endforeach?></select></div><div class="field"><label>User</label><select name="user_id"><option value="">All users</option><?php foreach($logUsers as $u):?><option value="<?=e($u['user_id'])?>" <?=((int)($_GET['user_id']??0)===(int)$u['user_id'])?'selected':''?>><?=e($u['full_name'])?></option><?php endforeach?></select></div><div class="field"><label>Date from</label><input type="date" name="from" value="<?=e($_GET['from']??'')?>"></div><div class="field"><label>Date to</label><input type="date" name="to" value="<?=e($_GET['to']??'')?>"></div><div class="filter-actions"><button class="btn">Apply filters</button><a class="btn secondary" href="index.php?page=audit">Clear</a></div></form></section><section class="card section-gap"><div class="view-actions"><span class="muted"><?=e(count($activityRows))?> shown · latest first</span></div><?php if(!$activityRows):?><div class="empty">No activity matches these filters.</div><?php else:?><table><thead><tr><th>Date / time</th><th>User</th><th>Action</th><th>Record</th><th>Description</th></tr></thead><tbody><?php foreach($activityRows as $log):?><tr><td class="nowrap"><?=e(display_datetime($log['created_at']))?></td><td><b><?=e($log['full_name']??'System')?></b><small class="cell-sub"><?=e($log['username']??'Automated event')?></small></td><td><?=badge($log['action'])?></td><td><?=e($log['entity_type'].($log['entity_id']!==null?' #'.$log['entity_id']:''))?></td><td><?=e($log['description'])?></td></tr><?php endforeach?></tbody></table><?php endif?></section>
<?php }else{?><section class="card empty"><p>This waiver administration area is ready for configuration.</p></section><?php }page_end();
function steps6(int $current):void{
    $l=['Employee','Waiver type','Details','Review','Sign','Complete'];
    if($current>=2&&$current<6)echo '<div data-waiver-session data-waiver-step="'.$current.'"></div>';
    echo '<div class="steps">';
    foreach($l as $i=>$x){
        $n=$i+1;
        $isDone=$n<$current;
        $isCurrent=$n===$current;
        $cls=$isDone?'done':($isCurrent?'current':'');
        $num=$isDone?'✓':$n;
        echo '<div class="step '.$cls.'"><span>'.$num.'</span><small>'.e($x).'</small></div>';
    }
    echo '</div>';
}

function wizard_svg(string $name, string $class=''):string
{
    $icons=[
        'search'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
        'chevron-down'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>',
        'calendar'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'clock'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 6 12 12 16 14"/></svg>',
        'personnel'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M6 20v-1a6 6 0 0 1 12 0v1"/></svg>',
        'info'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        'document'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'check-circle'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="#187b4a" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#187b4a" stroke="none"/><polyline points="7 12 10.5 15.5 17 9" fill="none" stroke="#fff"/></svg>',
        'employee-empty'=>'<svg class="w-svg-large '.$class.'" viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="21" cy="12" r="5.5"/><path d="M10 33c0-6 4.9-11 11-11"/><rect x="21" y="21" width="13" height="13" rx="3"/><polyline points="24.5 27.5 27 30 31.5 25"/></svg>',
        'unfit'=>'<svg class="workflow-option-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="6" r="3.2"/><path d="M9.5 10.8a3.2 3.2 0 0 0 5 0"/><path d="M14.5 10.8c2 .3 4 1.2 4 3.2v6a1 1 0 0 1-1 1h-.5a1 1 0 0 1-1-1v-4"/><path d="M9.5 10.8c-2 .3-4 1.2-4 3.2v2"/><path d="M6.5 13l1.8 3"/><rect x="5.5" y="16" width="8.5" height="4" rx="1.2"/></svg>',
        'refused'=>'<svg class="workflow-option-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="4.2" width="14" height="16.8" rx="2.4"/><path d="M9 4.2V2.8a1.3 1.3 0 0 1 1.3-1.3h3.4A1.3 1.3 0 0 1 15 2.8v1.4"/><circle cx="12" cy="12.6" r="3.8"/><path d="M12 10.6v4M10 12.6h4"/></svg>',
        'unfit-shield'=>'<svg class="w-svg-badge '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#187b4a" stroke="none"/><line x1="12" y1="8" x2="12" y2="16" stroke="#fff" stroke-width="2.2"/><line x1="8" y1="12" x2="16" y2="12" stroke="#fff" stroke-width="2.2"/></svg>',
        'copy'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
        'mail'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
        'lock'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'refresh'=>'<svg class="w-svg '.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>'
    ];
    return $icons[$name]??'';
}

function render_new_waiver_design(int $step):void{
    $draft=$_SESSION['waiver_draft']??[];
    $editing=null;
    if($step===3 && !empty($_GET['id'])){
        $editing=one("SELECT TOP 1 w.*,t.type_code FROM dbo.acd_mw_waivers w JOIN dbo.acd_mw_waiver_types t ON t.waiver_type_id=w.waiver_type_id WHERE w.waiver_id=? AND w.status='DRAFT' AND w.created_by=?",[(int)$_GET['id'],(int)user()['user_id']]);
        if($editing){
            $draft=['employee_id'=>(int)$editing['employee_id'],'type_code'=>(string)$editing['type_code']];
            $_SESSION['waiver_draft']=$draft;
            $_SESSION['editing_waiver_id']=(int)$_GET['id'];
        }
    }
    if($step!==3)unset($_SESSION['editing_waiver_id']);
    
    $employee=null;
    if(!empty($draft['employee_id'])){
        $employee=one("SELECT e.*,d.department_name FROM dbo.acd_mw_employees e LEFT JOIN dbo.acd_mw_departments d ON d.department_id=e.department_id WHERE e.employee_id=?",[(int)$draft['employee_id']]);
    }
    if($step>=4){
        $employee=$employee?:one("SELECT e.*,d.department_name FROM dbo.acd_mw_employees e LEFT JOIN dbo.acd_mw_departments d ON d.department_id=e.department_id JOIN dbo.acd_mw_waivers w ON w.employee_id=e.employee_id WHERE w.waiver_id=?",[(int)($_GET['id']??0)]);
    }
    
    steps6($step);
    echo '<div class="new-waiver-page" data-new-waiver-step="'.e($step).'">';
    
    if($step===1){
        $emps=all("SELECT e.*,d.department_name FROM dbo.acd_mw_employees e LEFT JOIN dbo.acd_mw_departments d ON d.department_id=e.department_id WHERE e.is_active=1 ORDER BY e.full_name");
        $departments=[];
        foreach($emps as $emp){
            if(!empty($emp['department_name']))$departments[(string)$emp['department_name']]=true;
        }
        ksort($departments);
        ?>
        <div class="wizard-layout wizard-layout-employee">
            <section class="wizard-panel employee-picker-panel">
                <div class="wizard-panel-heading">
                    <h2>Select employee</h2>
                    <p>Choose the employee this waiver is for.</p>
                </div>
                <div class="employee-toolbar">
                    <label class="wizard-search">
                        <span class="search-icon" aria-hidden="true"><?=wizard_svg('search')?></span>
                        <input id="employee-search" type="search" placeholder="Search by employee name or ID" data-employee-search autocomplete="off">
                    </label>
                    <label class="wizard-select">
                        <select aria-label="Department filter" data-department-filter>
                            <option value="">All departments</option>
                            <?php foreach(array_keys($departments) as $dept): ?>
                                <option value="<?=e($dept)?>"><?=e($dept)?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="select-chevron" aria-hidden="true"><?=wizard_svg('chevron-down')?></span>
                    </label>
                </div>
                <form id="step1-form" method="post" data-employee-form>
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="start">
                    <div class="employee-results wizard-employee-results" data-employee-results>
                        <?php foreach($emps as $index => $emp):
                            $initial=strtoupper(substr((string)$emp['full_name'],0,1));
                            $empDept=$emp['department_name']??'General';
                            $empPos=$emp['position']??'Staff';
                            $meta=e($emp['employee_number']).' &bull; '.e($empDept).' &bull; '.e($empPos);
                            $isHidden = ($index >= 6);
                        ?>
                        <label class="employee-choice<?=$isHidden ? ' is-hidden' : ''?>" data-employee-row data-employee-text="<?=e(strtolower($emp['full_name'].' '.$emp['employee_number'].' '.$empDept.' '.$empPos))?>" data-department="<?=e($emp['department_name']??'')?>" data-employee-id="<?=e($emp['employee_id'])?>" data-employee-name="<?=e($emp['full_name'])?>" data-employee-number="<?=e($emp['employee_number'])?>" data-employee-position="<?=e($empPos)?>" data-employee-department="<?=e($empDept)?>" data-employee-email="<?=e($emp['email']??'')?>"<?=$isHidden ? ' hidden style="display:none !important;"' : ''?>>
                            <input type="radio" name="employee_id" value="<?=e($emp['employee_id'])?>" required>
                            <span class="custom-radio" aria-hidden="true"><span class="radio-inner"></span></span>
                            <span class="avatar employee-avatar"><?=e($initial)?></span>
                            <span class="employee-choice-copy">
                                <b><?=e($emp['full_name'])?></b>
                                <small><?=$meta?></small>
                            </span>
                            <span class="selected-pill-badge" data-selected-label>Selected</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="wizard-footer employee-footer">
                        <span class="results-count" data-employee-count>Showing <?=count($emps)>0?1:0?>–<?=min(6,count($emps))?> of <?=count($emps)?> employees</span>
                        <div class="employee-pagination" data-employee-pagination>
                            <button type="button" class="page-button" data-employee-prev aria-label="Previous employees" disabled>&lsaquo;</button>
                            <span data-employee-pages>
                                <?php
                                $totalPages = max(1, (int)ceil(count($emps) / 6));
                                for($p = 1; $p <= $totalPages; $p++): ?>
                                    <button type="button" class="page-button<?= $p === 1 ? ' active' : '' ?>"><?= $p ?></button>
                                <?php endfor; ?>
                            </span>
                            <button type="button" class="page-button" data-employee-next aria-label="Next employees"<?= $totalPages <= 1 ? ' disabled' : '' ?>>&rsaquo;</button>
                        </div>
                    </div>
                </form>
            </section>
            
            <aside class="wizard-panel selected-employee-panel" data-selected-employee-panel>
                <h2>Selected employee</h2>
                <div class="selected-employee-empty" data-employee-empty-box>
                    <div class="selected-employee-symbol" aria-hidden="true"><?=wizard_svg('employee-empty')?></div>
                    <h3>No employee selected</h3>
                    <p>Select an employee from the list to continue.</p>
                </div>
            </aside>
        </div>

        <footer class="wizard-universal-footer">
            <div class="footer-nav-left">
                <a class="btn secondary btn-nav-secondary" href="index.php?page=dashboard">Cancel</a>
            </div>
            <div class="footer-nav-center">
                <span class="footer-step-hint" data-step1-hint>Select an employee from the list to continue</span>
            </div>
            <div class="footer-nav-right">
                <button class="btn btn-nav-primary" type="submit" form="step1-form" disabled data-employee-continue>Continue to waiver type &rarr;</button>
            </div>
        </footer>
        <?php
    }elseif($step===2){
        $empName=e($employee['full_name']??'');
        $empInitial=strtoupper(substr($employee['full_name']??'E',0,1));
        $empNumber=e($employee['employee_number']??'');
        $empDept=e($employee['department_name']??'');
        $empPos=e($employee['position']??'');
        $subLine=trim($empDept.' &bull; '.$empPos,' &bull;');
        ?>
        <div class="wizard-layout wizard-layout-type">
            <section class="wizard-panel type-picker-panel">
                <div class="wizard-panel-heading">
                    <h2>Choose waiver type</h2>
                    <p>Select the situation that best applies to this employee.</p>
                </div>
                <form id="step2-form" method="post" data-type-form class="type-form-wrapper">
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="type">
                    <div class="type-grid wizard-type-grid">
                        <label class="type-card" data-type-card="UNFIT_TO_WORK">
                            <input type="radio" name="waiver_type" value="UNFIT_TO_WORK" required>
                            <div class="type-card-top">
                                <span class="type-icon"><?=wizard_svg('unfit')?></span>
                                <span class="custom-radio" aria-hidden="true"><span class="radio-inner"></span></span>
                            </div>
                            <b>Unfit to work</b>
                            <p>The employee was advised not to work but chooses to continue shift against medical advisement.</p>
                            <div class="type-card-divider"></div>
                            <ul class="type-feature-list">
                                <li>Requires medical recommendation and clinical evaluation</li>
                                <li>Formal waiver and discharge of medical liability</li>
                                <li>Requires employee digital signature and supervisor review</li>
                            </ul>
                        </label>
                        
                        <label class="type-card" data-type-card="REFUSED_MEDICAL_TREATMENT">
                            <input type="radio" name="waiver_type" value="REFUSED_MEDICAL_TREATMENT" required>
                            <div class="type-card-top">
                                <span class="type-icon"><?=wizard_svg('refused')?></span>
                                <span class="custom-radio" aria-hidden="true"><span class="radio-inner"></span></span>
                            </div>
                            <b>Refused medical treatment</b>
                            <p>The employee declines recommended treatment, medication, hospital evaluation, or emergency transport.</p>
                            <div class="type-card-divider"></div>
                            <ul class="type-feature-list">
                                <li>Documents refusal of emergency transport or clinic care</li>
                                <li>Formal waiver and discharge of medical liability</li>
                                <li>Requires employee digital signature and supervisor review</li>
                            </ul>
                        </label>
                    </div>
                </form>
            </section>
            
            <aside class="wizard-panel employee-summary-side">
                <h2>Selected employee</h2>
                <div class="summary-identity">
                    <span class="avatar employee-avatar"><?=$empInitial?></span>
                    <div class="summary-name-col">
                        <h3><?=$empName?></h3>
                        <div class="summary-sub-row">
                            <span class="emp-num"><?=$empNumber?></span>
                            <span class="active-chip">Active</span>
                        </div>
                    </div>
                </div>
                
                <dl class="summary-details-list">
                    <div><dt>Department</dt><dd><?=$empDept?></dd></div>
                    <div><dt>Position</dt><dd><?=$empPos?></dd></div>
                    <div><dt>Email</dt><dd><?=e($employee['email']??($empName?strtolower(preg_replace('/[^a-z]/','.',$empName)).'@company.com':''))?></dd></div>
                </dl>

                <div class="summary-note">
                    <span class="note-icon"><?=wizard_svg('info')?></span>
                    <span>The selected waiver type determines the details, clinical questions, and acknowledgment shown in subsequent steps.</span>
                </div>

                <div class="side-change-wrap">
                    <a class="btn secondary btn-nav-secondary change-emp-btn" href="index.php?page=new-waiver&step=1" data-leave-workflow="true">Change employee</a>
                </div>
            </aside>
        </div>

        <footer class="wizard-universal-footer">
            <div class="footer-nav-left">
                <a class="btn secondary btn-nav-secondary" href="index.php?page=new-waiver&step=1" data-leave-workflow="true">Back</a>
            </div>
            <div class="footer-nav-center">
                <span class="footer-step-hint" data-type-hint>Select a waiver type to continue</span>
            </div>
            <div class="footer-nav-right">
                <button class="btn btn-nav-primary" type="submit" form="step2-form" disabled data-type-continue>Continue to details &rarr;</button>
            </div>
        </footer>
        <?php
    }elseif($step===3){
        $type=(string)($draft['type_code']??'UNFIT_TO_WORK');
        $typeName=$type==='REFUSED_MEDICAL_TREATMENT'?'REFUSED MEDICAL TREATMENT':'UNFIT TO WORK';
        $empName=e($employee['full_name']??'');
        $empInitial=strtoupper(substr($employee['full_name']??'E',0,1));
        $empNumber=e($employee['employee_number']??'');
        $empDept=e($employee['department_name']??'');
        $empPos=e($employee['position']??'');
        $subLine=trim($empDept.' &bull; '.$empPos,' &bull;');
        
        $typeTitle=$type==='REFUSED_MEDICAL_TREATMENT'?'Refused medical treatment':'Unfit to work';
        $typeDesc=$type==='REFUSED_MEDICAL_TREATMENT'?'Employee declines recommended treatment, evaluation, or transport.':'Employee chooses to continue working against medical advice.';
        
        $currentDate=date('F j, Y');
        $currentTime=date('g:i A');
        $detailsBack=$editing?'index.php?page=new-waiver&step=4&id='.(int)$editing['waiver_id']:'index.php?page=new-waiver&step=2';
        ?>
        <div class="wizard-layout wizard-layout-details">
            <section class="wizard-panel details-panel">
                <div class="wizard-panel-heading inline-heading">
                    <div>
                        <div class="details-heading-row">
                            <h2>Waiver details</h2>
                            <span class="type-pill-badge"><?=$typeName?></span>
                        </div>
                        <p>Enter the medical recommendation and supporting information.</p>
                    </div>
                </div>
                <form id="step3-form" method="post" class="wizard-form details-form" data-details-form>
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="details">
                    <?php if($editing): ?>
                        <input type="hidden" name="waiver_id" value="<?=(int)$editing['waiver_id']?>">
                    <?php endif; ?>
                    
                    <div class="form-row-2">
                        <div class="field">
                            <label>Date</label>
                            <div class="input-with-icon">
                                <span class="field-icon"><?=wizard_svg('calendar')?></span>
                                <input readonly value="<?=e($editing?date('F j, Y',strtotime($editing['waiver_date'])):$currentDate)?>">
                            </div>
                        </div>
                        <div class="field">
                            <label>Time</label>
                            <div class="input-with-icon">
                                <span class="field-icon"><?=wizard_svg('clock')?></span>
                                <input readonly value="<?=e($editing?date('g:i A',strtotime($editing['waiver_time'])):$currentTime)?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="field full">
                        <label>Medical / nurse personnel</label>
                        <div class="input-with-icon">
                            <span class="field-icon"><?=wizard_svg('personnel')?></span>
                            <input name="medical_personnel" value="<?=e($editing['medical_personnel_name']??(user()['full_name']??''))?>" placeholder="Select medical personnel" required>
                        </div>
                    </div>
                    
                    <div class="field full">
                        <label>Recommendation <span class="required-mark">*</span></label>
                        <p class="field-help">Describe the recommendation provided to the employee.</p>
                        <div class="textarea-wrapper">
                            <textarea id="recommendation-text" name="recommendation" maxlength="1000" placeholder="Enter the medical recommendation..." required><?=e($editing['recommendation']??'')?></textarea>
                            <span class="char-counter" id="recommendation-counter">0 / 1,000</span>
                        </div>
                    </div>
                    
                    <?php if($type==='REFUSED_MEDICAL_TREATMENT'): ?>
                    <div class="field full">
                        <label>Transportation offered</label>
                        <div class="select-with-icon">
                            <select name="transportation">
                                <?php foreach(TRANSPORTATION_OPTIONS as $option): ?>
                                    <option<?=($editing['transportation_offered']??'')===$option?' selected':''?>><?=e($option)?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="select-chevron"><?=wizard_svg('chevron-down')?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="field full">
                        <label>Remarks <span class="optional-mark">Optional</span></label>
                        <textarea name="remarks" maxlength="1000" placeholder="Add any additional notes or context..."><?=e($editing['remarks']??'')?></textarea>
                    </div>
                </form>
            </section>
            
            <aside class="wizard-panel waiver-summary-side">
                <h2>Waiver summary</h2>
                
                <div class="summary-section">
                    <p class="summary-section-label">Selected employee</p>
                    <div class="summary-identity compact">
                        <span class="avatar employee-avatar"><?=$empInitial?></span>
                        <div class="summary-name-col">
                            <h3><?=$empName?></h3>
                            <p class="emp-num"><?=$empNumber?></p>
                        </div>
                    </div>
                    <p class="side-emp-line"><?=$subLine?></p>
                </div>
                
                <div class="summary-section">
                    <p class="summary-section-label">Waiver classification</p>
                    <div class="summary-type-row">
                        <span class="summary-type-icon"><?=wizard_svg('unfit-shield')?></span>
                        <div class="summary-type-copy">
                            <b><?=$typeTitle?></b>
                            <p><?=$typeDesc?></p>
                        </div>
                    </div>
                </div>

                <div class="review-checklist summary-checklist-box">
                    <div class="check-item"><?=wizard_svg('check-circle')?> <span>Employee profile confirmed</span></div>
                    <div class="check-item"><?=wizard_svg('check-circle')?> <span>Waiver category locked</span></div>
                    <div class="check-item"><?=wizard_svg('check-circle')?> <span>Ready for clinical review</span></div>
                </div>
                
                <div class="summary-note">
                    <span class="note-icon"><?=wizard_svg('info')?></span>
                    <span>You can review all information in the next step before proceeding to employee signatures.</span>
                </div>
                
                <div class="side-change-wrap">
                    <a class="btn secondary btn-nav-secondary change-emp-btn" href="index.php?page=new-waiver&step=2" data-leave-workflow="true">Change employee or waiver type</a>
                </div>
            </aside>
        </div>

        <footer class="wizard-universal-footer">
            <div class="footer-nav-left">
                <a class="btn secondary btn-nav-secondary" href="<?=e($detailsBack)?>" data-leave-workflow="true">Back</a>
            </div>
            <div class="footer-nav-center">
                <span class="footer-step-hint">Step 3 of 6 &bull; Enter required medical details</span>
            </div>
            <div class="footer-nav-right">
                <button class="btn btn-nav-primary" type="submit" form="step3-form">Review waiver &rarr;</button>
            </div>
        </footer>
        <?php
    }elseif($step===4){
        $w=waiver((int)($_GET['id']??0));
        if(!$w){
            echo '<section class="card empty">Waiver not found.</section></div>';
            return;
        }
        $empInitial=strtoupper(substr((string)$w['employee_name'],0,1));
        $typeBadge=$w['type_code']==='REFUSED_MEDICAL_TREATMENT'?'REFUSED MEDICAL TREATMENT':'UNFIT TO WORK';
        $acks=acknowledgments($w['type_code']);
        $dateFormatted=date('M j, Y',strtotime($w['waiver_date'])).' &bull; '.date('g:i A',strtotime($w['waiver_time']));
        ?>
        <div class="wizard-layout wizard-layout-review">
            <section class="wizard-panel review-panel">
                <div class="wizard-panel-heading inline-heading">
                    <div>
                        <h2>Review waiver</h2>
                        <p>Confirm the information below before continuing to signatures.</p>
                    </div>
                    <span class="draft-badge">DRAFT</span>
                </div>
                
                <div class="review-scroll-container">
                    <!-- Section 1: Employee information -->
                    <div class="review-card-section">
                        <div class="review-section-header">
                            <h3>Employee information</h3>
                            <a class="edit-link" href="index.php?page=new-waiver&step=1">Edit</a>
                        </div>
                        <div class="review-emp-grid">
                            <span class="avatar employee-avatar"><?=$empInitial?></span>
                            <div class="review-cell">
                                <small>Name</small>
                                <b><?=e($w['employee_name'])?></b>
                            </div>
                            <div class="review-cell">
                                <small>Employee number</small>
                                <b><?=e($w['employee_number'])?></b>
                            </div>
                            <div class="review-cell">
                                <small>Department</small>
                                <b><?=e($w['department_name'])?></b>
                            </div>
                            <div class="review-cell">
                                <small>Position</small>
                                <b><?=e($w['position'])?></b>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 2: Waiver details -->
                    <div class="review-card-section">
                        <div class="review-section-header">
                            <h3>Waiver details</h3>
                            <a class="edit-link" href="index.php?page=new-waiver&step=3&id=<?=(int)$w['waiver_id']?>">Edit</a>
                        </div>
                        <div class="review-details-grid">
                            <div class="review-cell">
                                <small>Waiver type</small>
                                <div><span class="type-pill-badge"><?=$typeBadge?></span></div>
                            </div>
                            <div class="review-cell">
                                <small>Date and time</small>
                                <b><?=$dateFormatted?></b>
                            </div>
                            <div class="review-cell">
                                <small>Medical / nurse personnel</small>
                                <b><?=e($w['medical_personnel_name']??'N/A')?></b>
                            </div>
                            <div class="review-cell full-span">
                                <small>Recommendation</small>
                                <p class="review-text-val"><?=nl2br(e($w['recommendation']??''))?></p>
                            </div>
                            <?php if($w['type_code']==='REFUSED_MEDICAL_TREATMENT'): ?>
                            <div class="review-cell">
                                <small>Transportation</small>
                                <b><?=e($w['transportation_offered']??'None')?></b>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($w['remarks'])): ?>
                            <div class="review-cell full-span">
                                <small>Remarks</small>
                                <p class="review-text-val"><?=nl2br(e($w['remarks']))?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Section 3: Waiver statement -->
                    <div class="review-card-section">
                        <div class="review-section-header">
                            <h3>Waiver statement</h3>
                        </div>
                        <div class="waiver-statement-box">
                            <p><?=nl2br(e($w['signed_waiver_text']))?></p>
                        </div>
                    </div>
                    
                    <!-- Section 4: Employee acknowledgments -->
                    <div class="review-card-section">
                        <div class="review-section-header">
                            <h3>Employee acknowledgments</h3>
                        </div>
                        <div class="review-acks-list">
                            <?php foreach($acks as $ack): ?>
                            <div class="review-ack-item">
                                <span class="square-box"></span>
                                <span><?=e($ack)?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
            
            <aside class="wizard-panel review-summary-side">
                <h2>Review summary</h2>
                
                <div class="review-checklist">
                    <div class="check-item"><?=wizard_svg('check-circle')?> <span>Employee selected</span></div>
                    <div class="check-item"><?=wizard_svg('check-circle')?> <span>Waiver type selected</span></div>
                    <div class="check-item"><?=wizard_svg('check-circle')?> <span>Required details complete</span></div>
                </div>
                
                <div class="a4-ready-box">
                    <div class="a4-header">
                        <span class="a4-icon"><?=wizard_svg('document')?></span>
                        <b>A4 document ready</b>
                    </div>
                    <p>Printing or exporting will automatically use the official A4 document format.</p>
                    <a class="btn a4-preview-btn" target="_blank" href="pages/print.php?id=<?=(int)$w['waiver_id']?>">
                        <?=wizard_svg('document')?> Preview A4 document
                    </a>
                </div>
                
                <form id="step4-form" method="post" class="review-action-form" data-review-form>
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="waiver_id" value="<?=(int)$w['waiver_id']?>">
                    
                    <label class="review-confirm-checkbox">
                        <input type="checkbox" name="review_confirm" value="1" required data-review-checkbox>
                        <span>I have reviewed the information and confirm it is correct.</span>
                    </label>
                </form>
                
                <p class="review-bottom-note">You can still edit this waiver before it is signed.</p>
            </aside>
        </div>

        <footer class="wizard-universal-footer">
            <div class="footer-nav-left">
                <a class="btn secondary btn-nav-secondary" href="index.php?page=new-waiver&step=3&id=<?=(int)$w['waiver_id']?>" data-leave-workflow="true">Back to details</a>
            </div>
            <div class="footer-nav-center">
                <span class="footer-step-hint" data-review-hint>Please confirm the review checkbox to proceed</span>
            </div>
            <div class="footer-nav-right">
                <button class="btn btn-nav-primary" type="submit" form="step4-form" disabled data-review-submit>Continue to signatures &rarr;</button>
            </div>
        </footer>
        <?php
    }else{
        $w=waiver((int)($_GET['id']??0));
        if($w)complete_html($w);
        else echo '<section class="card empty">Waiver not found.</section>';
    }
    echo '</div>';
}
function waiver_route(array $r,bool $approval=false):array{if($approval)return["index.php?page=approval-sign&id={$r['waiver_id']}",'Review'];return match($r['status']){'DRAFT'=>["index.php?page=new-waiver&step=4&id={$r['waiver_id']}",'Continue'],'AWAITING_EMPLOYEE_SIGNATURE'=>["index.php?page=new-waiver&step=5&id={$r['waiver_id']}",'Continue signing'],'AWAITING_SUPERVISOR'=>in_array(user()['role_name'],['SUPERVISOR','SYSTEM ADMIN'],true)?["index.php?page=approval-sign&id={$r['waiver_id']}",'Review & sign']:["index.php?page=waiver-view&id={$r['waiver_id']}",'View'],default=>["index.php?page=waiver-view&id={$r['waiver_id']}",'View']};}
function table_html(array $rows,bool $approval=false):string{if(!$rows)return'<div class="empty">No waivers found.</div>';$isAdmin=user()&&user()['role_name']==='SYSTEM ADMIN';ob_start();?>
<?php if($isAdmin):?>
<div class="bulk-actions-toolbar" data-bulk-toolbar style="display:none;">
    <div class="bulk-info">
        <span class="bulk-badge"><strong data-selected-count>0</strong> selected</span>
        <span class="bulk-subtext" data-bulk-subtext>Select all waivers on this list</span>
    </div>
    <div class="bulk-buttons">
        <button type="button" class="btn secondary btn-sm" data-bulk-deselect>Deselect all</button>
        <button type="button" class="btn danger btn-sm" data-bulk-delete>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;vertical-align:-2px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            <span data-bulk-delete-text>Delete selected</span>
        </button>
    </div>
</div>
<?php endif;?>
<table class="waivers-table" data-waivers-table>
<thead><tr>
<?php if($isAdmin):?>
<th class="col-checkbox" style="width:38px;text-align:center;"><input type="checkbox" class="waiver-select-all" data-select-all-waivers title="Select all waivers" aria-label="Select all waivers"></th>
<?php endif;?>
<th>Waiver</th><th>Employee</th><th>Department</th><th>Type</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody><?php foreach($rows as $r):[$url,$label]=waiver_route($r,$approval);?><tr data-waiver-row id="waiver-row-<?=(int)$r['waiver_id']?>">
<?php if($isAdmin):?>
<td class="col-checkbox" style="width:38px;text-align:center;"><input type="checkbox" class="waiver-select-item" data-waiver-checkbox value="<?=(int)$r['waiver_id']?>" data-waiver-number="<?=e($r['waiver_number']??'Draft')?>" data-employee-name="<?=e($r['full_name'])?>" aria-label="Select waiver <?=e($r['waiver_number']??'Draft')?>"></td>
<?php endif;?>
<td><b><?=e($r['waiver_number']??'Draft')?></b></td><td><?=e($r['full_name'])?><small class="cell-sub"><?=e($r['employee_number'])?></small></td><td><?=e($r['department_name'])?></td><td><?=e($r['type_name'])?></td><td><?=badge($r['status'])?></td><td><?=e(display_datetime($r['created_at']))?></td><td><div class="row-actions"><a class="btn secondary btn-sm" href="<?=e($url)?>"><?=e($label)?></a><?php if($isAdmin):?><button type="button" class="btn danger btn-sm btn-delete-waiver" data-waiver-id="<?=(int)$r['waiver_id']?>" data-waiver-number="<?=e($r['waiver_number']??'Draft')?>" data-employee-name="<?=e($r['full_name'])?>">Delete</button><?php endif?></div></td></tr><?php endforeach?></tbody></table><?php return ob_get_clean();}
function document_html(array $w,bool $signed):void{?><article class="legal-document"><header><span>Company internal record</span><h2>WAIVER OF LIABILITY</h2><h3><?=e($w['type_name'])?></h3><div><?=e($w['waiver_number']??'DRAFT — NOT FINALIZED')?></div></header><section><h4>Employee information</h4><dl class="document-grid"><div><dt>Name</dt><dd><?=e($w['employee_name'])?></dd></div><div><dt>Employee number</dt><dd><?=e($w['employee_number'])?></dd></div><div><dt>Department</dt><dd><?=e($w['department_name'])?></dd></div><div><dt>Position</dt><dd><?=e($w['position'])?></dd></div></dl></section><section><h4>Waiver details</h4><dl class="document-grid"><div><dt>Date / time</dt><dd><?=e($w['waiver_date'].' '.$w['waiver_time'])?></dd></div><div><dt>Medical / nurse personnel</dt><dd><?=e($w['medical_personnel_name'])?></dd></div><div class="wide"><dt>Recommendation</dt><dd><?=e($w['recommendation'])?></dd></div><div><dt>Transportation</dt><dd><?=e($w['transportation_offered'])?></dd></div></dl></section><section><h4>Waiver statement</h4><p class="waiver-copy"><?=nl2br(e($w['signed_waiver_text']))?></p></section><section><h4>Acknowledgments</h4><?php foreach(acknowledgments($w['type_code']) as $x):?><p class="document-ack">□ <?=e($x)?></p><?php endforeach?></section><?php if($signed):?><section><h4>Signatures</h4><div class="signature-grid"><div><b>Employee</b><?php if($w['employee_signature']):?><img src="<?=e($w['employee_signature'])?>" alt="Employee signature"><?php endif?><p><?=e($w['employee_printed']??'Pending')?></p><small><?=e($w['employee_signed_at']??'Not signed')?></small></div><div><b>Supervisor</b><?php if($w['supervisor_signature']):?><img src="<?=e($w['supervisor_signature'])?>" alt="Supervisor signature"><?php endif?><p><?=e($w['supervisor_printed']??'Pending')?></p><small><?=e($w['supervisor_signed_at']??'Not signed')?></small></div></div><?php if($w['document_hash']):?><p class="hash">Document hash: <?=e($w['document_hash'])?></p><?php endif?></section><?php endif?></article><?php }
function signature_form(string $id):string{return'<div class="form-grid"><div class="field full"><label>Printed legal name</label><input name="printed_name" required></div><div class="field full"><label>Signature</label><div class="signature"><canvas width="800" height="260" data-signature="'.$id.'_signature"></canvas><button type="button" data-clear>Clear</button></div><input type="hidden" id="'.$id.'_signature" name="signature"></div></div>';}
function complete_html(array $w):void{?><section class="completion"><span class="completion-mark">✓</span><h2><?=$w['status']==='COMPLETED'?'Waiver completed':'Employee signed'?></h2><p><?=e($w['waiver_number']??'Waiting for supervisor acknowledgment')?></p><div class="completion-list"><span>Employee signature <b>✓ Completed</b></span><span>Supervisor signature <b><?=$w['supervisor_signed_at']?'✓ Completed':'Pending'?></b></span><span>Finalized <b><?=$w['finalized_at']?'✓ Completed':'Pending'?></b></span></div><div class="actions"><a class="btn" href="index.php?page=waiver-view&id=<?=e($w['waiver_id'])?>">View waiver</a><a class="btn secondary" href="index.php?page=new-waiver&step=1">Create another</a></div></section><?php }
