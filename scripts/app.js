document.querySelector('.menu')?.addEventListener('click',()=>document.querySelector('.sidebar').classList.toggle('open'));
document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm))e.preventDefault()}));
document.querySelectorAll('[data-copy-signing-link]').forEach(button=>button.addEventListener('click',async()=>{const input=button.closest('.signing-link-row')?.querySelector('[data-signing-link]');if(!input)return;try{await navigator.clipboard.writeText(input.value)}catch(error){input.select();document.execCommand('copy');}const original=button.textContent;button.textContent='Copied';setTimeout(()=>button.textContent=original,1400)}));
document.querySelector('#search')?.addEventListener('input',e=>document.querySelectorAll('[data-filter] tbody tr').forEach(row=>row.hidden=!row.textContent.toLowerCase().includes(e.target.value.toLowerCase())));

// Replace the basic employee filter with a six-row page model and exact range text.
const pagedEmployeePicker=document.querySelector('[data-employee-results]');
if(pagedEmployeePicker){
 const employeeRows=[...pagedEmployeePicker.querySelectorAll('[data-employee-row]')],employeeSearch=document.querySelector('[data-employee-search]'),departmentFilter=document.querySelector('[data-department-filter]'),rangeLabel=document.querySelector('[data-employee-count]'),continueButton=document.querySelector('[data-employee-continue]'),summaryPanel=document.querySelector('[data-selected-employee-panel]'),pageSize=6;let currentPage=1,matchingRows=employeeRows.slice();
 const setSummary=(row)=>{if(!summaryPanel)return;const get=selector=>summaryPanel.querySelector(selector);summaryPanel.innerHTML='<h2>Selected employee</h2><div class="summary-identity"><span class="avatar"></span><div><h3></h3><p></p><span class="active-chip">✓ Active</span></div></div><dl><div><dt>Department</dt><dd></dd></div><div><dt>Position</dt><dd></dd></div><div><dt>Email</dt><dd></dd></div></dl><div class="summary-note">ⓘ <span>The waiver will be linked to this employee’s record.</span></div>';get('.avatar').textContent=(row.dataset.employeeName||'E').slice(0,1).toUpperCase();get('h3').textContent=row.dataset.employeeName||'';get('.summary-identity p').textContent=row.dataset.employeeNumber||'';get('dl div:nth-child(1) dd').textContent=row.dataset.employeeDepartment||'Not provided';get('dl div:nth-child(2) dd').textContent=row.dataset.employeePosition||'Not provided';get('dl div:nth-child(3) dd').textContent=row.dataset.employeeEmail||'Not provided';employeeRows.forEach(item=>item.classList.toggle('is-selected',item===row));continueButton?.removeAttribute('disabled')};
 const pagination=document.querySelector('[data-employee-pagination]');const previous=pagination?.querySelector('[data-employee-prev]');const pageNumbers=pagination?.querySelector('[data-employee-pages]');const following=pagination?.querySelector('[data-employee-next]');
 const renderEmployees=()=>{const totalPages=Math.max(1,Math.ceil(matchingRows.length/pageSize));currentPage=Math.min(currentPage,totalPages);employeeRows.forEach(row=>row.hidden=true);matchingRows.slice((currentPage-1)*pageSize,currentPage*pageSize).forEach(row=>row.hidden=false);const first=matchingRows.length?(currentPage-1)*pageSize+1:0,last=Math.min(currentPage*pageSize,matchingRows.length);if(rangeLabel)rangeLabel.textContent=`Showing ${first}–${last} of ${matchingRows.length} employees`;pageNumbers.replaceChildren();for(let n=1;n<=totalPages;n++){const button=document.createElement('button');button.type='button';button.className='page-button'+(n===currentPage?' active':'');button.textContent=String(n);button.addEventListener('click',()=>{currentPage=n;renderEmployees()});pageNumbers.append(button)}previous.disabled=currentPage===1;following.disabled=currentPage===totalPages};
 employeeRows.forEach(row=>{row.addEventListener('click',()=>setSummary(row));row.querySelector('input')?.addEventListener('change',()=>setSummary(row))});const applyEmployeeFilter=()=>{const query=(employeeSearch?.value||'').trim().toLowerCase(),department=departmentFilter?.value||'';matchingRows=employeeRows.filter(row=>(!query||row.dataset.employeeText.includes(query))&&(!department||row.dataset.department===department));currentPage=1;renderEmployees()};employeeSearch?.addEventListener('input',applyEmployeeFilter);departmentFilter?.addEventListener('change',applyEmployeeFilter);previous?.addEventListener('click',()=>{if(currentPage>1){currentPage--;renderEmployees()}});following?.addEventListener('click',()=>{if(currentPage<Math.ceil(matchingRows.length/pageSize)){currentPage++;renderEmployees()}});renderEmployees();
}

