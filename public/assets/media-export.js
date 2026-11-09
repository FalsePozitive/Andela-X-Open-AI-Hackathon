'use strict';
(() => {
  const ui=JSON.parse(document.body.dataset.ui||'{}');
  const waitFor=(element,event,timeout=30000,signal)=>new Promise((resolve,reject)=>{
    if(signal?.aborted){reject(new Error(ui.videoCancelled));return;}
    const clean=()=>{clearTimeout(timer);element.removeEventListener(event,done);element.removeEventListener('error',fail);signal?.removeEventListener('abort',cancel);};
    const done=()=>{clean();resolve();};const fail=()=>{clean();reject(new Error(ui.videoLoadError));};
    const cancel=()=>{clean();reject(new Error(ui.videoCancelled));};
    const timer=setTimeout(()=>{clean();reject(new Error(ui.videoLoadError));},timeout);
    element.addEventListener(event,done,{once:true});element.addEventListener('error',fail,{once:true});
    signal?.addEventListener('abort',cancel,{once:true});
  });
  async function exportVideo({source,qr,id,onProgress=()=>{},signal}) {
    if(signal?.aborted)throw new Error(ui.videoCancelled);
    if(!window.MediaRecorder||!HTMLCanvasElement.prototype.captureStream)throw new Error(ui.videoUnsupported);
    const AudioContext=window.AudioContext||window.webkitAudioContext;
    if(!AudioContext)throw new Error(ui.videoUnsupported);
    const context=new AudioContext();await context.resume();
    const video=document.createElement('video');video.playsInline=true;video.preload='auto';video.crossOrigin='anonymous';
    const badge=new Image();badge.crossOrigin='anonymous';
    let recorder,stream,frame,node;let cancelled=false;
    const abort=()=>{cancelled=true;if(recorder?.state==='recording')recorder.stop();video.pause();};
    signal?.addEventListener('abort',abort,{once:true});
    try {
      if(signal?.aborted)throw new Error(ui.videoCancelled);
      const ready=waitFor(video,'loadeddata',30000,signal);video.src=source;video.load();await ready;
      if(!Number.isFinite(video.duration)||video.duration>600||video.duration<=0)throw new Error(ui.videoTooLong);
      const qrReady=waitFor(badge,'load',30000,signal);badge.src=qr;await qrReady;
      const scale=Math.min(1,1280/video.videoWidth,720/video.videoHeight);
      const canvas=document.createElement('canvas');canvas.width=Math.max(640,Math.round(video.videoWidth*scale));canvas.height=Math.max(360,Math.round(video.videoHeight*scale));
      const paint=canvas.getContext('2d');if(!paint)throw new Error(ui.videoUnsupported);
      const draw=()=>{
        paint.fillStyle='#FFFBF1';paint.fillRect(0,0,canvas.width,canvas.height);
        const w=Math.round(video.videoWidth*scale),h=Math.round(video.videoHeight*scale);paint.drawImage(video,(canvas.width-w)/2,(canvas.height-h)/2,w,h);
        const x=canvas.width-196,y=canvas.height-226;paint.fillStyle='#fff';paint.fillRect(x,y,180,210);paint.strokeStyle='#DAA520';paint.strokeRect(x+.5,y+.5,179,209);
        paint.drawImage(badge,x+8,y+5,164,164);paint.fillStyle='#332513';paint.font='bold 15px Arial';paint.fillText('okwethu',x+12,y+185);paint.font='12px Arial';paint.fillText(id,x+12,y+201);
        onProgress(Math.min(99,Math.floor(video.currentTime/video.duration*100)));
        if(!video.ended&&!cancelled)frame=requestAnimationFrame(draw);
      };
      stream=canvas.captureStream(24);
      const audio=context.createMediaStreamDestination();node=context.createMediaElementSource(video);node.connect(audio);
      for(const track of audio.stream.getAudioTracks())stream.addTrack(track);
      const mime=['video/webm;codecs=vp8,opus','video/webm','video/mp4'].find(type=>MediaRecorder.isTypeSupported(type));
      if(!mime)throw new Error(ui.videoUnsupported);
      recorder=new MediaRecorder(stream,{mimeType:mime,videoBitsPerSecond:2000000});
      const chunks=[];let bytes=0;let failure;
      const complete=new Promise((resolve,reject)=>{
        recorder.addEventListener('dataavailable',event=>{if(event.data.size){bytes+=event.data.size;if(bytes>50*1024*1024){failure=new Error(ui.videoTooLarge);abort();}else chunks.push(event.data);}});
        recorder.addEventListener('error',()=>reject(new Error(ui.videoExportError)),{once:true});
        recorder.addEventListener('stop',()=>failure?reject(failure):cancelled?reject(new Error(ui.videoCancelled)):resolve(new Blob(chunks,{type:recorder.mimeType})),{once:true});
        video.addEventListener('ended',()=>{if(recorder.state==='recording')recorder.stop();},{once:true});
        video.addEventListener('error',()=>{failure=new Error(ui.videoLoadError);abort();},{once:true});
      });
      // The source is routed to the recorder's audio destination, not speakers.
      recorder.start(1000);draw();
      try {await video.play();}catch{failure=new Error(ui.videoPlayError);abort();}
      const output=await complete;onProgress(100);return {blob:output,extension:output.type.startsWith('video/mp4')?'mp4':'webm'};
    } finally {
      if(frame)cancelAnimationFrame(frame);video.pause();video.removeAttribute('src');video.load();
      if(recorder?.state==='recording')recorder.stop();for(const track of stream?.getTracks()||[])track.stop();
      node?.disconnect();await context.close();signal?.removeEventListener('abort',abort);
    }
  }
  window.OkwethuMedia={exportVideo};
  document.querySelectorAll('[data-video-export]').forEach(button=>button.addEventListener('click',async()=>{
    const section=button.closest('[data-media-download]');const status=section.querySelector('[role="status"]');const cancel=section.querySelector('[data-cancel-export]');const controller=new AbortController();
    button.disabled=true;cancel.hidden=false;cancel.onclick=()=>controller.abort();
    try {
      status.textContent=ui.videoWorking;
      const result=await exportVideo({source:button.dataset.videoExport,qr:button.dataset.qr,id:button.dataset.storyId,signal:controller.signal,onProgress:value=>status.textContent=`${ui.videoWorking} ${value}%`});
      const link=document.createElement('a');const url=URL.createObjectURL(result.blob);link.href=url;link.download=`okwethu-${button.dataset.storyId}.${result.extension}`;link.click();setTimeout(()=>URL.revokeObjectURL(url),60000);status.textContent=ui.videoDone;
    }catch(error){status.textContent=error.message||ui.videoExportError;}
    finally{button.disabled=false;cancel.hidden=true;cancel.onclick=null;}
  }));
})();
