
const DATA=window.DG_DATA;
const FT=0.3048;
const $=s=>document.querySelector(s);
const nf=(v,d=0)=>(v==null||isNaN(v))?'–':v.toLocaleString('cs-CZ',{minimumFractionDigits:d,maximumFractionDigits:d});
const sg=v=>v==null?'–':v>0?'+'+nf(v,Number.isInteger(v)?0:1):v<0?'−'+nf(-v,Number.isInteger(v)?0:1):'E';
const pc=a=>a&&a[1]?a[0]/a[1]*100:null;
const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

/* metriky: hi = 1 vyšší je lepší, -1 nižší je lepší, 0 neutrální */
const METRICS=[
 {k:'r',abbr:'Rating',name:'Rating kola',type:'val',hi:1,fmt:v=>nf(v),grp:'Výkon',
  desc:'Číslo, kterým PDGA hodnotí kvalitu jednoho kola. Počítá se ze skóre a z obtížnosti hřiště v daný den (podle výsledků hráčů s ratingem), takže jde férově porovnávat kola na různých hřištích. Tvůj oficiální rating je průměr z kol za posledních ~12 měsíců a přepočítává se jednou měsíčně.',
  how:'Čím víc, tím líp. Orientačně jeden hod rozdílu ≈ 8–10 bodů ratingu, podle hřiště.'},
 {k:'s',abbr:'±Par',name:'Skóre vůči paru (na kolo)',type:'val',hi:-1,fmt:sg,grp:'Výkon',
  desc:'Počet hodů mínus par kola. −8 znamená osm hodů pod parem. Mezi hřišti s různým parem a obtížností se nedá přímo srovnávat – na to je rating.',
  how:'Čím nižší (zápornější), tím líp.'},
 {k:'fw',abbr:'Fairway',name:'Fairway',type:'ratio',hi:1,fmt:v=>nf(v)+' %',grp:'Odhozy',
  desc:'Fairway hits: podíl odhozů z týčka, které skončily na fairwayi (v dobré hratelné pozici, ne v roughu ani mimo hřiště). V PDGA Live bývá jmenovatel vyšší než počet jamek, protože se počítají všechny relevantní odhozy.',
  how:'Čím víc, tím líp. Nízké číslo na dlouhém hřišti obvykle znamená i víc OB.'},
 {k:'ob',abbr:'OB',name:'OB na kolo',type:'val',hi:-1,fmt:v=>nf(v,Number.isInteger(v)?0:1),grp:'Odhozy',
  desc:'Out of Bounds: počet hodů, které skončily mimo hřiště (voda, plot, zakázaná zóna) a stály trestný hod. Každé OB = +1 hod navíc a ztráta lepší pozice.',
  how:'Čím míň, tím líp. Nejdražší chyba: 1 OB ≈ 1 hod ≈ 8–10 bodů ratingu.'},
 {k:'c1r',abbr:'C1R',name:'C1R – green v regulaci',type:'ratio',hi:1,fmt:v=>nf(v)+' %',grp:'Přistání',
  desc:'Circle 1 in Regulation: podíl jamek, kde jsi dohodil do 10 m od koše (kruh 1) v regulačním počtu hodů, tedy nejpozději na par−2. Máš tak putt na birdie. Měří kvalitu odhozů a přiblížení, bez puttingu.',
  how:'Čím víc, tím líp. U tebe největší rezerva: je to ukazatel, který se nejvíc vrací u nejlepších kol.'},
 {k:'c2r',abbr:'C2R',name:'C2R – kruh 2 v regulaci',type:'ratio',hi:1,fmt:v=>nf(v)+' %',grp:'Přistání',
  desc:'Circle 2 in Regulation: totéž co C1R, ale do 20 m od koše v regulaci. Zahrnuje i C1R, takže rozdíl C2R − C1R ukazuje, jak často skončíš v pásmu 10–20 m.',
  how:'Čím víc, tím líp. Velká mezera mezi C2R a C1R = přistáváš blízko, ale ne dost blízko.'},
 {k:'sc',abbr:'Scramble',name:'Scramble (záchrana)',type:'ratio',hi:1,fmt:v=>nf(v)+' %',grp:'Přistání',
  desc:'Podíl jamek, kde odhoz minul fairway, a přesto jsi dal par nebo lépe. Ukazuje, jak umíš zachraňovat chyby z tee. Bývá z malého počtu jamek, takže skáče.',
  how:'Čím víc, tím líp. Ber ho s rezervou – v jednom kole jde často jen o 2–8 jamek.'},
 {k:'c1x',abbr:'C1X',name:'C1X putting (3–10 m)',type:'ratio',hi:1,fmt:v=>nf(v)+' %',grp:'Putting',
  desc:'Úspěšnost puttů z kruhu 1 bez nejkratších: z 3,3 až 10 m (11–33 stop). Putty do 3,3 m (tap-iny) se nepočítají, takže číslo je přísnější a lépe odráží skutečnou puttingovou formu.',
  how:'Čím víc, tím líp. Cíl je držet se stabilně nad 70 %.'},
 {k:'c2',abbr:'C2 putting',name:'C2 putting (10–20 m)',type:'ratio',hi:1,fmt:v=>nf(v)+' %',grp:'Putting',
  desc:'Úspěšnost puttů z kruhu 2: z 10 až 20 m (33–66 stop). Běžně nízké číslo – i pro špičku orientačně zhruba jeden z pěti.',
  how:'Čím víc, tím líp, ale nízké hodnoty jsou normální; důležitější je C1X a C1R.'},
 {k:'ap',abbr:'Ø putt',name:'Průměrná délka puttu',type:'val',hi:0,fmt:v=>nf(v,1)+' m',grp:'Putting',
  desc:'Průměrná vzdálenost puttů v kole. Není to „dobrý/špatný“ ukazatel: říká, z jak daleka jsi do koše pokoušel. PDGA Live ji dává ve stopách, tady je přepočtená na metry.',
  how:'Neutrální. Vyšší číslo = víc dlouhých puttů (obvykle po horších přiblíženích).'},
 {k:'lp',abbr:'Max putt',name:'Nejdelší proputtovaný',type:'val',hi:0,fmt:v=>nf(v)+' m',grp:'Putting',
  desc:'Nejdelší putt, který v kole spadl do koše. Zajímavost spíš než ukazatel formy.',
  how:'Neutrální.'},
];
const GLOSS=[
 ['Par','Počet hodů, které by na jamku (nebo celé hřiště) měl udělat zkušený hráč. Skóre −8 = osm hodů pod parem hřiště.'],
 ['Rating kola × rating turnaje','Rating kola je výkon v jednom kole. Rating turnaje je průměr tvých kol v turnaji. „Můj rating tehdy“ je oficiální PDGA rating v den turnaje.'],
 ['Regulace (par−2)','Na jamce par 3 máš v regulaci 1 hod, na par 4 dva hody a na par 5 tři hody. Pak je disk v kruhu a zbývá putt na birdie. Podle toho se počítá C1R a C2R.'],
 ['Kruh 1 / kruh 2','Kruh 1 (C1) = do 10 m od koše, kruh 2 (C2) = 10–20 m od koše.'],
 ['Tap-in','Velmi krátký putt (do 3,3 m). Do C1X se nepočítá.'],
 ['MP40 / MPO','Divize: MP40 = muži 40+ (masters), MPO = otevřená mužská kategorie bez omezení věku, v níž hrají i mladí hráči.'],
 ['ČADG kat. 1–3','Kategorie turnaje v české sérii; vyšší číslo = významnější turnaj (např. MČR a 1. liga mají kat. 3).'],
 ['Vztah k ratingu (r)','Korelace od −1 do +1. Čím blíž k 1 (nebo −1), tím víc se statistika pohybuje společně s ratingem kola. Neříká, co je příčina; hřiště a počasí do toho taky mluví.'],
 ['Barvy v tabulkách','Zelená/červená = hodnota je výrazně (víc než o 0,6 směrodatné odchylky) lepší/horší než tvůj průměr z ostatních kol (12 měsíců před turnajem; když jich je málo, ze všech ostatních turnajů; když není dost kol, barvy se nezobrazují). Statistiky z méně než 3 pokusů se nebarví.'],
];
const MK=Object.fromEntries(METRICS.map(m=>[m.k,m]));
function val(m,rd){
  if(m.type==='ratio')return pc(rd[m.k]);
  if(m.k==='ap'||m.k==='lp'){const v=rd[m.k];return v==null?null:(rd.ev.unit==='ft'?v*FT:v)}
  const v=rd[m.k];return v==null?null:v;
}
function agg(m,rds){
  if(m.type==='ratio'){let a=0,b=0;rds.forEach(r=>{const x=r[m.k];if(x){a+=x[0];b+=x[1]}});return b?a/b*100:null}
  const v=rds.map(r=>val(m,r)).filter(x=>x!=null);return v.length?v.reduce((a,b)=>a+b,0)/v.length:null;
}
const mean=a=>a.length?a.reduce((x,y)=>x+y,0)/a.length:null;
const sd=a=>{const m=mean(a);return Math.sqrt(mean(a.map(x=>(x-m)**2)))};
function corr(xs,ys){const n=xs.length;if(n<4)return null;const mx=mean(xs),my=mean(ys);let a=0,b=0,c=0;for(let i=0;i<n;i++){a+=(xs[i]-mx)*(ys[i]-my);b+=(xs[i]-mx)**2;c+=(ys[i]-my)**2}return b&&c?a/Math.sqrt(b*c):null}