// Step 2 and review confirmations remain unavailable until the required
// selection is made, even when browser validation is bypassed.
const typeForm=document.querySelector('[data-type-form]');if(typeForm){const button=typeForm.querySelector('button[type="submit"]');const radios=[...typeForm.querySelectorAll('input[name="waiver_type"]')];if(button){button.disabled=true;radios.forEach(radio=>radio.addEventListener('change',()=>{button.disabled=!radios.some(item=>item.checked)}))}}
const reviewForm=document.querySelector('form input[name="action"][value="review"]')?.form;if(reviewForm){const button=reviewForm.querySelector('button[type="submit"],button:not([type])');const label=reviewForm.querySelector('.review-confirm')||document.createElement('label');if(!label.parentElement){label.className='review-confirm';label.innerHTML='<input type="checkbox" name="review_confirm" value="1" data-review-confirm> <span>I have reviewed the information and confirm it is correct.</span>';button?.parentElement?.insertBefore(label,button)}if(button){const confirmInput=label.querySelector('input[name="review_confirm"]');button.disabled=!confirmInput?.checked;confirmInput?.addEventListener('change',event=>{button.disabled=!event.target.checked})}}

// Preserve the current draft when returning from review to the details form.
const wizardStep=document.querySelector('[data-new-waiver-step]')?.dataset.newWaiverStep;if(wizardStep==='4'){const id=new URLSearchParams(location.search).get('id');const back=document.querySelector('.review-footer a[href*="step=3"]');if(back&&id)back.href=`index.php?page=new-waiver&step=3&id=${encodeURIComponent(id)}`}
if(wizardStep==='3'){const id=new URLSearchParams(location.search).get('id'),form=document.querySelector('.wizard-form');if(form&&id){const hidden=document.createElement('input');hidden.type='hidden';hidden.name='waiver_id';hidden.value=id;form.append(hidden)}}

document.querySelectorAll('[data-signature]').forEach(canvas=>{const ctx=canvas.getContext('2d'), input=document.getElementById(canvas.dataset.signature), clear=canvas.parentElement.querySelector('[data-clear]'); let drawing=false,last=null;
 const point=e=>{const r=canvas.getBoundingClientRect(),t=e.touches?.[0]||e;return [(t.clientX-r.left)*canvas.width/r.width,(t.clientY-r.top)*canvas.height/r.height]};
 const start=e=>{drawing=true;last=point(e);e.preventDefault()}, move=e=>{if(!drawing)return;const p=point(e);ctx.strokeStyle='#244c38';ctx.lineWidth=2.4;ctx.lineCap='round';ctx.beginPath();ctx.moveTo(...last);ctx.lineTo(...p);ctx.stroke();last=p;input.value=canvas.toDataURL('image/png');e.preventDefault()},end=()=>drawing=false;
 canvas.addEventListener('pointerdown',start);canvas.addEventListener('pointermove',move);window.addEventListener('pointerup',end);clear?.addEventListener('click',()=>{ctx.clearRect(0,0,canvas.width,canvas.height);input.value=''});
});

// Poll the authenticated status endpoint while a one-time QR is active.
document.querySelectorAll('[data-signing-waiting]').forEach(panel=>{
 const waiverId=panel.dataset.waiverId,expiresAt=Date.now()+Number(panel.dataset.secondsRemaining||0)*1000,countdown=panel.querySelector('[data-signing-countdown]');
 let stopped=false,lastSeconds=null;
 const showSigned=data=>{
  stopped=true;
  const canSupervise=panel.dataset.canSupervise==='1';
  panel.classList.add('signed-state');panel.dataset.signingState='signed';panel.removeAttribute('data-signing-waiting');
  panel.innerHTML='<div class="signed-icon">&#10003;</div><div class="signed-copy"><p class="signing-kicker">Employee signature</p><h2>Employee signed</h2><p data-signed-name></p><img data-signed-image alt="Employee signature"><dl><div><dt>Signed</dt><dd data-signed-at></dd></div><div><dt>Verification</dt><dd>Company credentials</dd></div></dl><div class="next-step"><b>Next step</b><span>Supervisor acknowledgment required</span><a class="btn" data-next-action></a></div></div>';
  panel.querySelector('[data-signed-name]').textContent=data.printed_name||'Employee';
  panel.querySelector('[data-signed-at]').textContent=(data.signed_at||'Recorded').replace(/\.\d+$/,'');
  const image=panel.querySelector('[data-signed-image]');if(typeof data.signature_data==='string'&&data.signature_data.startsWith('data:image/png;base64,'))image.src=data.signature_data;
  const next=panel.querySelector('[data-next-action]');next.href=canSupervise?`index.php?page=approval-sign&id=${encodeURIComponent(waiverId)}`:`index.php?page=waiver-view&id=${encodeURIComponent(waiverId)}`;next.textContent=canSupervise?'Review for Supervisor':'View signed waiver';if(!canSupervise)next.classList.add('secondary');
 };
 const tick=()=>{if(stopped)return;const seconds=Math.max(0,Math.floor((expiresAt-Date.now())/1000));if(seconds!==lastSeconds&&countdown){countdown.textContent=`${String(Math.floor(seconds/60)).padStart(2,'0')}:${String(seconds%60).padStart(2,'0')}`;lastSeconds=seconds}if(seconds<=0){stopped=true;location.reload()}};
 const poll=async()=>{if(stopped)return;try{const response=await fetch(`/Waiver-Liability/api/waivers/check-signature-status.php?waiver_id=${encodeURIComponent(waiverId)}`,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});if(response.status===401){stopped=true;location.href='index.php?page=login';return}if(!response.ok)return;const data=await response.json();if(data.signed){showSigned(data);return}if(data.request_status&&data.request_status!=='ACTIVE'){stopped=true;location.reload()}}catch(error){/* Keep waiting through short network interruptions. */}};
 tick();poll();const clock=setInterval(tick,1000),watch=setInterval(poll,2500);window.addEventListener('pagehide',()=>{clearInterval(clock);clearInterval(watch)},{once:true});
});

