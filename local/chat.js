function sendMsg(obj,url,id) {
   if (obj.value=='') return false;
   nurl=url.replace(/\/ajax/,'\/ajax&value='+obj.value);
   obj.value='';
   var msg=HTTPGet(nurl);
   var chat=document.getElementById(id);
   //var node=document.createElement('li');
   //node.innerHTML=msg;
   //chat.appendChild(node);
   chat.innerHTML=msg;
   return true;
}
