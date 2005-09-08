function UncheckAll(obj,idx) {
   var form;
   var elem;
   if (typeof obj == 'string') {
     form=document.getElementById(obj);
     elem=form.elements;
   } else if (obj==undefined) {
     form=document.getElementsByTagName('form')[idx];
     elem=form.elements;
   } else {
     elem=obj.parentNode.getElementsByTagName('input');
   }
   for (i=0;i<elem.length;i++) {
      if (elem[i].type=='checkbox' && elem[i].checked==1) {
        elem[i].checked=0;
      }
   }
}

function CheckAll(obj,idx) {
   var form;
   var elem;
   if (typeof obj == 'string') {
     form=document.getElementById(obj);
     elem=form.elements;
   } else if (obj==undefined) {
     form=document.getElementsByTagName('form')[idx];
     elem=form.elements;
   } else {
     elem=obj.getElementsByTagName('input');
   }
   for (i=0;i<elem.length;i++) {
      if (elem[i].type=='checkbox' && elem[i].checked==0) {
        elem[i].checked=1;
      }
   }
}

function ToggleAll(obj,idx) {
   var form;
   var elem;
   if (typeof obj == 'string') {
     form=document.getElementById(obj);
     elem=form.elements;
   } else if (obj==undefined) {
     form=document.getElementsByTagName('form')[idx];
     elem=form.elements;
   } else {
     form=obj;
     elem=obj.getElementsByTagName('input');
   }
   for (i=0;i<elem.length;i++) {
      if (elem[i].type=='checkbox') {
        if (elem[i].checked==0) elem[i].checked=1;
        else elem[i].checked=0;
      }
   }
}

