(() => {
  const root=document.body;
  const saved=(key,fallback)=>{try{return localStorage.getItem(key)||fallback;}catch{return fallback;}};
  const save=(key,value)=>{try{localStorage.setItem(key,value);}catch{}};
  root.dataset.theme=['light','dark'].includes(saved('sk-theme','light'))?saved('sk-theme','light'):'light';
  root.dataset.density=['compact','comfortable'].includes(saved('sk-density','compact'))?saved('sk-density','compact'):'compact';
  const theme=document.querySelector('#theme-toggle'),density=document.querySelector('#density-toggle');
  const labels=()=>{theme.textContent=root.dataset.theme==='dark'?'Light mode':'Dark mode';density.textContent=root.dataset.density==='compact'?'Comfortable':'Compact';};
  labels();
  theme.addEventListener('click',()=>{root.dataset.theme=root.dataset.theme==='dark'?'light':'dark';save('sk-theme',root.dataset.theme);labels();});
  density.addEventListener('click',()=>{root.dataset.density=root.dataset.density==='compact'?'comfortable':'compact';save('sk-density',root.dataset.density);labels();});
  const toggle=document.querySelector('#sidebar-toggle'),sidebar=document.querySelector('.sidebar'),mobile=matchMedia('(max-width:760px)');
  const syncSidebar=()=>{sidebar.inert=mobile.matches&&!root.classList.contains('sidebar-open');toggle.setAttribute('aria-expanded',String(mobile.matches?root.classList.contains('sidebar-open'):!root.classList.contains('sidebar-collapsed')));};
  const close=()=>{root.classList.remove('sidebar-open');syncSidebar();toggle.focus();};
  if(saved('sk-sidebar','expanded')==='collapsed'&&!mobile.matches)root.classList.add('sidebar-collapsed');
  syncSidebar();mobile.addEventListener('change',syncSidebar);
  toggle.addEventListener('click',()=>{
    if(mobile.matches){const open=root.classList.toggle('sidebar-open');syncSidebar();if(open)sidebar.querySelector('a').focus();}
    else{const collapsed=root.classList.toggle('sidebar-collapsed');save('sk-sidebar',collapsed?'collapsed':'expanded');syncSidebar();}
  });
  document.querySelector('.sidebar-backdrop').addEventListener('click',close);
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&root.classList.contains('sidebar-open'))close();});
  sidebar.addEventListener('keydown',event=>{
    if(event.key!=='Tab'||!mobile.matches||!root.classList.contains('sidebar-open'))return;
    const links=[...sidebar.querySelectorAll('a,button')],first=links[0],last=links.at(-1);
    if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
    else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
  });
  document.querySelectorAll('.editor').forEach(editor=>{
    const content=editor.querySelector('.editor-content'),source=document.getElementById(editor.dataset.field);
    editor.parentElement.classList.add('js-editor');
    content.addEventListener('input',()=>source.value=content.innerHTML);
    editor.querySelectorAll('[data-command]').forEach(button=>{
      button.addEventListener('mousedown',event=>event.preventDefault());
      button.addEventListener('click',()=>{content.focus();document.execCommand(button.dataset.command,false,null);source.value=content.innerHTML;});
    });
    content.addEventListener('paste',event=>{event.preventDefault();document.execCommand('insertText',false,event.clipboardData.getData('text/plain'));source.value=content.innerHTML;});
    editor.closest('form').addEventListener('submit',()=>source.value=content.innerHTML);
  });
  document.querySelectorAll('[data-upload-kind]').forEach(input=>{
    const field=input.closest('.upload-field'),feedback=field.querySelector('.upload-feedback'),preview=field.querySelector('.upload-preview');
    let objectUrl=null;
    input.addEventListener('change',()=>{
      input.setCustomValidity('');feedback.textContent='';feedback.classList.remove('danger-text');preview.hidden=true;
      if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=null;}
      const file=input.files[0];if(!file)return;
      const pdf=input.dataset.uploadKind==='pdf';
      const allowed=pdf?/\.pdf$/i:/\.(jpe?g|png|webp)$/i;
      let error='';
      if(!allowed.test(file.name))error=pdf?'Choose a PDF document.':'Choose a JPG, PNG or WebP image.';
      else if(file.size>Number(input.dataset.maxBytes))error='Maximum file size is '+(pdf?10:5)+' MB.';
      if(error){input.setCustomValidity(error);feedback.textContent=error;feedback.classList.add('danger-text');return;}
      feedback.textContent='Selected: '+file.name+' ('+(file.size/1024).toFixed(1)+' KB). Save to upload.';
      if(!pdf){objectUrl=URL.createObjectURL(file);preview.src=objectUrl;preview.hidden=false;}
    });
  });
  const dialog=document.getElementById('delete-dialog');let pending=null;
  document.querySelectorAll('.delete-form').forEach(form=>form.addEventListener('submit',event=>{
    event.preventDefault();pending=form;dialog.returnValue='cancel';
    document.getElementById('delete-copy').textContent='Delete “'+form.dataset.name+'”? This action cannot be undone.';
    dialog.showModal();
  }));
  dialog.addEventListener('close',()=>{if(dialog.returnValue==='delete'&&pending)pending.submit();pending=null;});
  document.querySelectorAll('form:not(.delete-form):not([method="dialog"])').forEach(form=>form.addEventListener('submit',event=>{
    if(form.dataset.submitting==='true'){event.preventDefault();return;}
    const button=event.submitter;if(!button)return;
    // Disabled submit buttons do not contribute their name/value. Preserve the chosen save action.
    if(button.name){const hidden=document.createElement('input');hidden.type='hidden';hidden.name=button.name;hidden.value=button.value;hidden.dataset.submitAction='1';form.append(hidden);}
    form.dataset.submitting='true';button.dataset.submitLabel=button.textContent;button.disabled=true;
    button.textContent=form.method.toLowerCase()==='get'?'Loading…':'Saving…';
  }));
  window.addEventListener('pageshow',()=>{
    document.querySelectorAll('[data-submit-label]').forEach(button=>{button.disabled=false;button.textContent=button.dataset.submitLabel;delete button.dataset.submitLabel;});
    document.querySelectorAll('[data-submitting]').forEach(form=>delete form.dataset.submitting);
    document.querySelectorAll('[data-submit-action]').forEach(input=>input.remove());
  });
})();