/* stav */
let S={year:null,tab:'prehled',ev:null,metric:'c1r',scope:'pdga',sel:null};
try{const x=JSON.parse(localStorage.getItem('dg-stats-state')||'{}');if(x.year)S.year=x.year;if(x.scope)S.scope=x.scope}catch(e){}
const years=Object.keys(DATA.years).sort();
if(!DATA.years[S.year])S.year=years.includes('2026')?'2026':years[years.length-1];
const save=()=>{try{localStorage.setItem('dg-stats-state',JSON.stringify({year:S.year,scope:S.scope}))}catch(e){}};

function events(){return DATA.years[S.year].events.slice().sort((a,b)=>a.order-b.order)}
function rounds(scope=S.scope){
  const out=[];events().forEach(e=>{if(scope==='pdga'&&!e.pdga)return;e.rounds.forEach((r,i)=>{r.ev=e;r.i=i;out.push(r)})});return out}
const isWin=e=>parseInt(String(e.place).replace(/\D/g,''))===1;
const podium=e=>parseInt(String(e.place).replace(/\D/g,''))<=3;
function heat(m,v,all){ // vrací 'g' | 'b' | ''
  if(v==null||m.hi===0)return '';
  const arr=all.map(r=>val(m,r)).filter(x=>x!=null);const s=sd(arr);if(!s)return '';
  const z=(v-mean(arr))/s*m.hi;return z>0.6?'g':z<-0.6?'b':'';
}
/* Srovnání pro barvy: tvoje ostatní kola z 12 měsíců před turnajem (min. 8 kol), jinak všechna ostatní kola; málo dat = bez barev. */
function baselineFor(e){
  const MIN=8, ms=e.date?new Date(e.date).getTime():null, win=365*864e5, near=[], other=[];
  Object.values(DATA.years).forEach(y=>y.events.forEach(ev=>{
    if(ev===e||(e.pdga&&!ev.pdga))return;
    ev.rounds.forEach((r,i)=>{r.ev=ev;r.i=i;other.push(r);const t=ev.date?new Date(ev.date).getTime():null;if(ms!=null&&t!=null&&t<=ms&&ms-t<=win)near.push(r)})}));
  if(near.length>=MIN)return {rds:near,label:`tvůj průměr za 12 měsíců před turnajem (${near.length} kol)`};
  if(other.length>=MIN)return {rds:other,label:`tvůj průměr ze všech ostatních turnajů (${other.length} kol)`};
  return {rds:null,label:`zatím málo kol pro srovnání (potřeba aspoň ${MIN} jiných kol)`};
}
function colorFor(m,v,avg){if(m.hi===0||v==null)return 'var(--neutral)';const d=(v-avg)*m.hi;return d>=0?'var(--good)':'var(--bad)'}

