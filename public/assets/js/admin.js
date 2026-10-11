(() => {
  const root=document.body;
  const saved=(key,fallback)=>{try{return localStorage.getItem(key)||fallback;}catch{return fallback;}};
  const save=(key,value)=>{try{localStorage.setItem(key,value);}catch{}};
  root.dataset.theme=saved('sk-theme','light');root.dataset.density=saved('sk-density','compact');
  const theme=document.querySelector('#theme-toggle'),density=document.querySelector('#density-toggle');
  const labels=()=>{theme.textContent=root.dataset.theme==='dark'?'Light mode':'Dark mode';density.textContent=root.dataset.density==='compact'?'Comfortable':'Compact';};labels();
  theme.addEventListener('click',()=>{root.dataset.theme=root.dataset.theme==='dark'?'light':'dark';save('sk-theme',root.dataset.theme);labels();});
  density.addEventListener('click',()=>{root.dataset.density=root.dataset.density==='compact'?'comfortable':'compact';save('sk-density',root.dataset.density);labels();});
  const toggle=document.querySelector('#sidebar-toggle'),sidebar=document.querySelector('.sidebar'),mobile=matchMedia('(max-width:760px)');
  const syncSidebar=()=>{sidebar.inert=mobile.matches&&!root.classList.contains('sidebar-open');toggle.setAttribute('aria-expanded',String(mobile.matches?root.classList.contains('sidebar-open'):!root.classList.contains('sidebar-collapsed')));};
  const close=()=>{root.classList.remove('sidebar-open');syncSidebar();toggle.focus();};
  if(saved('sk-sidebar','expanded')==='collapsed'&&!matchMedia('(max-width:760px)').matches)root.classList.add('sidebar-collapsed');
  syncSidebar();mobile.addEventListener('change',syncSidebar);
  toggle.addEventListener('click',()=>{if(mobile.matches){const open=root.classList.toggle('sidebar-open');syncSidebar();if(open)sidebar.querySelector('a').focus();}else{const collapsed=root.classList.toggle('sidebar-collapsed');save('sk-sidebar',collapsed?'collapsed':'expanded');syncSidebar();}});
  document.querySelector('.sidebar-backdrop').addEventListener('click',close);
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&root.classList.contains('sidebar-open'))close();});
  sidebar.addEventListener('keydown',e=>{if(e.key!=='Tab'||!mobile.matches||!root.classList.contains('sidebar-open'))return;const links=[...sidebar.querySelectorAll('a,button')];const first=links[0],last=links.at(-1);if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}});
  document.querySelectorAll('.editor').forEach(editor=>{
    const content=editor.querySelector('.editor-content'),source=document.getElementById(editor.dataset.field);
    editor.parentElement.classList.add('js-editor');
    content.addEventListener('input',()=>source.value=content.innerHTML);
    editor.querySelectorAll('[data-command]').forEach(button=>{
      button.addEventListener('mousedown',e=>e.preventDefault());
      button.addEventListener('click',()=>{content.focus();document.execCommand(button.dataset.command,false,null);source.value=content.innerHTML;});
    });
    content.addEventListener('paste',e=>{e.preventDefault();document.execCommand('insertText',false,e.clipboardData.getData('text/plain'));source.value=content.innerHTML;});
    editor.closest('form').addEventListener('submit',()=>source.value=content.innerHTML);
  });
  const dialog=document.getElementById('delete-dialog');let pending=null;
  document.querySelectorAll('.delete-form').forEach(form=>form.addEventListener('submit',e=>{e.preventDefault();pending=form;document.getElementById('delete-copy').textContent=`Delete “${form.dataset.name}”? This action cannot be undone.`;dialog.showModal();}));
  dialog.addEventListener('close',()=>{if(dialog.returnValue==='delete'&&pending)pending.submit();pending=null;});
  document.querySelectorAll('form:not(.delete-form)').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('button[type=submit],.form-actions .btn-primary');if(button){button.disabled=true;button.textContent='Saving…';}}));
})();
