(function(){
  var WF=window.WF||{mode:'r1'}, R2=WF.mode==='r2';
  var SPRITE="<svg width=\"0\" height=\"0\" style=\"position:absolute\" aria-hidden=\"true\"><defs>\n  <symbol id=\"i-home\" viewBox=\"0 0 24 24\"><path d=\"M3 11l9-8 9 8\"/><path d=\"M5 10v10h14V10\"/></symbol>\n  <symbol id=\"i-store\" viewBox=\"0 0 24 24\"><path d=\"M3 9l1.5-5h15L21 9\"/><path d=\"M4 9v11h16V9\"/><path d=\"M9 20v-6h6v6\"/><path d=\"M3 9h18\"/></symbol>\n  <symbol id=\"i-users\" viewBox=\"0 0 24 24\"><circle cx=\"9\" cy=\"8\" r=\"3.5\"/><path d=\"M2 20c0-4 3-6 7-6s7 2 7 6\"/><path d=\"M17 5a3.5 3.5 0 010 7M22 20c0-3-2-5-4-5.5\"/></symbol>\n  <symbol id=\"i-search\" viewBox=\"0 0 24 24\"><circle cx=\"11\" cy=\"11\" r=\"7\"/><path d=\"M20 20l-4-4\"/></symbol>\n  <symbol id=\"i-shield\" viewBox=\"0 0 24 24\"><path d=\"M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z\"/><path d=\"M9 12l2 2 4-4\"/></symbol>\n  <symbol id=\"i-box\" viewBox=\"0 0 24 24\"><path d=\"M21 8l-9-5-9 5v8l9 5 9-5z\"/><path d=\"M3 8l9 5 9-5M12 13v8\"/></symbol>\n  <symbol id=\"i-clip\" viewBox=\"0 0 24 24\"><rect x=\"6\" y=\"4\" width=\"12\" height=\"17\" rx=\"2\"/><path d=\"M9 4h6v3H9zM9 12h6M9 16h4\"/></symbol>\n  <symbol id=\"i-layers\" viewBox=\"0 0 24 24\"><path d=\"M12 3l9 5-9 5-9-5z\"/><path d=\"M3 13l9 5 9-5\"/></symbol>\n  <symbol id=\"i-cart\" viewBox=\"0 0 24 24\"><circle cx=\"9\" cy=\"20\" r=\"1.5\"/><circle cx=\"18\" cy=\"20\" r=\"1.5\"/><path d=\"M2 3h3l2.5 12h11l2-8H6\"/></symbol>\n  <symbol id=\"i-receipt\" viewBox=\"0 0 24 24\"><path d=\"M6 3h12v18l-3-2-3 2-3-2-3 2z\"/><path d=\"M9 8h6M9 12h6\"/></symbol>\n  <symbol id=\"i-undo\" viewBox=\"0 0 24 24\"><path d=\"M9 14L4 9l5-5\"/><path d=\"M4 9h10a6 6 0 010 12h-3\"/></symbol>\n  <symbol id=\"i-truck\" viewBox=\"0 0 24 24\"><path d=\"M2 6h11v10H2zM13 9h4l4 4v3h-8\"/><circle cx=\"7\" cy=\"18\" r=\"1.8\"/><circle cx=\"17\" cy=\"18\" r=\"1.8\"/></symbol>\n  <symbol id=\"i-clock\" viewBox=\"0 0 24 24\"><circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 7v5l3 2\"/></symbol>\n  <symbol id=\"i-minus\" viewBox=\"0 0 24 24\"><circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M8 12h8\"/></symbol>\n  <symbol id=\"i-lock\" viewBox=\"0 0 24 24\"><rect x=\"4\" y=\"10\" width=\"16\" height=\"11\" rx=\"2\"/><path d=\"M8 10V7a4 4 0 018 0v3\"/></symbol>\n  <symbol id=\"i-chart\" viewBox=\"0 0 24 24\"><path d=\"M4 20V10M10 20V4M16 20v-7M22 20H2\"/></symbol>\n  <symbol id=\"i-cog\" viewBox=\"0 0 24 24\"><circle cx=\"12\" cy=\"12\" r=\"3\"/><path d=\"M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2\"/></symbol>\n  <symbol id=\"i-plus\" viewBox=\"0 0 24 24\"><path d=\"M12 5v14M5 12h14\"/></symbol>\n  <symbol id=\"i-upload\" viewBox=\"0 0 24 24\"><path d=\"M12 16V4M7 9l5-5 5 5M4 20h16\"/></symbol>\n  <symbol id=\"i-down\" viewBox=\"0 0 24 24\"><path d=\"M12 4v12M7 11l5 5 5-5M4 20h16\"/></symbol>\n  <symbol id=\"i-printer\" viewBox=\"0 0 24 24\"><path d=\"M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z\"/></symbol>\n  <symbol id=\"i-check\" viewBox=\"0 0 24 24\"><path d=\"M4 12l5 5L20 6\"/></symbol>\n  <symbol id=\"i-x\" viewBox=\"0 0 24 24\"><path d=\"M6 6l12 12M18 6L6 18\"/></symbol>\n  <symbol id=\"i-alert\" viewBox=\"0 0 24 24\"><path d=\"M12 3l10 18H2z\"/><path d=\"M12 10v5M12 18v.5\"/></symbol>\n  <symbol id=\"i-info\" viewBox=\"0 0 24 24\"><circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 11v6M12 7.5v.5\"/></symbol>\n  <symbol id=\"i-arrow\" viewBox=\"0 0 24 24\"><path d=\"M5 12h14M13 6l6 6-6 6\"/></symbol>\n  <symbol id=\"i-back\" viewBox=\"0 0 24 24\"><path d=\"M19 12H5M11 6l-6 6 6 6\"/></symbol>\n  <symbol id=\"i-wallet\" viewBox=\"0 0 24 24\"><path d=\"M3 7h16a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2z\"/><path d=\"M3 7V6a2 2 0 012-2h12M16 14h2\"/></symbol>\n  <symbol id=\"i-tag\" viewBox=\"0 0 24 24\"><path d=\"M3 12V3h9l9 9-9 9z\"/><circle cx=\"8\" cy=\"8\" r=\"1.5\"/></symbol>\n  <symbol id=\"i-ship\" viewBox=\"0 0 24 24\"><path d=\"M3 17l2 3h14l2-3zM6 17V9h12v8M12 9V4\"/></symbol>\n  <symbol id=\"i-swap\" viewBox=\"0 0 24 24\"><path d=\"M4 8h14l-3-3M20 16H6l3 3\"/></symbol>\n</defs></svg>";
  document.body.insertAdjacentHTML('afterbegin',SPRITE);
  document.body.insertAdjacentHTML('afterbegin','<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs><symbol id="i-ruler" viewBox="0 0 24 24"><path d="M3 17L17 3l4 4L7 21z"/><path d="M7 13l2 2M10 10l2 2M13 7l2 2"/></symbol></defs></svg>');
  var $=function(s,r){return (r||document).querySelector(s)}, $$=function(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))};
  var ic=function(n){return '<svg class="ic"><use href="#i-'+n+'"/></svg>'};

  var SIDE={
    pusat:[['Dashboard','home'],['Master Toko','store'],['User & Akses','users']],
    store:[['Dashboard','home'],'Barang',['Daftar Barang','box'],['Ubah Harga Massal','wallet'],'Stok',['Stok Opname','clip'],['Histori Stok','layers'],'Penjualan',['Kasir','cart'],['Riwayat Nota','receipt'],['Surat Jalan','truck'],['Retur','undo'],'Kas & Harian',['Rekap Shift','clock'],['Pengeluaran','minus'],['Tutup Hari','lock'],['Laporan','chart'],'Pengaturan',['Pengaturan Toko','cog']]
  };
  var R2STORE=['Menyusul (R2)',['Pembelian','tag'],['Penerimaan Kontainer','ship'],['Transfer Gudang','swap'],['Audit Trail','shield']];
  if(R2){SIDE.pusat.push('Menyusul (R2)',['Cek Stok Lintas Toko','search','r2'],['Audit Trail','shield','r2']);SIDE.store=SIDE.store.concat(R2STORE)}
  $$('.sb[data-side]').forEach(function(sb){
    var kind=sb.dataset.side, on=sb.dataset.on, who=(sb.dataset.who||'|').split('|');
    var h='<div class="brand"><div class="logo">'+(kind==='pusat'?'P':'MU')+'</div><div><b>'+(kind==='pusat'?'Mitra Manager':'Mitra Utama')+'</b><small>'+(kind==='pusat'?'Pusat':'Toko')+'</small></div></div>';
    SIDE[kind].forEach(function(e){
      if(typeof e==='string'){h+='<div class="g">'+e+'</div>';return}
      h+='<div class="it'+(e[0]===on?' on':'')+(e[2]&&!R2?' dim':'')+'">'+ic(e[1])+e[0]+(e[2]&&!R2?'<span class="rel r2">R2</span>':'')+'</div>';
    });
    h+='<div class="me"><div class="av">'+who[0].charAt(0)+'</div><div><b>'+who[0]+'</b><small>'+who[1]+'</small></div></div>';
    sb.innerHTML=h;
  });
  $$('.shell .mn, .login .fm').forEach(function(m){m.insertAdjacentHTML('beforeend','<div class="ft">© 2026 Alief Praditya</div>')});

  var idx=$('#idx'), scr=$$('.scr'), lastGroup='', n=0;
  scr.forEach(function(s){
    var d=s.dataset;
    var head=document.createElement('div');head.className='sh';
    head.innerHTML='<div><h2>'+d.title+'</h2><p>'+d.sub+'</p></div><span class="rel '+d.rel+'">'+(d.rel==='r1'?'R1':'R2')+'</span>';
    s.insertBefore(head,s.firstChild);
    var bd=$('.bd',s), win=document.createElement('div');win.className='win';
    win.innerHTML='<div class="wb"><i></i><i></i><i></i><span class="url">'+d.url+'</span></div>';
    bd.parentNode.insertBefore(win,bd);win.appendChild(bd);
    if(d.group!==lastGroup){var g=document.createElement('div');g.className='grp';g.textContent=d.group;idx.appendChild(g);lastGroup=d.group}
    n++;
    var b=document.createElement('button');b.dataset.id=s.id;
    b.innerHTML='<span class="n">'+n+'</span>'+d.nav+(d.rel==='r2'?'<span class="rel r2">R2</span>':'');
    b.onclick=function(){show(s.id)};
    idx.appendChild(b);
  });

  if(WF.other){var o=document.createElement('a');o.className='xlink';o.href=WF.other[0];o.textContent=WF.other[1];idx.appendChild(o)}
  function show(id){
    if(!document.getElementById(id))id=scr[0].id;
    scr.forEach(function(s){s.classList.toggle('on',s.id===id)});
    $$('.idx button').forEach(function(b){b.classList.toggle('on',b.dataset.id===id)});
    if(location.hash!=='#'+id)history.replaceState(null,'','#'+id);
    window.scrollTo(0,0);
  }
  window.addEventListener('keydown',function(e){
    if(e.metaKey||e.ctrlKey||e.altKey)return;
    var i=scr.findIndex(function(s){return s.classList.contains('on')});
    if(e.key==='ArrowRight'&&i<scr.length-1)show(scr[i+1].id);
    if(e.key==='ArrowLeft'&&i>0)show(scr[i-1].id);
  });
  window.addEventListener('hashchange',function(){show(location.hash.slice(1))});
  function openNotes(){$$('details.nt').forEach(function(d){d.open=true})}
  window.addEventListener('beforeprint',openNotes);
  if(/[?&]print\b/.test(location.search))openNotes();
  show(location.hash.slice(1));
})();
