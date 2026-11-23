document.documentElement.className += ' js';
(function(){
  var $=function(s,c){return (c||document).querySelector(s)},$$=function(s,c){return [].slice.call((c||document).querySelectorAll(s))};
  var S=window.LuxyStore;
  var views=['login','register','forgot','reset'];

  /* where to go after signing in: ?next=page.html (same folder only, never an outside address) */
  function nextUrl(){
    var n=new URLSearchParams(location.search).get('next');
    return n&&/^[\w\-]+\.html(\?[\w\-=&%.]*)?(?:#[\w\-]+)?$/.test(n)?n:'dashboard.html';
  }

  /* already signed in? go straight to the dashboard */
  if(S&&S.current()&&!/reset|forgot/.test(location.hash)){location.replace(nextUrl());return}

  /* ---- routing by hash: #login (default), #register, #forgot, #reset ---- */
  function route(){
    var v=location.hash.replace('#','');if(views.indexOf(v)<0)v='login';
    views.forEach(function(n){$('#v-'+n).classList.toggle('on',n===v)});
    $('#tabs').hidden=(v==='forgot'||v==='reset');
    $$('#tabs a').forEach(function(a){a.setAttribute('aria-selected',a.getAttribute('href')==='#'+v)});
    $$('.vis img').forEach(function(i){i.classList.toggle('on',i.dataset.for===(v==='register'?'register':'login'))});
    $$('.vcopy').forEach(function(c){c.classList.toggle('on',c.dataset.for===(v==='reset'?'forgot':v))});
    document.title=({login:'Log in',register:'Create an account',forgot:'Reset your password',reset:'Choose a new password'})[v]+' | LuxyBiz Tours & Wellness';
  }
  addEventListener('hashchange',function(){route();var h=$('#v-'+(location.hash.replace('#','')||'login')+' h1');if(h){h.setAttribute('tabindex','-1');h.focus({preventScroll:true})}});
  route();

  /* ---- show / hide password ---- */
  $$('[data-toggle]').forEach(function(b){b.addEventListener('click',function(){
    var i=document.getElementById(b.dataset.toggle),show=i.type==='password';
    i.type=show?'text':'password';b.textContent=show?'Hide':'Show';b.setAttribute('aria-pressed',show);b.setAttribute('aria-label',show?'Hide password':'Show password');
  })});

  /* ---- helpers ---- */
  var EMAIL=/^[^@\s]+@[^@\s]+\.[^@\s]+$/;
  function setErr(input,errId,msg){
    var e=document.getElementById(errId);e.textContent=msg||'';
    if(msg)input.setAttribute('aria-invalid','true');else input.removeAttribute('aria-invalid');
    return !msg;
  }
  function firstInvalid(form){var el=$('[aria-invalid=true]',form);if(el)el.focus()}
  function busy(form,on){var b=$('button[type=submit]',form);b.disabled=on;b.style.opacity=on?.6:''}
  function status(id,msg){var s=document.getElementById(id);s.textContent=msg;s.hidden=!msg}
  /* If a form has data-endpoint it posts JSON to your server; otherwise the built-in LuxyStore is used. */
  function remote(form,payload){
    return fetch(form.dataset.endpoint,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(payload)})
      .then(function(r){return {ok:r.ok}});
  }

  /* clear a field's error as soon as the person edits it */
  $$('form.af input').forEach(function(i){
    var clear=function(){
      (i.getAttribute('aria-describedby')||'').split(' ').forEach(function(id){if(/-e$/.test(id)){var e=document.getElementById(id);if(e)e.textContent=''}});
      i.removeAttribute('aria-invalid');
    };
    i.addEventListener('input',clear);i.addEventListener('change',clear);
  });

  /* ---- log in ---- */
  var fl=$('#f-login');
  $('#fill-demo').addEventListener('click',function(){$('#l-mail').value='demo@luxybiz.example';$('#l-pass').value='Demo@1234';$('#l-pass').focus()});
  fl.addEventListener('submit',function(e){
    e.preventDefault();
    var m=$('#l-mail'),p=$('#l-pass'),ok=true;
    $('#login-alert').hidden=true;status('login-status','');
    ok=setErr(m,'l-mail-e',!m.value.trim()?'Enter your email.':(!EMAIL.test(m.value.trim())?'Enter a valid email address.':''))&&ok;
    ok=setErr(p,'l-pass-e',!p.value?'Enter your password.':'')&&ok;
    if(!ok){firstInvalid(fl);return}
    busy(fl,true);
    var call=fl.dataset.endpoint?remote(fl,{email:m.value.trim(),password:p.value}):S.login(m.value,p.value);
    call.then(function(r){
      busy(fl,false);
      if(r.ok){location.href=nextUrl();return}
      /* one generic message for any failure, so the page never reveals which part was wrong */
      $('#login-alert').hidden=false;p.value='';p.focus();
    }).catch(function(){busy(fl,false);$('#login-alert').hidden=false});
  });

  /* ---- create account ---- */
  var fr=$('#f-register'),pass=$('#r-pass'),meter=$('#meter'),label=$('#r-pass-h');
  var base=label.textContent,words=['','Weak','Okay','Good','Strong'];
  pass.addEventListener('input',function(){
    var v=pass.value,s=0;
    if(v.length>=8)s++;if(v.length>=12)s++;if(/[a-z]/.test(v)&&/[A-Z]/.test(v))s++;if(/\d/.test(v)&&/[^A-Za-z0-9]/.test(v))s++;
    if(v.length<8&&v.length>0)s=Math.min(s,1);
    meter.dataset.s=v?s||1:0;label.textContent=v?words[s||1]+(v.length<8?'. Use at least 8 characters.':''):base;
  });
  fr.addEventListener('submit',function(e){
    e.preventDefault();
    var n=$('#r-name'),m=$('#r-mail'),ph=$('#r-phone'),p=$('#r-pass'),p2=$('#r-pass2'),ag=$('#r-agree'),ok=true;
    $('#reg-alert').hidden=true;status('reg-status','');
    ok=setErr(n,'r-name-e',!n.value.trim()?'Enter your full name.':'')&&ok;
    ok=setErr(m,'r-mail-e',!m.value.trim()?'Enter your email.':(!EMAIL.test(m.value.trim())?'Enter a valid email address.':''))&&ok;
    ok=setErr(ph,'r-phone-e',ph.value.trim()&&!/^[+\d][\d\s\-()]{6,}$/.test(ph.value.trim())?'Enter a valid phone number, or leave it blank.':'')&&ok;
    ok=setErr(p,'r-pass-e',p.value.length<8?'Use at least 8 characters.':'')&&ok;
    ok=setErr(p2,'r-pass2-e',p2.value!==p.value?'The passwords do not match.':(!p2.value?'Confirm your password.':''))&&ok;
    ok=setErr(ag,'r-agree-e',!ag.checked?'Please accept the Terms and Privacy Policy to continue.':'')&&ok;
    if(!ok){firstInvalid(fr);return}
    busy(fr,true);
    var d={name:n.value.trim(),email:m.value.trim(),phone:ph.value.trim(),password:p.value};
    var call=fr.dataset.endpoint?remote(fr,d):S.register(d);
    call.then(function(r){
      busy(fr,false);
      if(r.ok){location.href=nextUrl();return}
      $('#reg-alert').hidden=false;
    }).catch(function(){busy(fr,false);$('#reg-alert').hidden=false});
  });

  /* ---- forgot password ---- */
  var ff=$('#f-forgot'),MSG='If an account exists for that email, a reset link is on its way.';
  ff.addEventListener('submit',function(e){
    e.preventDefault();
    var m=$('#f-mail');status('forgot-status','');$('#demo-link').hidden=true;
    if(!setErr(m,'f-mail-e',!m.value.trim()?'Enter your email.':(!EMAIL.test(m.value.trim())?'Enter a valid email address.':''))){m.focus();return}
    busy(ff,true);
    S.requestReset(m.value).then(function(){
      busy(ff,false);status('forgot-status',MSG);
      /* DEMO ONLY: with no email service, offer the link directly (only when an account exists). */
      var t=S.peekReset(m.value);
      if(t){try{sessionStorage.setItem('luxy.resetTok',t)}catch(x){}$('#demo-link').hidden=false}
    });
  });

  /* ---- choose a new password ---- */
  var fz=$('#f-reset');
  fz.addEventListener('submit',function(e){
    e.preventDefault();
    var p=$('#n-pass'),p2=$('#n-pass2'),ok=true;$('#reset-alert').hidden=true;
    ok=setErr(p,'n-pass-e',p.value.length<8?'Use at least 8 characters.':'')&&ok;
    ok=setErr(p2,'n-pass2-e',p2.value!==p.value?'The passwords do not match.':'')&&ok;
    if(!ok){firstInvalid(fz);return}
    var tok=new URLSearchParams(location.search).get('token')||(function(){try{return sessionStorage.getItem('luxy.resetTok')}catch(x){return null}})();
    busy(fz,true);
    S.resetPassword(tok,p.value).then(function(r){
      busy(fz,false);
      if(!r.ok){$('#reset-alert').hidden=false;return}
      try{sessionStorage.removeItem('luxy.resetTok')}catch(x){}
      fz.reset();location.hash='#login';status('login-status','Your password has been updated. Log in with your new password.');
    });
  });
})();
