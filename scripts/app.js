document.querySelector('.menu')?.addEventListener('click',()=>document.querySelector('.sidebar').classList.toggle('open'));
document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm))e.preventDefault()}));
document.querySelector('#search')?.addEventListener('input',e=>document.querySelectorAll('[data-filter] tbody tr').forEach(row=>row.hidden=!row.textContent.toLowerCase().includes(e.target.value.toLowerCase())));
document.querySelectorAll('[data-signature]').forEach(canvas=>{const ctx=canvas.getContext('2d'), input=document.getElementById(canvas.dataset.signature), clear=canvas.parentElement.querySelector('[data-clear]'); let drawing=false,last=null;
 const point=e=>{const r=canvas.getBoundingClientRect(),t=e.touches?.[0]||e;return [(t.clientX-r.left)*canvas.width/r.width,(t.clientY-r.top)*canvas.height/r.height]};
 const start=e=>{drawing=true;last=point(e);e.preventDefault()}, move=e=>{if(!drawing)return;const p=point(e);ctx.strokeStyle='#244c38';ctx.lineWidth=2.4;ctx.lineCap='round';ctx.beginPath();ctx.moveTo(...last);ctx.lineTo(...p);ctx.stroke();last=p;input.value=canvas.toDataURL('image/png');e.preventDefault()},end=()=>drawing=false;
 canvas.addEventListener('pointerdown',start);canvas.addEventListener('pointermove',move);window.addEventListener('pointerup',end);clear?.addEventListener('click',()=>{ctx.clearRect(0,0,canvas.width,canvas.height);input.value=''});
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
 document.querySelectorAll('.sidebar a').forEach(link=>link.addEventListener('click',event=>{
  if(allowNavigation||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
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
