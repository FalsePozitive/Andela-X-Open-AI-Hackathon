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
    const result = await send(form.dataset.api, values(form), form.dataset.method || 'POST');
    if (form.hasAttribute('data-reload')) { location.reload(); return; }
    form.querySelector('.form-message').textContent = messages[form.dataset.success || 'saved'] + (result.aiFlag ? ' ' + messages.aiFlag : '');
    if (!form.dataset.method) form.reset();
  });
}));
document.querySelectorAll('form[data-consent]').forEach(form => form.addEventListener('submit', event => {
  event.preventDefault();
  submit(form, async () => {
    const updates = [...form.querySelectorAll('[data-purpose]')].map(box => ({contributorId: box.dataset.person, purpose: box.dataset.purpose, granted: box.checked}));
    await send(`/api/stories/${form.dataset.consent}/consent`, {updates}, 'PATCH');
    form.querySelector('.form-message').textContent = messages.saved;
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
  function people() { return [...contributors.querySelectorAll('.person')].map(p => ({key:p.dataset.key, name:p.querySelector('[data-name]').value, role:p.querySelector('[data-role]').value, isCommunityFund:p.querySelector('[data-fund]').checked, consents:Object.fromEntries([...p.querySelectorAll('[data-purpose]')].map(c => [c.dataset.purpose,c.checked])), bps:Number(bps.get(p.dataset.key) || 0)})); }
  function addPerson() {
    if (contributors.children.length >= 50) return;
    const fragment = document.querySelector('#person-template').content.cloneNode(true);
    const person = fragment.querySelector('.person'); person.dataset.key = crypto.randomUUID();
    person.querySelector('[data-remove]').addEventListener('click', () => {if (contributors.children.length > 1) {bps.delete(person.dataset.key); person.remove();}});
    contributors.append(fragment);
  }
  function splitPreview() {
    const list = people(); const total = list.reduce((sum,p) => sum+p.bps,0); const amount = Number(wizard.elements.sampleAmount.value);
    preview.replaceChildren();
    const summary = document.createElement('p'); summary.textContent = `${messages.total}: ${total.toLocaleString()} / 10,000 ${messages.basisPoints}`; summary.className = total === 10000 ? 'badge' : 'notice warning'; preview.append(summary);
    if (total !== 10000 || !Number.isSafeInteger(amount) || amount < 0 || amount > 1000000000 || list.some(p => !Number.isInteger(p.bps) || p.bps < 0)) return;
    const allocations = new Map(list.map(p => [p.key,Math.floor(amount*p.bps/10000)]));
    let remainder = amount - [...allocations.values()].reduce((a,b) => a+b,0);
    const ranked = [...list].sort((a,b) => b.bps-a.bps || list.indexOf(a)-list.indexOf(b));
    for (const person of ranked) { if (remainder && person.bps > 0) {allocations.set(person.key,allocations.get(person.key)+1); remainder--;} }
    for (const p of list) {const line = document.createElement('p'); line.textContent = `${p.name}: ${messages.amountLabel} ${allocations.get(p.key)} ${messages.minorUnits}`; preview.append(line);}
    const note = document.createElement('small'); note.textContent = messages.previewNote; preview.append(note);
  }
  function shareFields() {
    shares.replaceChildren();
    for (const person of people()) {const label = document.createElement('label'); label.textContent = `${person.name} · ${messages.basisPoints}`; const input = document.createElement('input'); input.type='number'; input.min='0'; input.max='10000'; input.step='1'; input.required=true; input.value=String(bps.get(person.key)||0); input.addEventListener('input', () => {bps.set(person.key,input.value); splitPreview();}); label.append(input); shares.append(label);}
    splitPreview();
  }
  function show() {
    sections.forEach((section,i) => {section.hidden=i!==step; section.querySelectorAll('input,textarea,select').forEach(field => field.disabled=i!==step);});
    wizard.querySelectorAll('[data-step-label]').forEach((label,i) => label.setAttribute('aria-current',i===step?'step':'false'));
    previous.hidden=step===0; next.hidden=step===4;
    if (step===3) shareFields();
    if (step===4) {const review=document.querySelector('#review'); review.replaceChildren(); const data=payload(); for (const text of [`${data.title} · ${data.mediaType} · ${data.sensitivity}`,`${data.creator.name} · ${data.language}`,data.contextNote,`${messages.terms}: ${data.license.preset} · ${messages.aiTraining}: ${data.license.aiTrainingAllowed?messages.aiAllowed:messages.aiNotAllowed}`,`${messages.stewardContact}: ${data.license.contactEmail}`,...data.contributors.map(p => `${p.name} (${p.role}): ${p.bps} bps · ${messages.granted}: ${Object.entries(p.consents).filter(([,v])=>v).map(([k])=>k).join(', ')||messages.none}`)]) {const p=document.createElement('p'); p.textContent=text; review.append(p);}}
  }
  function payload() {const f=wizard.elements; return {title:f.title.value,language:f.language.value,creator:{type:f.creatorType.value,name:f.creatorName.value,representativeName:f.representativeName.value},mediaType:f.mediaType.value,sensitivity:f.sensitivity.value,textBody:f.textBody.value,placeCoarse:f.placeCoarse.value,contextNote:f.contextNote.value,license:{preset:f.preset.value,aiTrainingAllowed:f.aiTrainingAllowed.checked,contactEmail:f.contactEmail.value,notes:f.notes.value},termsConfirmed:f.termsConfirmed.checked,sharesConfirmed:f.sharesConfirmed.checked,contributors:people().map(({key,...p})=>p)};}
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
