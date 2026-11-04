(() => {
  if(!window.westerosViews)return;
  const data=new URLSearchParams({action:'westeros_view',post_id:String(westerosViews.id),nonce:westerosViews.nonce});
  fetch(westerosViews.url,{method:'POST',body:data,credentials:'same-origin',keepalive:true}).catch(()=>{});
})();