/* ---------- grafy ---------- */
function lolli(items,avg,m){
  const n=items.length,step=32,padL=46,padR=14,padT=20,padB=62,H=270,W=padL+padR+n*step;
  const vs=items.map(i=>i.v).filter(v=>v!=null).concat([avg]);
  let lo=Math.min(...vs),hi=Math.max(...vs);const sp=(hi-lo)||1;lo-=sp*.1;hi+=sp*.1;
  if(m.type==='ratio'||m.k==='ob'){lo=Math.max(0,lo)}
  const y=v=>padT+(H-padT-padB)*(1-(v-lo)/(hi-lo));
  let g='';
  for(let t=0;t<=4;t++){const v=lo+(hi-lo)*t/4;g+=`<line x1="${padL}" x2="${W-padR}" y1="${y(v)}" y2="${y(v)}" stroke="var(--line)"/><text x="${padL-6}" y="${y(v)+4}" text-anchor="end" font-size="10.5" fill="var(--mute)">${m.k==='s'?sg(Math.round(v)):nf(v,m.k==='ob'||m.k==='ap'?1:0)}</text>`}
  g+=`<line x1="${padL}" x2="${W-padR}" y1="${y(avg)}" y2="${y(avg)}" stroke="var(--fg)" stroke-dasharray="4 3"/><text x="${W-padR}" y="${y(avg)-4}" text-anchor="end" font-size="10.5" fill="var(--fg)">průměr ${m.fmt(avg)}</text>`;
  if(m.k==='r'&&DATA.player.goal&&DATA.player.goal>lo&&DATA.player.goal<hi)g+=`<line x1="${padL}" x2="${W-padR}" y1="${y(DATA.player.goal)}" y2="${y(DATA.player.goal)}" stroke="var(--accent)" stroke-dasharray="2 4"/><text x="${padL+2}" y="${y(DATA.player.goal)-4}" font-size="10.5" fill="var(--accent)">cíl ${DATA.player.goal}</text>`;
  let prev=null,gi=0,gx=[];
  items.forEach((it,idx)=>{
    const x=padL+step*idx+step/2;
    if(it.ev.id!==prev){gx.push({ev:it.ev,x0:x,x1:x});prev=it.ev.id;if(idx>0){g+=`<line x1="${x-step/2}" x2="${x-step/2}" y1="${padT}" y2="${H-padB+8}" stroke="var(--line)" stroke-dasharray="2 3"/>`}}else gx[gx.length-1].x1=x;
    if(it.v==null)return;
    const c=colorFor(m,it.v,avg),sel=S.sel&&S.sel.ev===it.ev.id&&S.sel.i===it.i;
    g+=`<g class="pt" data-ev="${it.ev.id}" data-i="${it.i}" style="cursor:pointer">
     <line x1="${x}" x2="${x}" y1="${y(avg)}" y2="${y(it.v)}" stroke="${c}" stroke-width="3" stroke-linecap="round"/>
     <circle cx="${x}" cy="${y(it.v)}" r="${sel?9:6.5}" fill="${it.ev.pdga?c:'var(--card)'}" stroke="${sel?'var(--fg)':c}" stroke-width="${it.ev.pdga?(sel?2.5:0):2.5}"/>
     <rect x="${x-step/2}" y="${padT}" width="${step}" height="${H-padT-padB}" fill="transparent"/></g>
     <text x="${x}" y="${H-padB+20}" text-anchor="middle" font-size="10" fill="var(--mute)">${it.i+1}</text>`;
  });
  gx.forEach((q,k)=>{const cx=(q.x0+q.x1)/2;g+=`<text x="${cx}" y="${H-padB+(k%2?46:34)}" text-anchor="middle" font-size="10.5" font-weight="600" fill="var(--fg)">${esc(q.ev.short)}</text>`});
  return `<div class="scroll"><svg width="${Math.max(W,340)}" height="${H}" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(m.name)} podle kol">${g}</svg></div>`;
}
function scatter(pts,m,ry){
  const W=360,H=250,pl=44,pr=14,pt=14,pb=34;
  const xs=pts.map(p=>p.x),ys=pts.map(p=>p.y);
  let x0=Math.min(...xs),x1=Math.max(...xs),y0=Math.min(...ys),y1=Math.max(...ys);
  const px=(x1-x0)*.08||1,py=(y1-y0)*.08||1;x0-=px;x1+=px;y0-=py;y1+=py;
  const X=v=>pl+(W-pl-pr)*(v-x0)/(x1-x0),Y=v=>pt+(H-pt-pb)*(1-(v-y0)/(y1-y0));
  let g='';
  for(let t=0;t<=4;t++){const v=y0+(y1-y0)*t/4;g+=`<line x1="${pl}" x2="${W-pr}" y1="${Y(v)}" y2="${Y(v)}" stroke="var(--line)"/><text x="${pl-6}" y="${Y(v)+4}" text-anchor="end" font-size="10.5" fill="var(--mute)">${nf(v)}</text>`;
    const u=x0+(x1-x0)*t/4;g+=`<text x="${X(u)}" y="${H-pb+16}" text-anchor="middle" font-size="10.5" fill="var(--mute)">${m.k==='s'?sg(Math.round(u)):nf(u,m.k==='ob'||m.k==='ap'?1:0)}</text>`}
  const mx=mean(xs),my=mean(ys);let a=0,b=0;pts.forEach(p=>{a+=(p.x-mx)*(p.y-my);b+=(p.x-mx)**2});
  if(b){const sl=a/b,ic=my-sl*mx;g+=`<line x1="${X(x0)}" y1="${Y(ic+sl*x0)}" x2="${X(x1)}" y2="${Y(ic+sl*x1)}" stroke="var(--accent)" stroke-width="2" stroke-dasharray="5 4"/>`}
  pts.forEach(p=>g+=`<circle class="pt" data-ev="${p.ev.id}" data-i="${p.i}" cx="${X(p.x)}" cy="${Y(p.y)}" r="5.5" fill="var(--forest)" fill-opacity=".75" style="cursor:pointer"/>`);
  g+=`<text x="${W/2}" y="${H-4}" text-anchor="middle" font-size="11" fill="var(--mute)">${esc(m.name)} →</text>`;
  return `<svg viewBox="0 0 ${W} ${H}" width="100%" role="img" aria-label="Závislost ratingu kola na ${esc(m.name)}">${g}</svg>`;
}

