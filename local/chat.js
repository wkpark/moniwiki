function sendMsg(ev,obj,url,id,num) {
   var value='';
   var cc='';
   var ch='';
   var e;
   if (ev!='poll') {
     e = ev ? ev : window.event;
     if(ev) { // for Mozilla
       cc=e.keyCode;
       if(e.charCode>0) {
         ch=String.fromCharCode(e.charCode);
       }
     } else { // for IE
       if(e.keyCode>0) {
         ch=String.fromCharCode(e.keyCode);
         cc=e.keyCode;
       }
     }
     if (cc!=13) return;
   }

   if (obj!=null) value=escape(obj.value);
   nurl=url.replace(/\/ajax/,
      '\/ajax&value='+value+'&room='+id+'&item='+num);
   if (obj!=null) obj.value='';
   var msg=HTTPGet(nurl);
   var chat=document.getElementById(id);
   //var node=document.createElement('li');
   //node.innerHTML=msg;
   //chat.appendChild(node);
   chat.innerHTML=msg;

   return;
}

