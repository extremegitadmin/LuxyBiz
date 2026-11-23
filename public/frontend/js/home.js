(function(){
  var $=function(s,c){return (c||document).querySelector(s)},$$=function(s,c){return [].slice.call((c||document).querySelectorAll(s))};
  /* ---- header menu ---- */
  var hdr=$('#hdr'),burger=$('#burger');
  function closeMenu(){hdr.classList.remove('open');burger.setAttribute('aria-expanded','false');burger.setAttribute('aria-label','Open menu')}
  burger.addEventListener('click',function(){var o=hdr.classList.toggle('open');burger.setAttribute('aria-expanded',o);burger.setAttribute('aria-label',o?'Close menu':'Open menu')});
  $('#menu').addEventListener('click',function(e){if(e.target.closest('a'))closeMenu()});
  addEventListener('keydown',function(e){if(e.key==='Escape'&&hdr.classList.contains('open')){closeMenu();burger.focus()}});

  /* ---- automatically changing hero images ---- */
  var scenes=$$('.scene'),rgs=$$('.rg'),sceneIndex=0;
  function showScene(index){
    sceneIndex=(index+scenes.length)%scenes.length;
    scenes.forEach(function(scene,i){scene.classList.toggle('is-active',i===sceneIndex)});
    rgs.forEach(function(button,i){
      button.setAttribute('aria-pressed',i===sceneIndex);
      $('i',button).style.width=i===sceneIndex?'100%':'0';
    });
  }
  rgs.forEach(function(button,index){button.addEventListener('click',function(){showScene(index)})});
  if(scenes.length>1)setInterval(function(){showScene(sceneIndex+1)},6500);

  /* ---- destination carousel ---- */
  var destinations=$$('.stack .dest'),destinationStatus=$('#dest-slide-status'),destinationIndex=0;
  function showDestination(index){
    destinationIndex=(index+destinations.length)%destinations.length;
    destinations.forEach(function(destination,i){destination.hidden=i!==destinationIndex});
    destinationStatus.textContent=(destinationIndex+1)+' of '+destinations.length+' — '+$('h3',destinations[destinationIndex]).textContent;
  }
  $('#dest-prev').addEventListener('click',function(){showDestination(destinationIndex-1)});
  $('#dest-next').addEventListener('click',function(){showDestination(destinationIndex+1)});
  showDestination(0);

  /* ---- currency ---- */
  $$('[data-cur]').forEach(function(b){b.addEventListener('click',function(){
    var c=b.dataset.cur;
    $$('[data-cur]').forEach(function(x){x.setAttribute('aria-pressed',x.dataset.cur===c)});
    $$('[data-usd]').forEach(function(p){p.textContent=p.dataset[c]});
  })});

  /* ---- journey filter ---- */
  var cards=$$('.jc');
  var packagePages={
    'Munnar Mist & Tea Trails':'munnar-mist-tea-trails',
    'Alleppey Private Houseboat Escape':'alleppey-private-houseboat-escape',
    'Varkala Cliffside Calm':'varkala-cliffside-calm',
    'Kerala Wellness Retreat':'kerala-wellness-retreat',
    'Heritage and Temple Towns':'heritage-and-temple-towns',
    'Highlands to Backwaters Grand Loop':'highlands-to-backwaters-grand-loop'
  };
  cards.forEach(function(card){
    var slug=packagePages[$('h3',card).textContent.trim()],link=$('.tlink',card);
    if(slug&&link)link.href='package-tiers.html?package='+encodeURIComponent(slug);
  });
  function filter(f){
    cards.forEach(function(c){c.hidden=!(f==='all'||c.dataset.cat.split(' ').indexOf(f)>-1)});
    $$('.chip').forEach(function(x){x.setAttribute('aria-pressed',x.dataset.f===f)});
  }
  $$('.chip').forEach(function(ch){ch.addEventListener('click',function(){filter(ch.dataset.f)})});
  $$('[data-go]').forEach(function(a){a.addEventListener('click',function(){filter(a.dataset.go)})});

  /* ---- testimonials ---- */
  var qs=$$('[data-q]'),qi=0,dots=$('#qdots');
  qs.forEach(function(_,i){var d=document.createElement('button');d.type='button';d.setAttribute('aria-label','Show testimonial '+(i+1));d.addEventListener('click',function(){showQ(i)});dots.appendChild(d)});
  function showQ(i){qi=(i+qs.length)%qs.length;qs.forEach(function(q,k){q.hidden=k!==qi});$$('button',dots).forEach(function(d,k){if(k===qi)d.setAttribute('aria-current','true');else d.removeAttribute('aria-current')})}
  $('#qprev').addEventListener('click',function(){showQ(qi-1)});$('#qnext').addEventListener('click',function(){showQ(qi+1)});
  showQ(0);

  /* ---- enquiry form (front-end only) ---- */
  var form=$('#enq');
  form.addEventListener('submit',function(e){
    e.preventDefault();
    var n=$('#f-name'),m=$('#f-mail'),p=$('#f-phone'),ok=true;
    function bad(el,id,msg){$('#'+id).textContent=msg;el.setAttribute('aria-invalid','true');ok=false}
    function good(el,id){$('#'+id).textContent='';el.removeAttribute('aria-invalid')}
    good(n,'e-name');good(m,'e-mail');good(p,'e-phone');
    if(!n.value.trim())bad(n,'e-name','Please enter your name.');
    if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(m.value.trim()))bad(m,'e-mail','Please enter a valid email address.');
    if(p.value.trim()&&!/^[0-9][0-9\s-]{5,14}$/.test(p.value.trim()))bad(p,'e-phone','Please enter a valid phone number.');
    var msg=$('#ok');
    if(!ok){msg.hidden=true;(n.hasAttribute('aria-invalid')?n:m.hasAttribute('aria-invalid')?m:p).focus();return}
    msg.textContent='Thank you, '+n.value.trim().split(' ')[0]+'. Your enquiry is ready. Once the form is connected to your backend, it will reach your planner.';
    msg.hidden=false;
  });

})();