/* ---------- pohledy ---------- */
function viewOverview(){
  const evs=events().filter(e=>e.pdga),all=events(),rds=rounds('pdga');
  const rat=rds.map(r=>r.r).filter(x=>x!=null);
  const best=rds.reduce((a,b)=>(b.r>(a?a.r:0)?b:a),null);
  const thr=rds.reduce((a,r)=>a+r.s,0);
  const kp=(b,s,h)=>`<div class="kpi${h?' hl':''}"><b>${b}</b><span>${s}</span></div>`;
  let h=`<div class="kpis">
    ${kp(DATA.player.rating,`aktuální PDGA rating · cíl ${DATA.player.goal}+`,1)}
    ${kp(all.length,`turnajů (${all.length-evs.length?evs.length+' PDGA':'všechny PDGA'}) · ${rds.length} kol PDGA`)}
    ${kp(all.filter(isWin).length+' / '+all.filter(podium).length,'vítězství / pódií')}
    ${kp(nf(mean(rat)),'průměrný rating kola')}
    ${kp(best.r,`nejlepší kolo · ${esc(best.ev.short)} K${best.i+1}`)}
    ${kp(sg(thr),'součet vůči paru (PDGA kola)')}
  </div>`;
  h+=ratingCard();
  const m=MK.r,items=rds.map(r=>({v:r.r,ev:r.ev,i:r.i}));
  h+=`<section class="card"><h2>Rating každého kola</h2><p class="sub" style="margin:0 0 6px">Čárkovaná čára = průměr sezóny. Sloupek jde od průměru nahoru (lepší) nebo dolů (horší). Klepni na bod pro detail.</p>${lolli(items,mean(rat),m)}<div class="cap" id="cap"></div></section>`;
  h+=`<section class="card"><h2>Sezóna v číslech</h2><div class="scroll"><table><thead><tr><th>Statistika</th><th>Sezóna</th><th>Nejlepší kolo</th><th>Nejhorší kolo</th></tr></thead><tbody>`;
  METRICS.filter(m=>m.k!=='r').forEach(m=>{
    const a=agg(m,rds);const withV=rds.map(r=>({r,v:val(m,r)})).filter(x=>x.v!=null);
    let bst='–',wst='–';
    if(withV.length&&m.hi!==0){const s=withV.slice().sort((x,y)=>(y.v-x.v)*m.hi);bst=`${m.fmt(s[0].v)}<small>${esc(s[0].r.ev.short)} K${s[0].r.i+1}</small>`;wst=`${m.fmt(s[s.length-1].v)}<small>${esc(s[s.length-1].r.ev.short)} K${s[s.length-1].r.i+1}</small>`}
    h+=`<tr><td>${esc(m.name)}</td><td><b>${m.fmt(a)}</b></td><td>${bst}</td><td>${wst}</td></tr>`;
  });
  h+=`</tbody></table></div><p class="sub" style="margin:10px 0 0">Procenta jsou vážená (součet zásahů ÷ součet pokusů za všechna kola), ne průměr procent.</p></section>`;
  return h;
}

