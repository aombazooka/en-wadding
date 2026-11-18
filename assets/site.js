(function(){
  'use strict';
  function $(id){ return document.getElementById(id); }
  var body = document.body;

  /* ---------------------------------------------------------
     Gallery photos — edit this list to change the gallery.
     k: 't' tall · 's' small (close-ups) · 'w' wide (landscape)
     --------------------------------------------------------- */
  var FEATURED = [
    ['p09','t'],['p24','s'],['p07','s'],
    ['p30','w'],
    ['p16','t'],['p17','t'],
    ['p25','s'],['p21','t'],['p26','s'],
    ['p31','w'],
    ['p13','t'],['p20','t'],
    ['p15','s'],['p10','s']
  ];
  var MORE = [
    ['p05','t'],['p06','t'],['p03','t'],['p04','t'],['p11','t'],['p12','t'],
    ['p27','w'],
    ['p14','t'],['p08','t'],['p18','t'],['p19','t'],['p00','s'],['p22','s'],
    ['p32','w'],
    ['p28','t'],['p29','t'],['p23','t'],['p01','t'],['p02','t']
  ];
  var WEDDING = new Date('2027-01-10T07:09:00+07:00').getTime();

  /* ---------- Guest name from link: ?to=คุณสมชาย ---------- */
  try{
    var to = new URLSearchParams(location.search).get('to');
    if(to && to.trim()){
      to = to.trim().slice(0,60);
      $('guestTo').textContent = 'เรียน ' + to;
      $('invitedAs').value = to;
    }
  }catch(e){}

  /* ---------- Music ---------- */
  var audio = $('bgm'), vinyl = $('vinyl'), mini = $('miniVinyl');
  var musicOk = true;
  function noMusic(){ musicOk = false; body.classList.add('no-music'); }
  audio.addEventListener('error', noMusic);
  if(audio.error) noMusic();
  function setPlaying(on){ vinyl.classList.toggle('playing', on); mini.classList.toggle('playing', on); }
  audio.addEventListener('play', function(){ setPlaying(true); });
  audio.addEventListener('pause', function(){ setPlaying(false); });
  function playMusic(){ if(musicOk){ var p = audio.play(); if(p && p.catch) p.catch(function(){}); } }
  function toggleMusic(){ if(!musicOk) return; if(audio.paused) playMusic(); else audio.pause(); }
  vinyl.addEventListener('click', toggleMusic);
  mini.addEventListener('click', toggleMusic);
  // pause when the tab is hidden, resume when back
  var wasPlaying = false;
  document.addEventListener('visibilitychange', function(){
    if(document.hidden){ wasPlaying = !audio.paused; audio.pause(); }
    else if(wasPlaying){ playMusic(); }
  });

  /* ---------- Envelope / cover ---------- */
  var env = $('envelope'), cover = $('cover'), main = $('main');
  var letter = $('envLetter');
  var opened = false, zoomed = false, entered = false, enterTimer = null;
  // 1) tap: seal breaks, flap opens, the card slides out
  function openCard(){
    if(opened) return; opened = true;
    env.classList.add('open');
    playMusic(); // inside the tap → browsers allow audio
    setTimeout(zoomCard, 1750);
  }
  // 2) the card moves to the centre of the screen and grows so guests can read it
  function zoomCard(){
    var r = letter.getBoundingClientRect();
    var s = Math.min(window.innerWidth * .86 / r.width, window.innerHeight * .76 / r.height);
    var dy = -.68 * r.height + (window.innerHeight * .47 - (r.top + r.height / 2));
    letter.style.setProperty('--dy', dy + 'px');
    letter.style.setProperty('--s', s);
    env.classList.add('zoom');
    cover.classList.add('zooming');
    zoomed = true;
    enterTimer = setTimeout(enterSite, 5200);
  }
  // 3) fade into the site (automatically, or on tap)
  function enterSite(){
    if(entered) return; entered = true;
    clearTimeout(enterTimer);
    cover.classList.add('gone');
    body.classList.remove('locked');
    body.classList.add('opened');
    main.classList.add('show');
    startReveal();
    setTimeout(function(){ var v = cover.querySelector('video'); if(v) v.pause(); }, 1400);
  }
  cover.addEventListener('click', function(){ if(zoomed) enterSite(); });
  document.addEventListener('keydown', function(e){ if(zoomed && !entered && (e.key === 'Enter' || e.key === ' ' || e.key === 'Escape')) enterSite(); });
  env.addEventListener('click', openCard);
  env.addEventListener('keydown', function(e){ if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); openCard(); } });

  /* ---------- Scroll reveal ---------- */
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function(entries){
    entries.forEach(function(en){ if(en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target); } });
  }, {threshold:.12, rootMargin:'0px 0px -6% 0px'}) : null;
  function startReveal(){
    document.querySelectorAll('.rv').forEach(function(el){ if(io) io.observe(el); else el.classList.add('in'); });
  }

  /* ---------- Videos play only while on screen ---------- */
  var vids = document.querySelectorAll('video.autoplay');
  if('IntersectionObserver' in window){
    var vio = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        var v = en.target;
        if(en.isIntersecting){ var p = v.play(); if(p && p.catch) p.catch(function(){}); } else { v.pause(); }
      });
    }, {threshold:.2});
    vids.forEach(function(v){ vio.observe(v); });
  }

  /* ---------- Countdown ---------- */
  var cd = { d:$('cd-d'), h:$('cd-h'), m:$('cd-m'), s:$('cd-s') };
  function tick(){
    var s = Math.floor(Math.max(0, WEDDING - Date.now()) / 1000);
    cd.d.textContent = Math.floor(s / 86400);
    cd.h.textContent = Math.floor(s % 86400 / 3600);
    cd.m.textContent = Math.floor(s % 3600 / 60);
    cd.s.textContent = s % 60;
  }
  tick(); setInterval(tick, 1000);

  /* ---------- Calendar (January 2027, 10th highlighted) ---------- */
  (function(){
    var y = 2027, m = 0, day = 10, html = '';
    ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(function(d){ html += '<span class="dow">' + d + '</span>'; });
    var first = new Date(y, m, 1).getDay(), last = new Date(y, m + 1, 0).getDate();
    for(var i = 0; i < first; i++) html += '<span class="muted"></span>';
    for(var d = 1; d <= last; d++) html += d === day ? '<span class="day-on" aria-label="วันงาน ' + d + ' มกราคม">' + d + '</span>' : '<span>' + d + '</span>';
    $('cal').innerHTML = html;
  })();

  /* ---------- Add to calendar ---------- */
  var EVT = {
    title:'งานแต่งงาน อาย & แน๊ป',
    start:'20270110T000900Z', end:'20270110T050000Z',
    place:'ศูนย์พัฒนาเด็กเล็กบ้านพรุยายชี ม.4 ต.ปากหมาก อ.ไชยา จ.สุราษฎร์ธานี',
    note:'แผนที่: https://maps.app.goo.gl/jGXVc8wTSyMyhtSRA'
  };
  $('addGcal').href = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
    + '&text=' + encodeURIComponent(EVT.title)
    + '&dates=' + EVT.start + '/' + EVT.end
    + '&location=' + encodeURIComponent(EVT.place)
    + '&details=' + encodeURIComponent(EVT.note);
  $('addIcs').addEventListener('click', function(){
    function esc(s){ return s.replace(/([,;\\])/g, '\\$1'); }
    var stamp = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d+/, '');
    var ics = ['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//EyeNap//Wedding//TH','CALSCALE:GREGORIAN','BEGIN:VEVENT',
      'UID:eye-nap-20270110@wedding','DTSTAMP:' + stamp,'DTSTART:' + EVT.start,'DTEND:' + EVT.end,
      'SUMMARY:' + esc(EVT.title),'LOCATION:' + esc(EVT.place),'DESCRIPTION:' + esc(EVT.note),
      'BEGIN:VALARM','TRIGGER:-P1D','ACTION:DISPLAY','DESCRIPTION:' + esc('พรุ่งนี้งานแต่งงาน อาย & แน๊ป'),'END:VALARM',
      'END:VEVENT','END:VCALENDAR'].join('\r\n');
    var url = URL.createObjectURL(new Blob([ics], {type:'text/calendar;charset=utf-8'}));
    var a = document.createElement('a'); a.href = url; a.download = 'eye-nap-wedding.ics';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function(){ URL.revokeObjectURL(url); }, 4000);
  });

  /* ---------- Gallery + lightbox ---------- */
  var ALL = FEATURED.concat(MORE);
  var PHOTOS = ALL.map(function(x){ return 'assets/photos/' + x[0] + '.jpg'; });
  var CARDS = ['assets/card/front.jpg', 'assets/card/back.jpg'];
  function fill(el, list, offset){
    list.forEach(function(item, i){
      var b = document.createElement('button');
      b.type = 'button'; b.className = item[1];
      b.setAttribute('aria-label', 'ดูรูปที่ ' + (offset + i + 1));
      b.dataset.i = offset + i;
      var img = new Image();
      img.alt = ''; img.loading = 'lazy'; img.decoding = 'async';
      img.onload = function(){ img.classList.add('ok'); };
      img.src = 'assets/photos/t/' + item[0] + '.jpg';
      if(img.complete) img.classList.add('ok');
      b.appendChild(img); el.appendChild(b);
    });
    el.addEventListener('click', function(e){ var b = e.target.closest('button'); if(b) openLb(PHOTOS, +b.dataset.i, b); });
  }
  fill($('gallery'), FEATURED, 0);
  fill($('galleryMore'), MORE, FEATURED.length);
  document.querySelector('.g-cover').addEventListener('click', function(){
    for(var i = 0; i < ALL.length; i++) if(ALL[i][0] === 'p05') return openLb(PHOTOS, i, this);
  });
  document.querySelector('.g-cover').style.cursor = 'zoom-in';

  var moreBtn = $('moreBtn'), more = $('galleryMore');
  moreBtn.textContent = 'ดูภาพทั้งหมด (' + ALL.length + ')';
  moreBtn.addEventListener('click', function(){
    var show = more.hidden;
    more.hidden = !show;
    moreBtn.textContent = show ? 'ย่อแกลเลอรี' : 'ดูภาพทั้งหมด (' + ALL.length + ')';
    if(!show) $('gallery-sec').scrollIntoView({behavior:'smooth'});
  });

  var lb = $('lightbox'), lbImg = $('lbImg'), lbCount = $('lbCount'), cur = 0, lastFocus = null, lbList = PHOTOS;
  function show(i){
    var n = lbList.length;
    cur = (i + n) % n;
    lbImg.src = lbList[cur];
    lbCount.textContent = (cur + 1) + ' / ' + n;
    [cur + 1, cur - 1].forEach(function(k){ (new Image()).src = lbList[(k + n) % n]; });
  }
  function openLb(list, i, from){
    lbList = list; lastFocus = from || null;
    show(i); lb.hidden = false; body.style.overflow = 'hidden';
    $('lbClose').focus();
  }
  $('cards').addEventListener('click', function(e){ var b = e.target.closest('button'); if(b) openLb(CARDS, +b.dataset.i, b); });
  function closeLb(){
    lb.hidden = true; body.style.overflow = ''; lbImg.removeAttribute('src');
    if(lastFocus) lastFocus.focus({preventScroll:true});
  }
  $('lbClose').addEventListener('click', closeLb);
  $('lbPrev').addEventListener('click', function(){ show(cur - 1); });
  $('lbNext').addEventListener('click', function(){ show(cur + 1); });
  lb.addEventListener('click', function(e){ if(e.target === lb) closeLb(); });
  document.addEventListener('keydown', function(e){
    if(lb.hidden) return;
    if(e.key === 'Escape') closeLb();
    else if(e.key === 'ArrowRight') show(cur + 1);
    else if(e.key === 'ArrowLeft') show(cur - 1);
  });
  var tx = null;
  lb.addEventListener('touchstart', function(e){ tx = e.touches[0].clientX; }, {passive:true});
  lb.addEventListener('touchend', function(e){
    if(tx === null) return;
    var dx = e.changedTouches[0].clientX - tx; tx = null;
    if(Math.abs(dx) > 45) show(cur + (dx < 0 ? 1 : -1));
  });

  /* ---------- Desktop stage slideshow ---------- */
  var stageImgs = document.querySelectorAll('#stageSlides img');
  var wide = window.matchMedia('(min-width:960px)');
  var si = 0;
  setInterval(function(){
    if(!wide.matches || !stageImgs.length) return;
    stageImgs[si].classList.remove('on');
    si = (si + 1) % stageImgs.length;
    stageImgs[si].classList.add('on');
  }, 6500);

  /* ---------- Floating "send wishes" button ---------- */
  var fab = $('fab'), rsvp = $('rsvp');
  function updateFab(){
    if(!opened) return;
    // show between the hero and the wishes section only
    var reached = rsvp.getBoundingClientRect().top < window.innerHeight * .9;
    fab.classList.toggle('on', window.scrollY > window.innerHeight * .8 && !reached);
  }
  window.addEventListener('scroll', updateFab, {passive:true});

  /* ---------- Wishes → wish.php ---------- */
  var form = $('wishForm'), btn = $('wishBtn'), errBox = $('formError');
  var photoInput = $('photo'), picker = $('picker'), preview = $('photoPreview'), removeBtn = $('photoRemove');
  var photoBlob = null, previewUrl = null;
  function showError(msg){ errBox.textContent = msg; errBox.hidden = false; }

  // Shrink to max 1600px JPEG in the browser (smaller upload, strips location metadata)
  function resizeImage(file){
    return new Promise(function(resolve, reject){
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function(){
        var s = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
        var w = Math.round(img.naturalWidth * s), h = Math.round(img.naturalHeight * s);
        var c = document.createElement('canvas'); c.width = w; c.height = h;
        var ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h); ctx.drawImage(img, 0, 0, w, h);
        URL.revokeObjectURL(url);
        c.toBlob(function(b){ b ? resolve(b) : reject(new Error('resize')); }, 'image/jpeg', 0.85);
      };
      img.onerror = function(){ URL.revokeObjectURL(url); reject(new Error('decode')); };
      img.src = url;
    });
  }
  function clearPhoto(){
    photoBlob = null; photoInput.value = '';
    if(previewUrl){ URL.revokeObjectURL(previewUrl); previewUrl = null; }
    preview.hidden = true; preview.removeAttribute('src');
    picker.classList.remove('has'); removeBtn.hidden = true;
  }
  photoInput.addEventListener('change', function(){
    errBox.hidden = true;
    var file = photoInput.files && photoInput.files[0];
    if(!file) return;
    resizeImage(file).then(function(blob){
      clearPhoto();
      photoBlob = blob; previewUrl = URL.createObjectURL(blob);
      preview.src = previewUrl; preview.hidden = false;
      picker.classList.add('has'); removeBtn.hidden = false;
    }).catch(function(){
      clearPhoto();
      showError('เปิดไฟล์รูปนี้ไม่ได้ กรุณาเลือกรูป JPG หรือ PNG');
    });
  });
  removeBtn.addEventListener('click', clearPhoto);

  form.addEventListener('submit', function(e){
    e.preventDefault();
    errBox.hidden = true;
    var nameEl = form.elements.name, msgEl = form.elements.message;
    var name = nameEl.value.trim();
    if(!name){ nameEl.focus(); showError('กรุณากรอกชื่อ'); return; }
    if(!msgEl.value.trim()){ msgEl.focus(); showError('กรุณาเขียนคำอวยพร'); return; }

    var fd = new FormData(form);
    if(photoBlob) fd.append('photo', photoBlob, 'photo.jpg');
    btn.disabled = true; btn.textContent = 'กำลังส่ง...';
    fetch('wish.php', {method:'POST', body:fd})
      .then(function(r){ return r.json().catch(function(){ return {ok:false}; }); })
      .then(function(res){
        if(!res.ok){
          throw new Error(res.error || (res.errors && Object.values(res.errors)[0]) || 'ส่งไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
        }
        $('thanksTitle').textContent = 'ขอบคุณค่ะ คุณ' + name;
        form.hidden = true;
        $('thanks').hidden = false;
        msgEl.value = ''; clearPhoto();
      })
      .catch(function(err){
        showError(err && err.message && err.message !== 'Failed to fetch' ? err.message : 'เชื่อมต่อไม่ได้ กรุณาลองใหม่อีกครั้ง');
      })
      .finally(function(){ btn.disabled = false; btn.textContent = 'ส่งคำอวยพร'; });
  });
  $('wishAgain').addEventListener('click', function(){
    $('thanks').hidden = true;
    form.hidden = false;
  });
})();
