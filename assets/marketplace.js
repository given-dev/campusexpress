'use strict';
const catalog = JSON.parse(document.getElementById('catalog').textContent);
const readSaved = (key, fallback) => { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } };
let cart = readSaved('campusexpress-cart-v1', []);
if (!Array.isArray(cart)) cart = [];
cart = cart.filter(item => item && Number.isInteger(item.id) && Number.isInteger(item.quantity) && item.quantity > 0 && item.quantity <= 99 && typeof item.name === 'string' && typeof item.vendor === 'string' && Number.isFinite(item.price) && item.price >= 0 && /^\d+$/.test(item.phone));
cart = cart.map(item => catalog[item.id] ? {...catalog[item.id], quantity:item.quantity} : item);
function saveCart() { try { localStorage.setItem('campusexpress-cart-v1', JSON.stringify(cart)); } catch {} updateCounts(); }
function updateCounts() { const count = cart.reduce((sum,item) => sum + item.quantity, 0); document.querySelectorAll('.cart-count').forEach(el => { el.textContent = count; el.hidden = !count; }); }
const money = value => 'UGX ' + new Intl.NumberFormat('en-UG').format(value);
function element(tag, text, className) { const el=document.createElement(tag); if(text !== undefined) el.textContent=text; if(className) el.className=className; return el; }
const menu = document.getElementById('account-menu');
const menuToggle = document.getElementById('menu-toggle');
function toggleMenu(force) { menu.hidden = force === undefined ? !menu.hidden : !force; menuToggle.setAttribute('aria-expanded', String(!menu.hidden)); }
menuToggle.addEventListener('click', () => toggleMenu());
document.getElementById('account-nav').addEventListener('click', () => { window.scrollTo({top:0}); toggleMenu(true); menu.querySelector('a').focus(); });
document.addEventListener('click', event => { if(!menu.contains(event.target) && !menuToggle.contains(event.target) && !event.target.closest('#account-nav')) toggleMenu(false); });
document.addEventListener('keydown', event => { if(event.key === 'Escape' && !menu.hidden) { toggleMenu(false); menuToggle.focus(); } });
document.getElementById('search-nav').addEventListener('click', () => document.getElementById('search-input').focus());
const campusDialog=document.getElementById('campus-dialog');
const campusInput=document.getElementById('campus-input');
const campus=readSaved('campusexpress-campus', '');
if(typeof campus === 'string' && campus) { campusInput.value=campus; document.getElementById('campus-label').textContent=campus; }
document.getElementById('campus-toggle').addEventListener('click', () => campusDialog.showModal());
document.getElementById('campus-form').addEventListener('submit', event => { event.preventDefault(); const value=campusInput.value.trim(); if(!value) { campusInput.setCustomValidity('Enter your campus name.'); campusInput.reportValidity(); return; } document.getElementById('campus-label').textContent=value; try { localStorage.setItem('campusexpress-campus',JSON.stringify(value)); } catch {} campusDialog.close(); });
campusInput.addEventListener('input', () => campusInput.setCustomValidity(''));
let noticeTimer;
document.querySelectorAll('[data-add]').forEach(button => button.addEventListener('click', () => {
 const item=catalog[button.dataset.add]; if(!item || !item.available) return;
 const existing=cart.find(row => row.id === item.id); if(existing) existing.quantity=Math.min(99,existing.quantity+1); else cart.push({...item,quantity:1}); saveCart();
 const notice=document.getElementById('cart-notice'); notice.textContent=item.name+' added to cart'; notice.hidden=false; clearTimeout(noticeTimer); noticeTimer=setTimeout(() => notice.hidden=true,2200);
}));
function renderCart() {
 const list=document.getElementById('cart-items'); list.replaceChildren();
 const checkout=document.getElementById('cart-checkout'); checkout.replaceChildren();
 const total=document.getElementById('cart-total'); total.replaceChildren();
 if(!cart.length) { list.append(element('p','Your cart is empty. Add a few campus essentials.','empty-state')); return; }
 cart.forEach(item => {
  const row=element('div',undefined,'cart-row'); const heading=element('div',undefined,'cart-row-top'); heading.append(element('strong',item.name),element('span',money(item.price*item.quantity))); row.append(heading,element('p',item.vendor,'muted'));
  const controls=element('div',undefined,'quantity-controls');
  [-1,1].forEach(delta => { const button=element('button',delta===1?'+':'−'); button.type='button'; button.setAttribute('aria-label',(delta===1?'Increase ':'Decrease ')+item.name+' quantity'); button.disabled=delta===1 && item.quantity>=99; button.addEventListener('click',()=>{ item.quantity+=delta; cart=cart.filter(row=>row.quantity>0); saveCart(); renderCart(); }); controls.append(button); if(delta===-1) controls.append(element('span',String(item.quantity))); }); row.append(controls); list.append(row);
 });
 total.append(element('p','Subtotal: '+money(cart.reduce((sum,item)=>sum+item.price*item.quantity,0)),'cart-total'));
 const groups=new Map(); cart.forEach(item=>{ if(!groups.has(item.vendorId)) groups.set(item.vendorId,[]); groups.get(item.vendorId).push(item); });
 groups.forEach(items=>{
  const link=element('a',window.customerSignedIn?'Order from '+items[0].vendor:'Sign in to order from '+items[0].vendor,'orange-button checkout-link');
  if(!window.customerSignedIn) link.href='user_login.php'; else {
   const location=document.getElementById('campus-label').textContent;
   const message='Hello '+items[0].vendor+', I would like to order:\n'+items.map(item=>item.quantity+' × '+item.name+' ('+money(item.price*item.quantity)+')').join('\n')+'\nSubtotal: '+money(items.reduce((sum,item)=>sum+item.price*item.quantity,0))+(location!=='Select campus'?'\nCampus: '+location:'')+'\nPlease confirm availability, delivery time and delivery fee.';
   link.href='https://wa.me/'+items[0].phone+'?text='+encodeURIComponent(message); link.target='_blank'; link.rel='noopener noreferrer';
   link.addEventListener('click',()=>items.forEach(item=>{ fetch('track_order.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({vendor_id:item.vendorId,menu_item_id:item.id,order_method:'whatsapp',csrf_token:document.querySelector('meta[name="csrf-token"]').content})}).catch(()=>{}); }));
  }
  checkout.append(link);
 });
}
document.querySelectorAll('[data-open-cart]').forEach(button=>button.addEventListener('click',()=>{renderCart();document.getElementById('cart-dialog').showModal();}));
saveCart();