function viewEvents(){
  if(S.ev)return viewDetail();
  const evs=events();
  const rds=rounds('pdga'),avgR=mean(rds.map(r=>r.r));
  let h=`<div class="list">`;
  evs.forEach(e=>{
    const pills=e.rounds.map((r,i)=>{
      if(r.r!=null){const d=r.r-avgR;return `<span class="pill ${d>8?'g':d<-8?'b':''}">K${i+1} · ${r.r}</span>`}
      return `<span class="pill">K${i+1} · ${sg(r.s)}</span>`});
    h+=`<button class="ev" data-open="${e.id}"><div class="row"><strong>${esc(e.name)}</strong><span class="place${isWin(e)?' win':''}">${esc(e.place)}</span></div>
      <div class="sub">${fmtD(e.date)} · ${esc(e.division)} · ${e.rounds.length} kol${e.pdga?'':' · neoficiální'} · celkem <b style="color:var(--fg)">${sg(e.total)}</b>${e.eventRating?' · rating turnaje '+e.eventRating:''}</div>
      <div class="pills">${pills.join('')}</div></button>`;
  });
  return h+`</div>`;
}
function viewDetail(){
  const evs=events(),idx=evs.findIndex(e=>e.id===S.ev),e=evs[idx];
  const bl=baselineFor(e),base=bl.rds;
  const rowsDef=METRICS.filter(m=>m.k!=='s');
  const dist=e.dist?`${nf(e.dist*(e.unit==='ft'?FT:1))} m`:null;
  let h=`<div class="nav2"><button class="back" data-back>← Všechny turnaje</button></div>
  <section class="card"><div class="row" style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;flex-wrap:wrap"><h2 style="margin:0">${esc(e.name)}</h2><span class="place${isWin(e)?' win':''}">${esc(e.place)} místo</span></div>
  <div class="facts"><span>Datum <b>${fmtD(e.date)}</b></span>${e.venue?`<span>${esc(e.venue)}</span>`:''}${e.cat?`<span>ČADG kat. <b>${e.cat}</b></span>`:''}<span>Divize <b>${esc(e.division)}</b></span><span>Celkem <b>${sg(e.total)}</b></span>${e.eventRating?`<span>Rating turnaje <b>${e.eventRating}</b></span>`:''}${e.pdgaRating?`<span>Můj rating tehdy <b>${e.pdgaRating}</b></span>`:''}<span>Par <b>${e.par}</b> × ${e.rounds.length}</span><span>${e.holes} jamek</span>${dist?`<span>Délka <b>${dist}</b></span>`:''}</div>
  ${e.note?`<div class="note">${esc(e.note)}</div>`:''}</section>
  <section class="card"><h3 style="margin-bottom:4px">Kolo po kole</h3><p class="sub" style="margin:0 0 8px">Barva = výrazně lepší / horší než ${esc(bl.label)}.</p><div class="scroll"><table><thead><tr><th>Statistika</th>${e.rounds.map((r,i)=>`<th>K${i+1}${r.day&&r.day!=='jednodenní'?`<small>${r.day}</small>`:''}</th>`).join('')}<th>Celkem</th></tr></thead><tbody>`;
  h+=`<tr><td>Skóre vůči paru</td>${e.rounds.map(r=>`<td><b>${sg(r.s)}</b><small>${r.t} hodů</small></td>`).join('')}<td><b>${sg(e.total)}</b><small>${e.rounds.reduce((a,r)=>a+r.t,0)} hodů</small></td></tr>`;
  rowsDef.forEach(m=>{
    h+=`<tr><td>${esc(m.name)}</td>`;
    e.rounds.forEach(r=>{r.ev=e;const v=val(m,r);const c=base&&!(m.type==='ratio'&&r[m.k]&&r[m.k][1]<3)?heat(m,v,base):'';
      const frac=m.type==='ratio'&&r[m.k]?`<small>${r[m.k][0]}/${r[m.k][1]}</small>`:'';
      h+=`<td class="${c}">${v==null?'–':m.fmt(v)}${frac}</td>`});
    let tot;
    if(m.k==='r')tot=e.eventRating;
    else if(m.k==='ob')tot=e.rounds.reduce((a,r)=>a+(r.ob||0),0);
    else if(m.k==='lp'){const a=e.rounds.map(r=>val(m,r)).filter(x=>x!=null);tot=a.length?Math.max(...a):null}
    else tot=agg(m,e.rounds);
    h+=`<td><b>${tot==null?'–':m.fmt(tot)}</b></td></tr>`;
  });
  h+=`</tbody></table></div><div class="legend"><span><i class="dot" style="background:var(--good)"></i>výrazně lepší než srovnávací průměr</span><span><i class="dot" style="background:var(--bad)"></i>výrazně horší</span></div></section>`;
  h+=`<div class="nav2">${idx>0?`<button class="back" data-open="${evs[idx-1].id}">← ${esc(evs[idx-1].short)}</button>`:'<span></span>'}${idx<evs.length-1?`<button class="back" data-open="${evs[idx+1].id}">${esc(evs[idx+1].short)} →</button>`:''}</div>`;
  return h;
}