// Protect active waiver sessions. Saved DRAFT and employee-signature states
// remain resumable from the waiver register.
const waiverSession=document.querySelector('[data-waiver-session]');
if(waiverSession){
 let allowNavigation=false,pendingUrl='';
 const dialog=document.createElement('dialog');
 dialog.className='route-guard';
 dialog.innerHTML='<div class="route-guard-icon">!</div><h2>Leave this waiver?</h2><p>All progress will be lost if you leave this active waiver session.</p><div class="route-guard-actions"><button type="button" class="btn secondary" data-stay>Stay here</button><button type="button" class="btn danger" data-leave>Leave page</button></div>';
 document.body.appendChild(dialog);
 document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{allowNavigation=true}));
 window.addEventListener('beforeunload',event=>{if(allowNavigation)return;event.preventDefault();event.returnValue=''});
 document.querySelectorAll('.sidebar a, .topbar a, a[data-leave-workflow]').forEach(link=>link.addEventListener('click',event=>{
  if(allowNavigation||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||link.closest('.new-waiver-page'))return;
  event.preventDefault();pendingUrl=link.href;
  if(typeof dialog.showModal==='function')dialog.showModal();
  else if(window.confirm('All progress will be lost if you leave this active waiver session.')){allowNavigation=true;location.href=pendingUrl}
 }));
 dialog.querySelector('[data-stay]').addEventListener('click',()=>{pendingUrl='';dialog.close()});
 dialog.querySelector('[data-leave]').addEventListener('click',()=>{allowNavigation=true;dialog.close();location.href=pendingUrl});
 dialog.addEventListener('cancel',event=>{event.preventDefault();pendingUrl='';dialog.close()});
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
 let toolbar=host.querySelector('.server-table-toolbar');
 const serverFiltered=Boolean(toolbar);
 if(!toolbar){
  toolbar=document.createElement('div');toolbar.className='table-toolbar';
  toolbar.innerHTML='<label class="table-search"><span>Search</span><input type="search" placeholder="Search waivers by employee, department, or type..." data-table-search></label><label><span>Status</span><select data-table-filter data-column="4"><option value="">All statuses</option></select></label><label><span>Waiver type</span><select data-table-filter data-column="3"><option value="">All types</option></select></label>';
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
 search?.addEventListener('input',()=>{page=1;render()});filters.forEach(filter=>filter.addEventListener('change',()=>{page=1;render()}));host.querySelectorAll('[data-quick-status]').forEach(button=>button.addEventListener('click',()=>{const status=filters.find(filter=>filter.dataset.column==='4');if(status){status.value=button.dataset.quickStatus;page=1;render()}host.querySelectorAll('[data-quick-status]').forEach(item=>item.classList.toggle('active',item===button))}));size?.addEventListener('change',()=>{page=1;render()});prev.addEventListener('click',()=>{if(page>1){page--;render()}});next.addEventListener('click',()=>{const pages=Math.max(1,Math.ceil(matching().length/pageSize()));if(page<pages){page++;render()}});render();
});

// Login controls: reveal the password on demand and remember only the username.
document.querySelectorAll('[data-password-toggle]').forEach(button=>button.addEventListener('click',()=>{const input=button.closest('.auth-input')?.querySelector('input');if(!input)return;const showing=input.type==='text';input.type=showing?'password':'text';button.setAttribute('aria-pressed',String(!showing));button.setAttribute('aria-label',showing?'Show password':'Hide password')}));
document.querySelectorAll('[data-login-form]').forEach(form=>{const username=form.querySelector('input[name="username"]'),remember=form.querySelector('[data-remember-username]');if(!username||!remember)return;const saved=localStorage.getItem('waiverDeskUsername');if(saved&&!username.value){username.value=saved;remember.checked=true}form.addEventListener('submit',()=>{if(remember.checked)localStorage.setItem('waiverDeskUsername',username.value);else localStorage.removeItem('waiverDeskUsername')})});
