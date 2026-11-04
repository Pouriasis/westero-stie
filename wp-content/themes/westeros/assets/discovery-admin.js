(() => {
  'use strict';
  const root=document.querySelector('[data-dossier-editor]');
  if(!root)return;
  const input=root.querySelector('input[type=search]'), picked=root.querySelector('[data-dossier-picked]');
  const results=root.querySelector('[data-dossier-results]'),status=root.querySelector('[data-dossier-status]');
  const searchButton=root.querySelector('[data-dossier-search]');
  let controller,sequence=0;
  const chosen=()=>new Set([...picked.querySelectorAll('[data-post-id]')].map(row=>Number(row.dataset.postId)));
  const update=()=>{
    const ids=chosen();root.querySelector('[data-dossier-empty]').hidden=ids.size>0;
    results.querySelectorAll('button[data-dossier-add]').forEach(button=>{button.disabled=ids.has(Number(button.dataset.dossierAdd))||ids.size>=40;button.textContent=ids.has(Number(button.dataset.dossierAdd))?'در فهرست است':'افزودن';});
    [...picked.children].forEach((row,i)=>{row.querySelector('[data-dossier-move=up]').disabled=i===0;row.querySelector('[data-dossier-move=down]').disabled=i===picked.children.length-1;});
  };
  const button=(text,attr,value)=>{const element=document.createElement('button');element.type='button';element.className='button';element.textContent=text;element.setAttribute(attr,value);return element;};
  const add=(id,title)=>{
    if(chosen().has(id)||chosen().size>=40)return;
    const row=document.createElement('li');row.dataset.postId=String(id);
    const hidden=document.createElement('input');hidden.type='hidden';hidden.name='ws_dossier_ids[]';hidden.value=String(id);
    const label=document.createElement('span');label.textContent=title;
    const actions=document.createElement('div');actions.append(button('بالا','data-dossier-move','up'),button('پایین','data-dossier-move','down'),button('حذف','data-dossier-remove',''));
    row.append(hidden,label,actions);picked.append(row);update();status.textContent='مطلب به انتهای مسیر اضافه شد.';
  };
  const search=async()=>{
    const text=input.value.trim();controller?.abort();const request=++sequence;
    if(text.length<2||text.length>100){results.replaceChildren();status.textContent='بین ۲ تا ۱۰۰ نویسه وارد کنید.';searchButton.disabled=false;return;}
    controller=new AbortController();searchButton.disabled=true;results.replaceChildren();status.textContent='در حال جست‌وجو…';
    const url=new URL(westerosDossierEditor.url,location.href);url.searchParams.set('action','westeros_dossier_search');url.searchParams.set('nonce',westerosDossierEditor.nonce);url.searchParams.set('q',text);
    try{
      const response=await fetch(url,{credentials:'same-origin',signal:controller.signal});const payload=await response.json();
      if(request!==sequence)return;
      if(!response.ok||!payload.success||!Array.isArray(payload.data))throw new Error('search');
      payload.data.forEach(item=>{if(!Number.isSafeInteger(item.id)||item.id<1||typeof item.title!=='string')return;const row=document.createElement('li');const label=document.createElement('span');label.textContent=item.title+(item.date?' — '+item.date:'');const action=button('افزودن','data-dossier-add',String(item.id));action.addEventListener('click',()=>add(item.id,item.title));row.append(label,action);results.append(row);});
      status.textContent=payload.data.length?'نتیجه‌ها آماده‌اند.':'مطلبی پیدا نشد.';update();
    }catch(error){if(error.name!=='AbortError'&&request===sequence){status.textContent='جست‌وجو انجام نشد. دوباره تلاش کنید.';}}
    finally{if(request===sequence)searchButton.disabled=false;}
  };
  searchButton.addEventListener('click',search);
  input.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();search();}});
  picked.addEventListener('click',event=>{
    const action=event.target.closest('button'),row=action?.closest('[data-post-id]');if(!row)return;
    if(action.hasAttribute('data-dossier-remove')){const focus=row.nextElementSibling||row.previousElementSibling;row.remove();update();(focus?.querySelector('button:not(:disabled)')||input).focus();status.textContent='مطلب از مسیر حذف شد.';return;}
    if(action.dataset.dossierMove==='up'&&row.previousElementSibling)picked.insertBefore(row,row.previousElementSibling);
    if(action.dataset.dossierMove==='down'&&row.nextElementSibling)picked.insertBefore(row.nextElementSibling,row);
    update();row.querySelector('button:not(:disabled)')?.focus();status.textContent='ترتیب مسیر به‌روز شد.';
  });
  update();
})();