function viewParams(){
  const m=MK[S.metric],rds=rounds();
  const items=rds.map(r=>({v:val(m,r),ev:r.ev,i:r.i})).filter(x=>x.v!=null);
  const avg=agg(m,rds);
  let h=`<section class="card"><h3>Vyber statistiku</h3><div class="chips">${METRICS.map(x=>`<button class="chip" data-metric="${x.k}" aria-pressed="${x.k===S.metric}">${esc(x.name)}</button>`).join('')}</div>
   <div style="margin-top:12px" class="seg" role="group" aria-label="Rozsah"><button data-scope="pdga" aria-pressed="${S.scope==='pdga'}">Jen PDGA turnaje</button><button data-scope="all" aria-pressed="${S.scope==='all'}">Včetně domácího</button></div>
   ${S.scope==='all'?'<p class="sub" style="margin:8px 0 0">Domácí turnaj má 15 krátkých jamek par 3 – čísla nejsou přímo srovnatelná (prázdné body v grafu).</p>':''}</section>`;
  if(!items.length)return h+`<div class="card empty">Pro tuto statistiku nejsou v ${S.year} data.</div>`;
  h+=`<section class="card"><h2>${esc(m.name)} – každé kolo</h2><p class="sub" style="margin:-4px 0 4px">${esc(m.desc)}</p><p style="margin:0 0 8px;font-size:14px"><b>Jak číst:</b> ${esc(m.how)}</p>${lolli(items,avg,m)}<div class="cap" id="cap"></div></section>`;
  // podle turnaje
  const evs=events().filter(e=>S.scope==='all'||e.pdga);
  const rowsE=evs.map(e=>({e,v:agg(m,e.rounds.map(r=>(r.ev=e,r)))})).filter(x=>x.v!=null);
  const mx=Math.max(...rowsE.map(x=>x.v)),mn=Math.min(...rowsE.map(x=>x.v));
  h+=`<div class="two"><section class="card"><h3>Podle turnaje</h3><div class="scroll"><table><tbody>`;
  rowsE.slice().sort((a,b)=>m.hi===0?0:(b.v-a.v)*m.hi).forEach(x=>{
    const w=mx===mn?100:30+70*((x.v-mn)/(mx-mn));
    h+=`<tr><td><button class="back" style="color:var(--fg);font-weight:500" data-open="${x.e.id}">${esc(x.e.short)}</button></td><td style="width:45%"><div class="bar"><i style="width:${w}%;background:${colorFor(m,x.v,avg)}"></i></div></td><td><b>${m.fmt(x.v)}</b></td></tr>`});
  h+=`</tbody></table></div></section>`;
  // vztah k ratingu
  if(m.k!=='r'){
    const pts=rds.map(r=>({x:val(m,r),y:r.r,ev:r.ev,i:r.i})).filter(p=>p.x!=null&&p.y!=null);
    const c=corr(pts.map(p=>p.x),pts.map(p=>p.y));
    h+=`<section class="card"><h3>Vztah k ratingu kola</h3>${pts.length>3?scatter(pts,m):'<p class="sub">Málo dat.</p>'}<p class="sub" style="margin:6px 0 0">${c==null?'':`Korelace <b style="color:var(--fg)">${nf(c,2)}</b> (${pts.length} kol). ${Math.abs(c)>=.5?'Silná souvislost':Math.abs(c)>=.3?'Střední souvislost':'Slabá souvislost'} s ratingem kola; obtížnost hřiště do toho ale taky mluví.`}</p></section>`;
  }
  h+=`</div>`;
  return h;
}

