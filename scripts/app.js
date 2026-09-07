document.querySelector('.menu')?.addEventListener('click',()=>document.querySelector('.sidebar').classList.toggle('open'));

// Auto-open server-generated modal dialogs (replacing banner alerts)
document.querySelectorAll('dialog[data-auto-open]').forEach(dialog=>{
    if(typeof dialog.showModal==='function')dialog.showModal();
});

// Toast notification manager (auto-dismiss, pause-on-hover, close button, programmatic showToast)
(function initToastSystem(){
    function setupToast(toastEl, duration = 4000) {
        if (!toastEl || toastEl.dataset.toastInit) return;
        toastEl.dataset.toastInit = '1';
        const closeBtn = toastEl.querySelector('.toast-close');
        let timer = null;
        let startTime = Date.now();
        let remaining = duration;

        function hide() {
            if (timer) clearTimeout(timer);
            toastEl.classList.add('toast-hiding');
            setTimeout(() => {
                const container = toastEl.closest('.app-toast-container');
                toastEl.remove();
                if (container && container.children.length === 0) {
                    container.remove();
                }
            }, 300);
        }

        function startTimer() {
            startTime = Date.now();
            timer = setTimeout(hide, remaining);
        }

        function pauseTimer() {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
            remaining -= (Date.now() - startTime);
            if (remaining < 500) remaining = 500;
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                hide();
            });
        }

        toastEl.addEventListener('mouseenter', pauseTimer);
        toastEl.addEventListener('mouseleave', startTimer);

        startTimer();
    }

    document.querySelectorAll('.app-toast').forEach(toast => setupToast(toast, 4000));

    window.showToast = function(message, type = 'success', duration = 4000) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'app-toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `app-toast toast-${type}`;
        toast.setAttribute('role', 'status');

        const titleText = type === 'error' ? 'Notice' : (type === 'info' ? 'Information' : 'Success');
        const iconSvg = type === 'error'
            ? '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="8"></circle><line x1="10" y1="6" x2="10" y2="11"></line><line x1="10" y1="14" x2="10.01" y2="14"></line></svg>'
            : '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 10.5 8.5 15 16 6"></polyline></svg>';

        toast.innerHTML = `
            <div class="toast-icon-wrap" aria-hidden="true">${iconSvg}</div>
            <div class="toast-content">
                <div class="toast-title">${titleText}</div>
                <div class="toast-message"></div>
            </div>
            <button type="button" class="toast-close" aria-label="Dismiss notification" title="Close notification">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="2" y1="2" x2="12" y2="12"></line>
                    <line x1="12" y1="2" x2="2" y2="12"></line>
                </svg>
            </button>
            <div class="toast-progress" aria-hidden="true">
                <div class="toast-progress-bar" style="animation-duration: ${duration}ms"></div>
            </div>
        `;
        toast.querySelector('.toast-message').textContent = message;
        container.appendChild(toast);
        setupToast(toast, duration);
    };
})();


// Universal modal dialog for confirmations (replacing native window.confirm)
const confirmModal=document.createElement('dialog');
confirmModal.className='app-modal confirm-modal';
confirmModal.innerHTML=`
    <div class="app-modal-icon confirm">?</div>
    <h2>Confirm action</h2>
    <p data-modal-message></p>
    <div class="app-modal-actions">
        <button type="button" class="btn secondary" data-modal-cancel>Cancel</button>
        <button type="button" class="btn danger" data-modal-confirm>Confirm</button>
    </div>
`;
document.body.appendChild(confirmModal);
let pendingConfirmAction=null;
let allowWaiverNavigation=false;
window.bypassNavigationGuard=()=>{allowWaiverNavigation=true};

document.querySelectorAll('[data-confirm]').forEach(element=>{
    element.addEventListener('click',event=>{
        event.preventDefault();
        const message=element.dataset.confirm||'Are you sure you want to proceed?';
        confirmModal.querySelector('[data-modal-message]').textContent=message;
        pendingConfirmAction=()=>{
            allowWaiverNavigation=true;
            if(element.tagName==='BUTTON'&&element.form){
                element.form.submit();
            }else if(element.tagName==='A'&&element.href){
                location.href=element.href;
            }else if(element.closest('form')){
                element.closest('form').submit();
            }
        };
        if(typeof confirmModal.showModal==='function'){
            confirmModal.showModal();
        }else if(pendingConfirmAction){
            pendingConfirmAction();
        }
    });
});

confirmModal.querySelector('[data-modal-cancel]').addEventListener('click',()=>{
    confirmModal.close();
    pendingConfirmAction=null;
});

confirmModal.querySelector('[data-modal-confirm]').addEventListener('click',()=>{
    confirmModal.close();
    if(pendingConfirmAction){
        const action=pendingConfirmAction;
        pendingConfirmAction=null;
        action();
    }
});

