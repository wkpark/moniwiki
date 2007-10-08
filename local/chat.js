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

   var d=new Date();
   var last=document.getElementById('laststamp');
   var nic='';
   if (last) last='&laststamp='+last.innerHTML;
   else last='';

   if (obj!=null) {
      value=escape(obj.value);
      obj.value=''; // clear
      nic=obj.parentNode.getElementsByTagName('input')[0];
      if (nic) nic='&nic='+escape(nic.value);
      else nic='';
   }

   var soundon_id=id.replace(/^chat/,'')+'soundon';
   var soundon=document.getElementById(soundon_id).className;

   nurl=url.replace(/\/ajax/,
     '\/ajax&value='+value+'&room='+id+nic+'&item='+num+
     '&stamp='+d.getTime()+last);

   var msg=HTTPGet(nurl);
   if (msg != 'false') {
      var chat=document.getElementById(id);
      //var node=document.createElement('li');
      //node.innerHTML=msg;
      //chat.appendChild(node);
      chat.innerHTML=msg;
      if (soundon == 'soundOn') Sound('pass');
   }
   return;
}

function Sound(sndobj) {
  var sound=document.getElementById(sndobj);
  if (sound) { try { sound.Play(); } catch (e) { sound.DoPlay(); } }
}

function setSound(id,surl) {
    var sound=document.getElementById('effect');
    var node=document.createElement('embed');
    node.setAttribute('src',surl);
    node.setAttribute('id',id);
    node.setAttribute('autostart','false');
    if (navigator.appVersion.indexOf("MSIE")!=-1)
    	node.setAttribute('hidden','true');
    node.setAttribute('loop','false');
    node.setAttribute('height','0px');
    node.setAttribute('width','0px');
    sound.appendChild(node);
}

function OnOff(obj) {
    if (obj.className=='soundOff') {
        obj.className='soundOn';
    } else {
        obj.className='soundOff';
    }
}