function viewWeak(){
  const rds=rounds('pdga').filter(r=>r.r!=null);
  if(rds.length<6)return `<div class="card empty"><b>Zatím málo kol</b>Rozbor se zapne, jakmile bude aspoň 6 kol s ratingem.</div>`;
  const sorted=rds.slice().sort((a,b)=>b.r-a.r),k=Math.max(2,Math.round(rds.length/3));
  const top=sorted.slice(0,k),bot=sorted.slice(-k);
  const rows=METRICS.filter(m=>m.k!=='r'&&m.k!=='s').map(m=>{
    const pts=rds.map(r=>({x:val(m,r),y:r.r})).filter(p=>p.x!=null);
    return {m,c:corr(pts.map(p=>p.x),pts.map(p=>p.y)),t:agg(m,top),b:agg(m,bot),all:agg(m,rds)};
  }).filter(x=>x.c!=null).sort((a,b)=>Math.abs(b.c)-Math.abs(a.c));
  let h=`<section class="card"><h2>Co dělí dobrá kola od špatných</h2><p class="sub" style="margin:0 0 8px">Nejlepší třetina kol (${k}) proti nejhorší třetině (${k}) podle ratingu. Řazeno podle síly vztahu k ratingu.</p><div class="scroll"><table><thead><tr><th>Statistika</th><th>Nejlepší</th><th>Nejhorší</th><th>Vztah</th></tr></thead><tbody>`;
  rows.forEach(x=>{
    const w=Math.abs(x.c)*100;
    h+=`<tr><td>${esc(x.m.name)}</td><td>${x.m.fmt(x.t)}</td><td>${x.m.fmt(x.b)}</td><td style="min-width:110px"><div class="bar"><i style="width:${w}%;background:${x.c*(x.m.hi||1)>0?'var(--forest)':'var(--bad)'}"></i></div><small style="color:var(--mute)">r = ${nf(x.c,2)}</small></td></tr>`});
  h+=`</tbody></table></div></section>`;
  // nejhorší kola a jejich viník
  h+=`<section class="card"><h2>Nejhorší kola a co je stáhlo</h2><div class="list">`;
  const zs=METRICS.filter(m=>m.hi!==0&&m.k!=='r'&&m.k!=='s').map(m=>{const a=rds.map(r=>val(m,r)).filter(v=>v!=null);return {m,mu:mean(a),sd:sd(a)}});
  sorted.slice(-5).reverse().forEach(r=>{
    const bad=zs.map(z=>{const v=val(z.m,r);return v==null||!z.sd?null:{m:z.m,v,z:(v-z.mu)/z.sd*z.m.hi}}).filter(Boolean).sort((a,b)=>a.z-b.z).slice(0,3);
    h+=`<button class="ev" data-open="${r.ev.id}"><div class="row"><strong>${esc(r.ev.short)} · kolo ${r.i+1}</strong><span class="pill b">${r.r}</span></div><div class="sub">${sg(r.s)} · nejvíc pod průměrem: ${bad.map(b=>`${esc(b.m.name.split(' – ')[0].split(' (')[0])} <b style="color:var(--fg)">${b.m.fmt(b.v)}</b>`).join(', ')}</div></button>`});
  h+=`</div></section>`;
  // slabá místa sezóny
  const me=METRICS.filter(m=>m.hi!==0&&m.type==='ratio').map(m=>({m,a:agg(m,rds)}));
  h+=`<section class="card"><h2>Úspěšnost v sezóně</h2>${me.map(x=>`<div style="margin:8px 0"><div style="display:flex;justify-content:space-between"><span>${esc(x.m.name)}</span><b>${x.m.fmt(x.a)}</b></div><div class="bar"><i style="width:${x.a}%"></i></div></div>`).join('')}</section>`;
  const man=DATA.years[S.year].insights||[];
  if(man.length)h+=`<section class="card"><h2>Poznámky</h2><ul>${man.map(t=>`<li>${esc(t)}</li>`).join('')}</ul></section>`;
  h+=`<p class="sub">Pozor: ${rds.length} kol je malý vzorek a rating kola závisí i na hřišti a počasí. Berte to jako směr, ne jako důkaz.</p>`;
  return h;
}