document.querySelectorAll('[data-copy-signing-link]').forEach(button=>button.addEventListener('click',async()=>{const input=button.closest('.signing-link-row, .secure-link-row')?.querySelector('[data-signing-link]');if(!input)return;try{await navigator.clipboard.writeText(input.value)}catch(error){input.select();document.execCommand('copy');}const original=button.textContent;button.textContent='Copied';setTimeout(()=>button.textContent=original,1400)}));
document.querySelector('#search')?.addEventListener('input',e=>document.querySelectorAll('[data-filter] tbody tr').forEach(row=>row.hidden=!row.textContent.toLowerCase().includes(e.target.value.toLowerCase())));

// Step 1: Six-row employee pagination, search, department filter, and dynamic summary card
const pagedEmployeePicker=document.querySelector('[data-employee-results]');
if(pagedEmployeePicker){
    const employeeRows=[...pagedEmployeePicker.querySelectorAll('[data-employee-row]')];
    const employeeSearch=document.querySelector('[data-employee-search]');
    const departmentFilter=document.querySelector('[data-department-filter]');
    const rangeLabel=document.querySelector('[data-employee-count]');
    const summaryPanel=document.querySelector('[data-selected-employee-panel]');
    const pageSize=6;
    let currentPage=1, matchingRows=employeeRows.slice();

    const infoSvg='<svg class="w-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';

    const setSummary=(row)=>{
        if(!summaryPanel)return;
        const empId=row.dataset.employeeId;
        const empName=row.dataset.employeeName||'';
        const empNumber=row.dataset.employeeNumber||'';
        const empDept=row.dataset.employeeDepartment||'Quality Assurance';
        const empPos=row.dataset.employeePosition||'Quality Inspector';
        const empEmail=row.dataset.employeeEmail||(empName.toLowerCase().replace(/[^a-z]/g,'.')+'@company.com');
        const initial=empName.slice(0,1).toUpperCase()||'E';

        summaryPanel.innerHTML=`
            <h2>Selected employee</h2>
            <div class="selected-employee-card">
                <div class="summary-identity">
                    <span class="avatar employee-avatar">${initial}</span>
                    <div class="summary-name-col">
                        <h3>${empName}</h3>
                        <div class="summary-sub-row">
                            <span class="emp-num">${empNumber}</span>
                            <span class="active-chip">Active</span>
                        </div>
                    </div>
                </div>
                <dl class="summary-details-list">
                    <div><dt>Department</dt><dd>${empDept}</dd></div>
                    <div><dt>Position</dt><dd>${empPos}</dd></div>
                    <div><dt>Email</dt><dd>${empEmail}</dd></div>
                </dl>
                <div class="summary-note">
                    <span class="note-icon">${infoSvg}</span>
                    <span>The waiver will be linked to this employee's record.</span>
                </div>
            </div>
        `;

        // Enable universal footer continue button and update hint
        const footerContinue = document.querySelector('[data-employee-continue]');
        if (footerContinue) {
            footerContinue.disabled = false;
        }
        const footerHint = document.querySelector('[data-step1-hint]');
        if (footerHint) {
            footerHint.textContent = `Selected: ${empName} (${empNumber})`;
        }

        employeeRows.forEach(item=>{
            const isThis=item===row;
            item.classList.toggle('is-selected',isThis);
            const r=item.querySelector('input');
            if(r)r.checked=isThis;
        });
    };

    // Universal footer button submission for Step 1
    const footerContinueBtn = document.querySelector('[data-employee-continue]');
    footerContinueBtn?.addEventListener('click', () => {
        allowWaiverNavigation = true;
        const form = document.querySelector('[data-employee-form]');
        form?.submit();
    });

    const pagination=document.querySelector('[data-employee-pagination]');
    const previous=pagination?.querySelector('[data-employee-prev]');
    const pageNumbers=pagination?.querySelector('[data-employee-pages]');
    const following=pagination?.querySelector('[data-employee-next]');

    const renderEmployees=()=>{
        const totalPages=Math.max(1,Math.ceil(matchingRows.length/pageSize));
        currentPage=Math.min(currentPage,totalPages);
        employeeRows.forEach(row=>{
            row.hidden=true;
            row.classList.add('is-hidden');
            row.style.setProperty('display','none','important');
        });
        matchingRows.slice((currentPage-1)*pageSize,currentPage*pageSize).forEach(row=>{
            row.hidden=false;
            row.classList.remove('is-hidden');
            row.style.setProperty('display','flex','important');
        });
        const first=matchingRows.length?(currentPage-1)*pageSize+1:0;
        const last=Math.min(currentPage*pageSize,matchingRows.length);
        if(rangeLabel)rangeLabel.textContent=`Showing ${first}–${last} of ${matchingRows.length} employees`;
        if(pageNumbers){
            pageNumbers.replaceChildren();
            for(let n=1;n<=totalPages;n++){
                const button=document.createElement('button');
                button.type='button';
                button.className='page-button'+(n===currentPage?' active':'');
                button.textContent=String(n);
                button.addEventListener('click',()=>{currentPage=n;renderEmployees()});
                pageNumbers.append(button);
            }
        }
        if(previous)previous.disabled=currentPage===1;
        if(following)following.disabled=currentPage===totalPages;
    };

    employeeRows.forEach(row=>{
        row.addEventListener('click',()=>setSummary(row));
        row.querySelector('input')?.addEventListener('change',()=>setSummary(row));
    });

    const applyEmployeeFilter=()=>{
        const query=(employeeSearch?.value||'').trim().toLowerCase();
        const department=departmentFilter?.value||'';
        matchingRows=employeeRows.filter(row=>(!query||row.dataset.employeeText.includes(query))&&(!department||row.dataset.department===department));
        currentPage=1;
        renderEmployees();
    };

    employeeSearch?.addEventListener('input',applyEmployeeFilter);
    departmentFilter?.addEventListener('change',applyEmployeeFilter);
    previous?.addEventListener('click',()=>{if(currentPage>1){currentPage--;renderEmployees()}});
    following?.addEventListener('click',()=>{if(currentPage<Math.ceil(matchingRows.length/pageSize)){currentPage++;renderEmployees()}});
    renderEmployees();
}

