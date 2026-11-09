'use strict';
const meta = document.querySelector('meta[name="csrf-token"]');
const csrf = meta?.content || '';
const messages = JSON.parse(document.body.dataset.ui || '{}');
async function send(url, payload, method = 'POST', file) {
  const options = {method, credentials: 'same-origin', headers: {'X-CSRF-Token': csrf}};
  if (file) { const form = new FormData(); form.append('payload', JSON.stringify(payload)); form.append('media', file); options.body = form; }
  else { options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(payload); }
  const response = await fetch(url, options);
  let result;
  try { result = await response.json(); } catch { throw new Error(messages.serverError); }
  if (!response.ok) throw new Error(result.error?.message || messages.formError);
  return result;
}
function values(form) {
  const result = {};
  for (const field of form.elements) {
    if (!field.name || field.type === 'submit') continue;
    result[field.name] = field.type === 'checkbox' ? field.checked : field.hasAttribute('data-int') ? (field.value === '' ? null : Number(field.value)) : field.value;
  }
  return result;
}
async function submit(form, action) {
  const message = form.querySelector('.form-message');
  const button = form.querySelector('button[type="submit"], button:not([type])');
  if (button) button.disabled = true;
  message.textContent = ''; message.classList.remove('error');
  try { await action(); }
  catch (error) { message.textContent = error.message; message.classList.add('error'); message.setAttribute('tabindex', '-1'); message.focus(); }
  finally { if (button) button.disabled = false; }
}
document.querySelectorAll('form[data-api]').forEach(form => form.addEventListener('submit', event => {
  event.preventDefault();
  submit(form, async () => {
    const payload=values(form);
    if (form.hasAttribute('data-terms')) payload.shares=[...form.querySelectorAll('[data-share-id]')].map(input=>({contributorId:input.dataset.shareId,percentage:input.value}));
    const result = await send(form.dataset.api, payload, form.dataset.method || 'POST');
    if (form.hasAttribute('data-reload')) { location.reload(); return; }
    form.querySelector('.form-message').textContent = messages[form.dataset.success || 'saved'] + (result.aiFlag ? ' ' + messages.aiFlag : '');
    if (result.trackingUrl) {
      const message=form.querySelector('.form-message');const link=document.createElement('a');
      link.href=result.trackingUrl;link.textContent=messages.privateTracking || 'Save your private tracking link';message.append(document.createElement('br'),link);
      const hint=document.createElement('p');hint.textContent=messages.trackingHint || 'Anyone with this link can view and respond to your request. Keep it private; it expires after 30 days.';message.append(hint);
      if(result.emailDelivery==='disabled') {const note=document.createElement('p');note.textContent=messages.emailDisabled || 'Email delivery is currently disabled. Save this link to follow your request.';message.append(note);}
    }
    if (!form.dataset.method) form.reset();
  });
}));
document.querySelectorAll('form[data-consent]').forEach(form => form.addEventListener('submit', event => {
  event.preventDefault();
  submit(form, async () => {
    const updates = [...form.querySelectorAll('[data-purpose]')].map(box => ({contributorId: box.dataset.person, purpose: box.dataset.purpose, granted: box.checked}));
    await send(`/api/stories/${form.dataset.consent}/consent`, {updates,expectedRevision:Number(form.elements.expectedRevision.value),reason:form.elements.reason.value}, 'PATCH');
    location.reload();
  });
}));
document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
document.querySelectorAll('time[data-local-date]').forEach(time => {const date = new Date(time.dateTime); if (!isNaN(date)) time.textContent = date.toLocaleDateString(undefined, {year:'numeric',month:'short',day:'numeric'});});
const wizard = document.querySelector('#story-form');
if (wizard) {
  let step = 0;
  const sections = [...wizard.querySelectorAll('[data-step]')];
  const contributors = document.querySelector('#contributors');
  const shares = document.querySelector('#shares');
  const preview = document.querySelector('#split-preview');
  const next = document.querySelector('#next');
  const previous = document.querySelector('#previous');
  const bps = new Map();
  function scaledDecimal(value) {const text=String(value).trim();if(!/^\d+(?:\.\d{1,2})?$/.test(text))return NaN;const [whole,fraction='']=text.split('.');const result=Number(whole)*100+Number(fraction.padEnd(2,'0'));return Number.isSafeInteger(result)?result:NaN;}
  function people() { return [...contributors.querySelectorAll('.person')].map(p => ({key:p.dataset.key, name:p.querySelector('[data-name]').value, role:p.querySelector('[data-role]').value, isCommunityFund:p.querySelector('[data-fund]').checked, consents:Object.fromEntries([...p.querySelectorAll('[data-purpose]')].map(c => [c.dataset.purpose,c.checked])), percentage:String(bps.get(p.dataset.key) || '0'), bps:scaledDecimal(bps.get(p.dataset.key) || '0')})); }
  function addPerson() {
    if (contributors.children.length >= 50) return;
    const fragment = document.querySelector('#person-template').content.cloneNode(true);
    const person = fragment.querySelector('.person'); person.dataset.key = crypto.randomUUID();
    person.querySelector('[data-remove]').addEventListener('click', () => {if (contributors.children.length > 1) {bps.delete(person.dataset.key); person.remove();}});
    contributors.append(fragment);
  }
  function splitPreview() {
    const list = people(); const total = list.reduce((sum,p) => sum+p.bps,0); const amount = scaledDecimal(wizard.elements.sampleAmount.value);
    preview.replaceChildren();
    const summary = document.createElement('p'); summary.textContent = `${messages.total}: ${Number.isFinite(total)?(total/100).toFixed(2):'—'}% / 100%`; summary.className = total === 10000 ? 'badge' : 'notice warning'; preview.append(summary);
    if (total !== 10000 || !Number.isSafeInteger(amount) || amount < 0 || amount > 1000000000 || list.some(p => !Number.isInteger(p.bps) || p.bps < 0)) return;
    const allocations = new Map(list.map(p => [p.key,Math.floor(amount*p.bps/10000)]));
    let remainder = amount - [...allocations.values()].reduce((a,b) => a+b,0);
    const ranked = [...list].sort((a,b) => b.bps-a.bps || list.indexOf(a)-list.indexOf(b));
    for (const person of ranked) { if (remainder && person.bps > 0) {allocations.set(person.key,allocations.get(person.key)+1); remainder--;} }
    for (const p of list) {const line = document.createElement('p'); line.textContent = `${p.name}: ${messages.amountLabel} ZAR ${(allocations.get(p.key)/100).toFixed(2)}`; preview.append(line);}
    const note = document.createElement('small'); note.textContent = messages.previewNote; preview.append(note);
  }
  function shareFields() {
    shares.replaceChildren();
    for (const person of people()) {const label = document.createElement('label'); label.textContent = `${person.name} · %`; const input = document.createElement('input'); input.type='number'; input.min='0'; input.max='100'; input.step='0.01'; input.required=true; input.value=String(bps.get(person.key)||0); input.addEventListener('input', () => {bps.set(person.key,input.value); splitPreview();}); label.append(input); shares.append(label);}
    splitPreview();
  }
  function show() {
    sections.forEach((section,i) => {section.hidden=i!==step; section.querySelectorAll('input,textarea,select').forEach(field => field.disabled=i!==step);});
    wizard.querySelectorAll('[data-step-label]').forEach((label,i) => label.setAttribute('aria-current',i===step?'step':'false'));
    previous.hidden=step===0; next.hidden=step===4;
    if (step===3) shareFields();
    if (step===4) {const review=document.querySelector('#review'); review.replaceChildren(); const data=payload(); for (const text of [`${data.title} · ${data.mediaType} · ${data.sensitivity}`,`${data.creator.name} · ${data.language}`,data.contextNote,`${messages.terms}: ${data.license.preset} · ${messages.aiTraining}: ${data.license.aiTrainingAllowed?messages.aiAllowed:messages.aiNotAllowed}`,`${messages.stewardContact}: ${data.license.contactEmail}`,...data.contributors.map(p => `${p.name} (${p.role}): ${p.percentage}% · ${messages.granted}: ${Object.entries(p.consents).filter(([,v])=>v).map(([k])=>k).join(', ')||messages.none}`)]) {const p=document.createElement('p'); p.textContent=text; review.append(p);}}
  }
  function payload() {const f=wizard.elements; return {communityId:f.communityId?.value,title:f.title.value,language:f.language.value,creator:{type:f.creatorType.value,name:f.creatorName.value,representativeName:f.representativeName.value},mediaType:f.mediaType.value,sensitivity:f.sensitivity.value,textBody:f.textBody.value,placeCoarse:f.placeCoarse.value,contextNote:f.contextNote.value,license:{preset:f.preset.value,aiTrainingAllowed:f.aiTrainingAllowed.checked,contactEmail:f.contactEmail.value,notes:f.notes.value},termsConfirmed:f.termsConfirmed.checked,sharesConfirmed:f.sharesConfirmed.checked,contributors:people().map(({key,bps,...p})=>p)};}
  next.addEventListener('click', () => {
    const invalid=[...sections[step].querySelectorAll('input,textarea,select')].find(field=>!field.checkValidity()); if (invalid) {invalid.reportValidity(); return;}
    if (step===0 && wizard.elements.creatorType.value==='COMMUNITY' && !wizard.elements.representativeName.value.trim()) {wizard.querySelector('.form-message').textContent=messages.addRepresentative; return;}
    if (step===0 && wizard.elements.mediaType.value==='text' && !wizard.elements.textBody.value.trim()) {wizard.querySelector('.form-message').textContent=messages.addText; return;}
    if (step===0 && wizard.elements.mediaType.value!=='text' && !wizard.elements.media.files.length) {wizard.querySelector('.form-message').textContent=messages.addFile; return;}
    if (step===3 && people().reduce((sum,p)=>sum+p.bps,0)!==10000) {wizard.querySelector('.form-message').textContent=messages.splitTotal; return;}
    wizard.querySelector('.form-message').textContent=''; step++; show();
  });
  previous.addEventListener('click',()=>{step--;show();});
  wizard.elements.sampleAmount.addEventListener('input',splitPreview);
  wizard.elements.mediaType.addEventListener('change',()=>{const text=wizard.elements.mediaType.value==='text'; wizard.querySelector('[data-text-field]').hidden=!text;wizard.querySelector('[data-file-field]').hidden=text;});
  document.querySelector('#add-person').addEventListener('click',addPerson);
  wizard.addEventListener('submit',event=>{event.preventDefault();if(step!==4)return;submit(wizard,async()=>{const result=await send('/api/stories',payload(),'POST',wizard.elements.media.files[0]);location.href=`/p/${result.id}`;});});
  addPerson(); show();
}


document.querySelector('[data-copy-credit]')?.addEventListener('click',async()=>{
  const field=document.querySelector('#credit-line');const message=document.querySelector('[data-copy-message]');
  try {await navigator.clipboard.writeText(field.value);message.textContent=messages.creditCopied;}
  catch {field.focus();field.select();message.textContent=messages.creditManual;}
});
document.querySelectorAll('[data-terms]').forEach(form=>{
  const fields=[...form.querySelectorAll('[data-share-id]')];const total=form.querySelector('[data-share-total]');
  const update=()=>{const sum=fields.reduce((n,f)=>{const value=f.value;if(!/^\d+(?:\.\d{1,2})?$/.test(value))return NaN;const [whole,fraction='']=value.split('.');return n+Number(whole)*100+Number(fraction.padEnd(2,'0'));},0);total.textContent=`${messages.total}: ${Number.isFinite(sum)?(sum/100).toFixed(2):'—'}%`;total.classList.toggle('error',sum!==10000);};
  fields.forEach(f=>f.addEventListener('input',update));update();
});