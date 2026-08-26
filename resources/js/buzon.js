const errors = [];

function rememberError(message, file, line) {
    errors.push({mensaje:String(message||'').slice(0,500),archivo:String(file||'—').slice(0,300),linea:String(line??'—'),hora:new Date().toLocaleTimeString('es-MX')});
    if (errors.length > 5) errors.splice(0, errors.length - 5);
}

window.addEventListener('error', (event) => rememberError(event.message, event.filename, event.lineno));
window.addEventListener('unhandledrejection', (event) => rememberError(event.reason?.message || event.reason, 'Promise', '—'));

function initWidget(root) {
    const panel=root.querySelector('.bz-panel'), overlay=root.querySelector('.bz-overlay'), steps=[...root.querySelectorAll('[data-buzon-step]')];
    const typeInput=root.querySelector('[data-buzon-tipo]'), severity=root.querySelector('[data-buzon-severity]'), title=root.querySelector('#bz-title');
    const showStep=(name)=>{steps.forEach((step)=>{step.hidden=step.dataset.buzonStep!==name;});if(name==='choose')title.textContent='Enviar comentario';if(name==='history')title.textContent='Mis reportes';};
    const open=()=>{panel.hidden=false;overlay.hidden=false;document.body.style.overflow=window.innerWidth<=640?'hidden':'';const context=root.querySelector('[data-buzon-context]');if(context)context.value=JSON.stringify({userAgent:navigator.userAgent,resolucion:`${window.screen.width}x${window.screen.height}`,errores:errors.slice()});};
    const close=()=>{panel.hidden=true;overlay.hidden=true;document.body.style.overflow='';};
    root.querySelector('[data-buzon-open]')?.addEventListener('click',open);
    root.querySelectorAll('[data-buzon-close]').forEach((button)=>button.addEventListener('click',close));
    root.querySelectorAll('[data-buzon-type]').forEach((button)=>button.addEventListener('click',()=>{typeInput.value=button.dataset.buzonType;severity.hidden=typeInput.value!=='error';severity.querySelectorAll('input').forEach((input)=>{input.disabled=typeInput.value!=='error';});showStep('form');title.textContent=typeInput.value==='error'?'Reportar un error':'Enviar una sugerencia';}));
    root.querySelectorAll('[data-buzon-back]').forEach((button)=>button.addEventListener('click',()=>showStep('choose')));
    root.querySelector('[data-buzon-history]')?.addEventListener('click',()=>showStep('history'));
    root.querySelector('[data-buzon-image]')?.addEventListener('change',(event)=>{const preview=root.querySelector('[data-buzon-preview]'),file=event.target.files?.[0];if(!file){preview.hidden=true;return;}preview.src=URL.createObjectURL(file);preview.hidden=false;});
    if(typeInput?.value){severity.hidden=typeInput.value!=='error';severity.querySelectorAll('input').forEach((input)=>{input.disabled=typeInput.value!=='error';});}
    if(root.dataset.openOnLoad==='true'||root.querySelector('[data-buzon-success]'))open();
    if(root.querySelector('[data-buzon-success]'))setTimeout(close,4000);
    document.addEventListener('keydown',(event)=>{if(event.key==='Escape'&&!panel.hidden)close();});
}

function initBoard(board) {
    let dragged=null;
    board.querySelectorAll('[data-ticket-id]').forEach((card)=>{card.addEventListener('dragstart',()=>{dragged=card;card.classList.add('is-dragging');});card.addEventListener('dragend',()=>{card.classList.remove('is-dragging');dragged=null;});});
    board.querySelectorAll('[data-buzon-column]').forEach((column)=>{
        column.addEventListener('dragover',(event)=>{event.preventDefault();column.classList.add('is-drop');});
        column.addEventListener('dragleave',()=>column.classList.remove('is-drop'));
        column.addEventListener('drop',async(event)=>{event.preventDefault();column.classList.remove('is-drop');if(!dragged)return;const state=column.dataset.buzonColumn,url=board.dataset.updateTemplate.replace('__ID__',dragged.dataset.ticketId);const response=await fetch(url,{method:'PATCH',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({estado:state})});if(response.ok)column.querySelector('.bz-board-list').prepend(dragged);else window.location.reload();});
    });
}

document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('[data-buzon-root]').forEach(initWidget);document.querySelectorAll('[data-buzon-board]').forEach(initBoard);});