// Step 2: Waiver type card selection and button state
const typeForm=document.querySelector('[data-type-form]');
if(typeForm){
    const continueBtn=document.querySelector('[data-type-continue]');
    const typeHint=document.querySelector('[data-type-hint]');
    const typeCards=typeForm.querySelectorAll('[data-type-card]');

    typeCards.forEach(card=>{
        card.addEventListener('click',(event)=>{
            if(event.target && event.target.tagName === 'INPUT') return;
            const radio=card.querySelector('input[type="radio"]');
            if(radio)radio.checked=true;
            typeCards.forEach(c=>c.classList.toggle('is-selected',c===card));
            if(continueBtn)continueBtn.disabled=false;
            if(typeHint){
                const label=card.querySelector('b')?.textContent||'Waiver type';
                typeHint.textContent=`Selected: ${label}`;
            }
        });
    });

    continueBtn?.addEventListener('click', () => {
        allowWaiverNavigation = true;
        typeForm.submit();
    });
}

// Step 3: Character counter for medical recommendation
const recField=document.getElementById('recommendation-text');
const recCounter=document.getElementById('recommendation-counter');
if(recField&&recCounter){
    const updateRecCount=()=>{
        const len=recField.value.length;
        recCounter.textContent=`${len.toLocaleString()} / 1,000`;
        if(len>=950){
            recCounter.style.color='#d93829';
            recCounter.style.fontWeight='700';
        }else{
            recCounter.style.color='';
            recCounter.style.fontWeight='';
        }
    };
    recField.addEventListener('input',updateRecCount);
    updateRecCount();
}

// Step 4: Review confirmation checkbox enables continue button
const reviewCheckbox=document.querySelector('[data-review-checkbox]');
const reviewSubmitBtn=document.querySelector('[data-review-submit]');
const reviewHint=document.querySelector('[data-review-hint]');
if(reviewCheckbox&&reviewSubmitBtn){
    reviewCheckbox.addEventListener('change',()=>{
        reviewSubmitBtn.disabled=!reviewCheckbox.checked;
        if(reviewHint){
            reviewHint.textContent=reviewCheckbox.checked
                ? 'Ready to continue to employee signature'
                : 'Please confirm the review checkbox to proceed';
        }
    });
}

// Universal A4 Document Preview Modal
function closeA4PreviewModal() {
    const dialog = document.getElementById('a4-preview-dialog');
    if (!dialog) return;
    const iframe = dialog.querySelector('.a4-preview-iframe');
    if (iframe) iframe.src = 'about:blank';
    try {
        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
        }
    } catch (_) {}
    dialog.removeAttribute('open');
    dialog.style.setProperty('display', 'none', 'important');
}