function ratingCard(){
  const H=DATA.ratingHistory.map(([d,r,n])=>({t:Date.parse(d),r,n,y:d.slice(0,4),d}));
  if(!H.length)return '';
  const W=360,Ht=210,pl=36,pr=10,pt=14,pb=26;
  const t0=H[0].t,t1=H[H.length-1].t,goal=DATA.player.goal;
  const lo=Math.floor(Math.min(...H.map(p=>p.r))/10)*10-5,hi=Math.max(goal,...H.map(p=>p.r))+8;
  const X=t=>pl+(W-pl-pr)*(t-t0)/(t1-t0),Y=v=>pt+(Ht-pt-pb)*(1-(v-lo)/(hi-lo));
  let g='';
  for(let v=Math.ceil(lo/20)*20;v<=hi;v+=20)g+=`<line x1="${pl}" x2="${W-pr}" y1="${Y(v)}" y2="${Y(v)}" stroke="var(--line)"/><text x="${pl-5}" y="${Y(v)+4}" text-anchor="end" font-size="10.5" fill="var(--mute)">${v}</text>`;
  const ys=[...new Set(H.map(p=>p.y))];
  ys.forEach(y=>{const x=X(Date.parse(y+'-01-01'));if(x>pl)g+=`<line x1="${x}" x2="${x}" y1="${pt}" y2="${Ht-pb}" stroke="var(--line)" stroke-dasharray="2 3"/>`;g+=`<text x="${Math.max(x,pl)+3}" y="${Ht-8}" font-size="10.5" fill="var(--mute)">${y}</text>`});
  g+=`<line x1="${pl}" x2="${W-pr}" y1="${Y(goal)}" y2="${Y(goal)}" stroke="var(--accent)" stroke-dasharray="2 4"/><text x="${W-pr}" y="${Y(goal)-4}" text-anchor="end" font-size="10.5" fill="var(--accent)">cíl ${goal}</text>`;
  g+=`<polyline fill="none" stroke="var(--forest)" stroke-width="2" stroke-linejoin="round" points="${H.map(p=>X(p.t)+','+Y(p.r)).join(' ')}"/>`;
  const cur=H.filter(p=>p.y===S.year);
  if(cur.length)g+=`<polyline fill="none" stroke="var(--accent)" stroke-width="3.5" stroke-linejoin="round" points="${cur.map(p=>X(p.t)+','+Y(p.r)).join(' ')}"/>`;
  const L=H[H.length-1];g+=`<circle cx="${X(L.t)}" cy="${Y(L.r)}" r="5" fill="var(--accent)"/><text x="${X(L.t)-8}" y="${Y(L.r)-9}" text-anchor="end" font-size="12" font-weight="700" fill="var(--fg)">${L.r}</text>`;
  const ends=ys.map(y=>{const a=H.filter(p=>p.y===y);return {y,r:a[a.length-1].r,n:a[a.length-1].n}});
  const chips=ends.map((e,i)=>{const dlt=i?e.r-ends[i-1].r:null;return `<span class="pill ${dlt>0?'g':dlt<0?'b':''}">${e.y} · ${e.r}${dlt!=null?` (${dlt>0?'+':dlt<0?'−':'±'}${Math.abs(dlt)})`:''}</span>`}).join('');
  return `<section class="card"><h2>Vývoj PDGA ratingu</h2><p class="sub" style="margin:0 0 6px">Měsíční přepočty od roku 2019, vybraná sezóna ${S.year} je oranžově. Pod grafem rating na konci každého roku a změna proti předchozímu.</p><svg viewBox="0 0 ${W} ${Ht}" width="100%" role="img" aria-label="Vývoj PDGA ratingu">${g}</svg><div class="pills" style="margin-top:8px">${chips}</div></section>`;
}
function viewGlossary(){
  const rds=rounds('pdga');
  let h=`<section class="card"><h2>Co která statistika znamená</h2><p class="sub" style="margin:0">U každé je vedle výkladu tvoje hodnota za sezónu ${S.year}.</p></section>`;
  ['Výkon','Odhozy','Přistání','Putting'].forEach(grp=>{
    h+=`<section class="card"><h3>${grp}</h3><div class="list">`;
    METRICS.filter(m=>m.grp===grp).forEach(m=>{
      const a=rds.length?agg(m,rds):null;
      h+=`<div><div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline"><strong style="font:700 16px var(--font-d)">${esc(m.name)}${m.abbr&&m.abbr!==m.name?`<span class="tag">${esc(m.abbr)}</span>`:''}</strong>${a!=null?`<span class="pill">${m.fmt(a)}</span>`:''}</div><p style="margin:4px 0">${esc(m.desc)}</p><p class="sub" style="margin:0"><b>Jak číst:</b> ${esc(m.how)}</p></div>`});
    h+=`</div></section>`});
  h+=`<section class="card"><h3>Pojmy kolem</h3><div class="list">${GLOSS.map(([a,b])=>`<div><strong>${esc(a)}</strong><p style="margin:2px 0 0">${esc(b)}</p></div>`).join('')}</div></section>`;
  return h;
}
const fmtD=d=>d?new Date(d).toLocaleDateString('cs-CZ',{day:'numeric',month:'numeric',year:'numeric'}).replace(/\s/g,''):'';
/* ---------- vykreslení ---------- */
const TABS=[['prehled','Přehled'],['turnaje','Turnaje'],['parametry','Parametry'],['zlepseni','Kde se zlepšit'],['slovnik','Slovník']];
function render(){
  $('#years').innerHTML=years.map(y=>`<button data-year="${y}" aria-pressed="${y===S.year}">${y}</button>`).join('');
  $('#tabs').innerHTML=TABS.map(([k,l])=>`<button role="tab" data-tab="${k}" aria-selected="${k===S.tab}">${l}</button>`).join('');
  const evs=events();
  $('#sub').textContent=`${DATA.player.name} · PDGA #${DATA.player.pdga} · sezóna ${S.year}`;
  let h;
  if(S.tab==='slovnik')h=viewGlossary();
  else if(!evs.length)h=`<div class="card empty"><b>Sezóna ${S.year} je zatím prázdná</b>Jakmile přibude první turnaj, objeví se tu přehled, grafy i rozbor.</div>`+ratingCard();
  else h={prehled:viewOverview,turnaje:viewEvents,parametry:viewParams,zlepseni:viewWeak}[S.tab]();
  $('#app').innerHTML=h;
  caption();
}
function caption(){
  const c=$('#cap');if(!c)return;
  if(!S.sel){c.innerHTML='<span class="sub">Klepni na bod v grafu.</span>';return}
  const e=events().find(x=>x.id===S.sel.ev),r=e&&e.rounds[S.sel.i];if(!r)return;r.ev=e;
  const m=S.tab==='parametry'?MK[S.metric]:MK.r;const v=val(m,r);
  c.innerHTML=`<span><b>${esc(e.short)} · kolo ${S.sel.i+1}</b> – ${esc(m.name)}: <b>${m.fmt(v)}</b> · ${sg(r.s)} · ${r.t} hodů</span><button data-open="${e.id}">Otevřít turnaj</button>`;
}
document.addEventListener('click',ev=>{
  const t=ev.target.closest('[data-year],[data-tab],[data-open],[data-back],[data-metric],[data-scope],.pt');if(!t)return;
  if(t.dataset.year){S.year=t.dataset.year;S.ev=null;S.sel=null;save()}
  else if(t.dataset.tab){S.tab=t.dataset.tab;S.ev=null;S.sel=null}
  else if(t.dataset.open){S.tab='turnaje';S.ev=t.dataset.open;window.scrollTo(0,0)}
  else if('back' in t.dataset){S.ev=null}
  else if(t.dataset.metric){S.metric=t.dataset.metric;S.sel=null}
  else if(t.dataset.scope){S.scope=t.dataset.scope;S.sel=null;save()}
  else if(t.classList.contains('pt')){const sx=window.scrollX,sy=window.scrollY;const sc=t.closest('.scroll');const l=sc?sc.scrollLeft:0;S.sel={ev:t.dataset.ev,i:+t.dataset.i};render();const n=document.querySelector('.scroll');if(n&&sc)n.scrollLeft=l;window.scrollTo(sx,sy);return}
  render();
});
render();