function openA4PreviewModal(url) {
    let dialog = document.getElementById('a4-preview-dialog');
    if (!dialog) {
        dialog = document.createElement('dialog');
        dialog.id = 'a4-preview-dialog';
        dialog.className = 'app-modal a4-preview-dialog';
        dialog.innerHTML = `
            <div class="a4-modal-header">
                <div class="a4-modal-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <b>A4 Document Preview</b>
                </div>
                <div class="a4-modal-actions">
                    <button type="button" class="btn btn-sm btn-print-a4" data-print-a4>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        Print
                    </button>
                    <a class="btn secondary btn-sm" data-a4-external target="_blank" href="#">New window</a>
                    <button type="button" class="btn-close-a4" data-close-a4 aria-label="Close preview">&times;</button>
                </div>
            </div>
            <div class="a4-modal-body">
                <iframe class="a4-preview-iframe" src="about:blank" title="A4 Document Preview"></iframe>
            </div>
        `;
        document.body.appendChild(dialog);

        // Close button trigger
        dialog.querySelector('[data-close-a4]').addEventListener('click', (e) => {
            e.preventDefault();
            closeA4PreviewModal();
        });

        // Print button trigger
        dialog.querySelector('[data-print-a4]').addEventListener('click', () => {
            const iframe = dialog.querySelector('.a4-preview-iframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            }
        });

        // Backdrop click dismisses modal
        dialog.addEventListener('click', (e) => {
            const rect = dialog.getBoundingClientRect();
            const isInDialog = (
                rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX && e.clientX <= rect.left + rect.width
            );
            if (!isInDialog || e.target === dialog) {
                closeA4PreviewModal();
            }
        });

        dialog.addEventListener('cancel', (e) => {
            e.preventDefault();
            closeA4PreviewModal();
        });

        dialog.addEventListener('close', () => {
            closeA4PreviewModal();
        });
    }

    const modalUrl = url.includes('modal=1') ? url : (url + (url.includes('?') ? '&' : '?') + 'modal=1');
    const externalUrl = url.replace(/([?&])modal=1(&|$)/, '$1').replace(/[?&]$/, '');
    dialog.querySelector('.a4-preview-iframe').src = modalUrl;
    dialog.querySelector('[data-a4-external]').href = externalUrl;

    dialog.style.removeProperty('display');
    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
        dialog.style.display = 'flex';
    }
}

// Global escape key handler to dismiss modal
window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const dialog = document.getElementById('a4-preview-dialog');
        if (dialog && (dialog.open || dialog.style.display !== 'none')) {
            closeA4PreviewModal();
        }
    }
});

document.querySelectorAll('[data-a4-preview], .a4-preview-btn, .a4-preview-btn-top').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        const url = btn.dataset.a4Preview || btn.getAttribute('href');
        if (url && url !== '#') {
            openA4PreviewModal(url);
        }
    });
});

// Precision digital signature canvas
document.querySelectorAll('[data-signature]').forEach(canvas => {
    const ctx = canvas.getContext('2d');
    const input = document.getElementById(canvas.dataset.signature);
    const clear = canvas.parentElement.querySelector('[data-clear]');
    let drawing = false, last = null;

    const calibrateCanvas = () => {
        const rect = canvas.getBoundingClientRect();
        if (rect.width && rect.height) {
            const ratio = window.devicePixelRatio || 1;
            canvas.width = Math.round(rect.width * ratio);
            canvas.height = Math.round(rect.height * ratio);
            ctx.scale(ratio, ratio);
            ctx.strokeStyle = '#18442e';
            ctx.lineWidth = 2.4;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }
    };

    setTimeout(calibrateCanvas, 60);
    window.addEventListener('resize', calibrateCanvas);

    const point = e => {
        const r = canvas.getBoundingClientRect();
        const t = e.touches ? e.touches[0] : e;
        return [t.clientX - r.left, t.clientY - r.top];
    };

    const start = e => {
        drawing = true;
        last = point(e);
        e.preventDefault();
    };

    const move = e => {
        if (!drawing) return;
        const p = point(e);
        ctx.beginPath();
        ctx.moveTo(...last);
        ctx.lineTo(...p);
        ctx.stroke();
        last = p;
        if (input) input.value = canvas.toDataURL('image/png');
        e.preventDefault();
    };

    const end = () => {
        drawing = false;
    };

    canvas.addEventListener('pointerdown', start);
    canvas.addEventListener('pointermove', move);
    window.addEventListener('pointerup', end);
    window.addEventListener('pointercancel', end);

    clear?.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (input) input.value = '';
    });

    const form = canvas.closest('form');
    if (form) {
        form.addEventListener('submit', e => {
            if (input && !input.value) {
                e.preventDefault();
                alert('Please sign inside the signature box before submitting.');
            }
        });
    }
});

// Guarantee all form submissions (programmatic or native) disable unsaved changes prompts
const originalFormSubmit = HTMLFormElement.prototype.submit;
HTMLFormElement.prototype.submit = function() {
    allowWaiverNavigation = true;
    return originalFormSubmit.apply(this, arguments);
};
if (HTMLFormElement.prototype.requestSubmit) {
    const originalRequestSubmit = HTMLFormElement.prototype.requestSubmit;
    HTMLFormElement.prototype.requestSubmit = function() {
        allowWaiverNavigation = true;
        return originalRequestSubmit.apply(this, arguments);
    };
}

// Intercept legitimate form submissions and forward progression in capture phase
document.addEventListener('submit', () => {
    allowWaiverNavigation = true;
}, true);

document.addEventListener('click', (event) => {
    // Any submit button, forward action, or completion button grants navigation permission
    const forwardTrigger = event.target.closest('button[type="submit"], input[type="submit"], .btn-nav-primary, .footer-nav-right a, .footer-nav-right button, [data-employee-continue], [data-type-continue], [data-review-submit], [data-forward]');
    if (forwardTrigger) {
        allowWaiverNavigation = true;
    }
}, true);

// If form validation fails, re-arm the route guard
document.addEventListener('invalid', () => {
    allowWaiverNavigation = false;
}, true);

// Poll the authenticated status endpoint while a one-time QR is active.
document.querySelectorAll('[data-signing-waiting]').forEach(panel=>{
 const waiverId=panel.dataset.waiverId;
 const totalSeconds=Number(panel.dataset.totalSeconds||600);
 const expiresAt=Date.now()+Number(panel.dataset.secondsRemaining||0)*1000;
 const countdown=panel.querySelector('[data-signing-countdown]');
 const progressBar=panel.querySelector('#signing-progress-bar');
 let stopped=false,lastSeconds=null;

 // Incoming signature ingestion: Suppress beforeunload and reload smoothly
 const showSigned=()=>{
  stopped=true;
  allowWaiverNavigation=true;
  location.reload();
 };

 const tick=()=>{
  if(stopped)return;
  const seconds=Math.max(0,Math.floor((expiresAt-Date.now())/1000));
  if(seconds!==lastSeconds&&countdown){
   countdown.textContent=`${String(Math.floor(seconds/60)).padStart(2,'0')}:${String(seconds%60).padStart(2,'0')}`;
   if(progressBar&&totalSeconds>0){
    const pct=Math.max(0,Math.min(100,(seconds/totalSeconds)*100));
    progressBar.style.width=`${pct}%`;
   }
   lastSeconds=seconds;
  }
  if(seconds<=0){
   stopped=true;
   allowWaiverNavigation=true;
   location.reload();
  }
 };

 const poll=async()=>{
  if(stopped)return;
  try{
   const response=await fetch(`/Waiver-Liability/api/waivers/check-signature-status.php?waiver_id=${encodeURIComponent(waiverId)}`,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
   if(response.status===401){
    stopped=true;
    allowWaiverNavigation=true;
    location.href='index.php?page=login';
    return;
   }
   if(!response.ok)return;
   const data=await response.json();
   if(data.signed){
    showSigned(data);
    return;
   }
   if(data.request_status&&data.request_status!=='ACTIVE'){
    stopped=true;
    allowWaiverNavigation=true;
    location.reload();
   }
  }catch(error){/* Keep waiting through short network interruptions. */}
 };

 tick();
 poll();
 const clock=setInterval(tick,1000),watch=setInterval(poll,2500);
 window.addEventListener('pagehide',()=>{clearInterval(clock);clearInterval(watch)},{once:true});
});

// Protect active waiver sessions. Restrict confirmation strictly to intentional exit actions:
// navigating backwards, clicking external links, or explicitly discarding draft.
const waiverSession=document.querySelector('[data-waiver-session]');
if(waiverSession){
 let pendingUrl='';
 const dialog=document.createElement('dialog');
 dialog.className='route-guard';
 dialog.innerHTML='<div class="route-guard-icon">!</div><h2>Leave this waiver?</h2><p>All progress will be lost if you leave this active waiver session.</p><div class="route-guard-actions"><button type="button" class="btn secondary" data-stay>Stay here</button><button type="button" class="btn danger" data-leave>Leave page</button></div>';
 document.body.appendChild(dialog);

 // Native browser prompt: ONLY warn if navigation is NOT explicitly authorized
 window.addEventListener('beforeunload',event=>{
  if(allowWaiverNavigation)return;
  event.preventDefault();
  event.returnValue='';
 });

 document.addEventListener('click',event=>{
  if(allowWaiverNavigation||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;

  // Never intercept forward progression, submit buttons, modals, or right footer controls
  if(event.target.closest('button[type="submit"], input[type="submit"], .btn-nav-primary, .footer-nav-right, [data-forward], [data-stay], [data-leave], .app-modal, .route-guard')) {
   return;
  }

  // Strict exit triggers: Back navigation, change employee, sidebar links, topbar links
  const leaveTrigger=event.target.closest('.sidebar a, .topbar a, a[data-leave-workflow], [data-confirm-back], .footer-nav-left a, a.change-emp-btn');
  if(!leaveTrigger||!leaveTrigger.href)return;
  const href=leaveTrigger.getAttribute('href')||'';
  if(href.startsWith('#')||href.startsWith('javascript:'))return;

  event.preventDefault();
  pendingUrl=leaveTrigger.href;
  if(typeof dialog.showModal==='function'){
      dialog.showModal();
  }else{
      allowWaiverNavigation=true;
      location.href=pendingUrl;
  }
 });

 dialog.querySelector('[data-stay]').addEventListener('click',()=>{pendingUrl='';dialog.close()});
 dialog.querySelector('[data-leave]').addEventListener('click',()=>{allowWaiverNavigation=true;dialog.close();location.href=pendingUrl});
 dialog.addEventListener('cancel',event=>{event.preventDefault();pendingUrl='';dialog.close()});
 dialog.addEventListener('click',event=>{if(event.target===dialog){pendingUrl='';dialog.close()}});
}

// Move server-side report filters into the table card so search and category
// controls form one compact toolbar directly above their results.
document.querySelectorAll('.filter-grid').forEach(form=>{
 const source=form.closest('.card'),target=source?.nextElementSibling;
 if(!target?.querySelector('table'))return;
 form.classList.add('server-table-toolbar');
 const heading=target.querySelector('.table-heading');
 heading?.insertAdjacentElement('afterend',form);
 source.remove();
});

// Shared client pagination for every table rendered in the application.
document.querySelectorAll('.content table').forEach((table,index)=>{
 const body=table.tBodies[0];
 if(!body)return;
 const rows=[...body.rows],host=table.closest('.card')||table.parentElement;
 const fixedPageSize=host.classList.contains('recent-waivers')?5:null;
 const scroller=document.createElement('div');scroller.className='table-scroll';
 table.parentNode.insertBefore(scroller,table);scroller.appendChild(table);
 const ths=[...table.querySelectorAll('thead th')];
 let statusCol=ths.findIndex(th=>th.textContent.trim().toLowerCase()==='status');
 let typeCol=ths.findIndex(th=>th.textContent.trim().toLowerCase().includes('type'));
 if(statusCol===-1)statusCol=4;
 if(typeCol===-1)typeCol=3;
 let toolbar=host.querySelector('.server-table-toolbar');
 const serverFiltered=Boolean(toolbar);
 if(!toolbar){
  toolbar=document.createElement('div');toolbar.className='table-toolbar';
  toolbar.innerHTML=`<label class="table-search"><span>Search</span><input type="search" placeholder="Search waivers by employee, department, or type..." data-table-search></label><label><span>Status</span><select data-table-filter data-column="${statusCol}"><option value="">All statuses</option></select></label><label><span>Waiver type</span><select data-table-filter data-column="${typeCol}"><option value="">All types</option></select></label>`;
  scroller.insertAdjacentElement('beforebegin',toolbar);
 }
 if(fixedPageSize===null){const sizeLabel=document.createElement('label');sizeLabel.className='page-size-field';sizeLabel.innerHTML='<span>Rows</span><select data-page-size><option>5</option><option selected>10</option><option>20</option><option>50</option></select>';toolbar.appendChild(sizeLabel)}
 const footer=document.createElement('div');footer.className='table-pagination';footer.innerHTML='<span data-page-summary></span><div><button type="button" class="page-button" data-page-prev aria-label="Previous page">‹</button><span data-page-numbers></span><button type="button" class="page-button" data-page-next aria-label="Next page">›</button></div>';
 scroller.insertAdjacentElement('afterend',footer);
 const search=toolbar.querySelector('[data-table-search]'),filters=[...toolbar.querySelectorAll('[data-table-filter]')],size=toolbar.querySelector('[data-page-size]'),summary=footer.querySelector('[data-page-summary]'),numbers=footer.querySelector('[data-page-numbers]'),prev=footer.querySelector('[data-page-prev]'),next=footer.querySelector('[data-page-next]');
 if(!serverFiltered)filters.forEach(filter=>{const column=Number(filter.dataset.column),values=[...new Set(rows.map(row=>row.cells[column]?.textContent.trim()).filter(Boolean))].sort();values.forEach(value=>filter.add(new Option(value,value)));if(values.length<2)filter.closest('label').hidden=true});
 let page=1;
 const pageSize=()=>fixedPageSize??Number(size?.value||10);
 const matching=()=>rows.filter(row=>(!search||row.textContent.toLowerCase().includes(search.value.trim().toLowerCase()))&&filters.every(filter=>!filter.value||row.cells[Number(filter.dataset.column)]?.textContent.trim()===filter.value));
 const render=()=>{
  const filtered=matching(),perPage=pageSize(),pages=Math.max(1,Math.ceil(filtered.length/perPage));page=Math.min(page,pages);
  rows.forEach(row=>row.hidden=true);filtered.slice((page-1)*perPage,page*perPage).forEach(row=>row.hidden=false);
  const start=filtered.length?(page-1)*perPage+1:0,end=Math.min(page*perPage,filtered.length);summary.textContent=`${start}–${end} of ${filtered.length}`;
  numbers.innerHTML='';const first=Math.max(1,page-2),last=Math.min(pages,first+4);for(let n=first;n<=last;n++){const button=document.createElement('button');button.type='button';button.className='page-button'+(n===page?' active':'');button.textContent=n;button.setAttribute('aria-label',`Page ${n}`);button.addEventListener('click',()=>{page=n;render()});numbers.appendChild(button)}
  prev.disabled=page===1;next.disabled=page===pages;
 };
 search?.addEventListener('input',()=>{page=1;render()});filters.forEach(filter=>filter.addEventListener('change',()=>{page=1;render()}));host.querySelectorAll('[data-quick-status]').forEach(button=>button.addEventListener('click',()=>{const status=filters.find(filter=>Number(filter.dataset.column)===statusCol);if(status){status.value=button.dataset.quickStatus;page=1;render()}host.querySelectorAll('[data-quick-status]').forEach(item=>item.classList.toggle('active',item===button))}));size?.addEventListener('change',()=>{page=1;render()});prev.addEventListener('click',()=>{if(page>1){page--;render()}});next.addEventListener('click',()=>{const pages=Math.max(1,Math.ceil(matching().length/pageSize()));if(page<pages){page++;render()}});render();
});

// Login controls: reveal the password on demand and remember only the username.
document.querySelectorAll('[data-password-toggle]').forEach(button=>button.addEventListener('click',()=>{const input=button.closest('.auth-input')?.querySelector('input');if(!input)return;const showing=input.type==='text';input.type=showing?'password':'text';button.setAttribute('aria-pressed',String(!showing));button.setAttribute('aria-label',showing?'Show password':'Hide password')}));
document.querySelectorAll('[data-login-form]').forEach(form=>{const username=form.querySelector('input[name="username"]'),remember=form.querySelector('[data-remember-username]');if(!username||!remember)return;const saved=localStorage.getItem('waiverDeskUsername');if(saved&&!username.value){username.value=saved;remember.checked=true}form.addEventListener('submit',()=>{if(remember.checked)localStorage.setItem('waiverDeskUsername',username.value);else localStorage.removeItem('waiverDeskUsername')})});

// Admin Delete Waiver Confirmation Modal
document.addEventListener('click', event => {
 const trigger = event.target.closest('.btn-delete-waiver');
 if (!trigger) return;
 event.preventDefault();

 const modal = document.getElementById('delete-waiver-modal');
 const idInput = document.getElementById('delete-waiver-id');
 const msgEl = document.getElementById('delete-waiver-message');
 if (!modal || !idInput) return;

 const waiverId = trigger.getAttribute('data-waiver-id') || '';
 const waiverNumber = trigger.getAttribute('data-waiver-number') || 'Draft';
 const employeeName = trigger.getAttribute('data-employee-name') || 'this employee';

 idInput.value = waiverId;
 if (msgEl) {
  const numSpan = document.createElement('strong');
  numSpan.textContent = waiverNumber;
  const nameSpan = document.createElement('strong');
  nameSpan.textContent = employeeName;

  msgEl.textContent = 'Are you sure you want to permanently delete ';
  msgEl.appendChild(numSpan);
  msgEl.appendChild(document.createTextNode(' for '));
  msgEl.appendChild(nameSpan);
  msgEl.appendChild(document.createElement('br'));
  msgEl.appendChild(document.createElement('br'));
  msgEl.appendChild(document.createTextNode('All associated signatures, acknowledgments, and mobile signing tokens will be removed. This action cannot be undone.'));
 }

 if (typeof modal.showModal === 'function') {
  modal.showModal();
 }
});

const deleteModal = document.getElementById('delete-waiver-modal');
if (deleteModal) {
 deleteModal.addEventListener('click', event => {
  if (event.target === deleteModal) deleteModal.close();
 });
}

const deleteForm = document.getElementById('delete-waiver-form');
if (deleteForm) {
 deleteForm.addEventListener('submit', () => {
  if (typeof allowWaiverNavigation !== 'undefined') {
   allowWaiverNavigation = true;
  }
 });
}

// Bulk Selection & Delete All / Delete Selected for Admin
(function initBulkDeleteWaivers() {
 document.addEventListener('change', event => {
  if (event.target.matches('[data-select-all-waivers]')) {
   const table = event.target.closest('table');
   if (!table) return;
   const shouldCheck = event.target.checked;
   table.querySelectorAll('[data-waiver-checkbox]').forEach(cb => {
    cb.checked = shouldCheck;
   });
   updateBulkState(table);
  } else if (event.target.matches('[data-waiver-checkbox]')) {
   const table = event.target.closest('table');
   if (!table) return;
   updateBulkState(table);
  }
 });

 function updateBulkState(table) {
  const host = table.closest('.card') || table.parentElement;
  const toolbar = host.querySelector('[data-bulk-toolbar]');
  const selectAll = table.querySelector('[data-select-all-waivers]');
  const checkboxes = [...table.querySelectorAll('[data-waiver-checkbox]')];
  const checked = checkboxes.filter(cb => cb.checked);
  const count = checked.length;
  const total = checkboxes.length;

  checkboxes.forEach(cb => {
   const row = cb.closest('tr');
   if (row) row.classList.toggle('row-selected', cb.checked);
  });

  if (selectAll) {
   if (count === 0) {
    selectAll.checked = false;
    selectAll.indeterminate = false;
   } else if (count === total) {
    selectAll.checked = true;
    selectAll.indeterminate = false;
   } else {
    selectAll.checked = false;
    selectAll.indeterminate = true;
   }
  }

  if (toolbar) {
   if (count > 0) {
    toolbar.style.display = 'flex';
    const countEl = toolbar.querySelector('[data-selected-count]');
    const subtextEl = toolbar.querySelector('[data-bulk-subtext]');
    const deleteTextEl = toolbar.querySelector('[data-bulk-delete-text]');
    if (countEl) countEl.textContent = count;
    const isAll = count === total;
    if (subtextEl) {
     subtextEl.textContent = isAll ? `All ${total} waivers selected` : `${count} of ${total} waivers selected`;
    }
    if (deleteTextEl) {
     deleteTextEl.textContent = isAll ? `Delete all (${total})` : `Delete selected (${count})`;
    }
   } else {
    toolbar.style.display = 'none';
   }
  }
 }

 document.addEventListener('click', event => {
  const deselectBtn = event.target.closest('[data-bulk-deselect]');
  if (deselectBtn) {
   const host = deselectBtn.closest('.card') || document;
   const table = host.querySelector('[data-waivers-table]');
   if (!table) return;
   table.querySelectorAll('[data-waiver-checkbox]').forEach(cb => { cb.checked = false; });
   updateBulkState(table);
   return;
  }

  const deleteBtn = event.target.closest('[data-bulk-delete]');
  if (deleteBtn) {
   const host = deleteBtn.closest('.card') || document;
   const table = host.querySelector('[data-waivers-table]');
   if (!table) return;
   const checkboxes = [...table.querySelectorAll('[data-waiver-checkbox]')];
   const checked = checkboxes.filter(cb => cb.checked);
   if (checked.length === 0) return;

   const modal = document.getElementById('bulk-delete-waiver-modal');
   const titleEl = document.getElementById('bulk-delete-modal-title');
   const msgEl = document.getElementById('bulk-delete-modal-message');
   const previewEl = document.getElementById('bulk-delete-preview');
   const inputsEl = document.getElementById('bulk-delete-hidden-inputs');
   const confirmBtn = document.getElementById('bulk-delete-confirm-btn');
   if (!modal || !inputsEl) return;

   const isAll = checked.length === checkboxes.length;
   const count = checked.length;

   if (titleEl) {
    titleEl.textContent = isAll ? `Delete all ${count} waivers?` : `Delete ${count} selected waivers?`;
   }

   if (msgEl) {
    const strongCount = document.createElement('strong');
    strongCount.textContent = isAll ? `all ${count} waivers` : `${count} selected waivers`;
    msgEl.textContent = 'Are you sure you want to permanently delete ';
    msgEl.appendChild(strongCount);
    msgEl.appendChild(document.createTextNode('? All associated signatures, acknowledgments, and mobile signing tokens will be removed. This action cannot be undone.'));
   }

   if (previewEl) {
    previewEl.innerHTML = '';
    const previewHeader = document.createElement('div');
    previewHeader.style.marginBottom = '6px';
    previewHeader.style.fontWeight = '600';
    previewHeader.textContent = `Waivers to be deleted (${count}):`;
    previewEl.appendChild(previewHeader);

    const listWrap = document.createElement('div');
    listWrap.style.display = 'flex';
    listWrap.style.flexWrap = 'wrap';
    listWrap.style.gap = '4px';

    checked.slice(0, 30).forEach(cb => {
     const tag = document.createElement('span');
     tag.className = 'bulk-preview-item';
     const num = cb.getAttribute('data-waiver-number') || `Draft #${cb.value}`;
     const emp = cb.getAttribute('data-employee-name') || '';
     tag.textContent = emp ? `${num} (${emp})` : num;
     listWrap.appendChild(tag);
    });

    if (count > 30) {
     const more = document.createElement('span');
     more.className = 'bulk-preview-item';
     more.style.background = '#f0f4f1';
     more.textContent = `+${count - 30} more`;
     listWrap.appendChild(more);
    }
    previewEl.appendChild(listWrap);
   }

   inputsEl.innerHTML = '';
   checked.forEach(cb => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'waiver_ids[]';
    input.value = cb.value;
    inputsEl.appendChild(input);
   });

   if (confirmBtn) {
    confirmBtn.textContent = isAll ? `Yes, delete all (${count})` : `Yes, delete ${count} waivers`;
   }

   if (typeof modal.showModal === 'function') {
    modal.showModal();
   }
  }
 });

 const bulkModal = document.getElementById('bulk-delete-waiver-modal');
 if (bulkModal) {
  bulkModal.addEventListener('click', event => {
   if (event.target === bulkModal) bulkModal.close();
  });
 }

 const bulkForm = document.getElementById('bulk-delete-waiver-form');
 if (bulkForm) {
  bulkForm.addEventListener('submit', () => {
   if (typeof allowWaiverNavigation !== 'undefined') {
    allowWaiverNavigation = true;
   }
  });
 }
})();


