//
// dynamic section editing for MoniWiki
//
function sectionEdit(ev,obj,sect) {
  if (sect) {
    var sec=document.getElementById('sect-'+sect);
    var ed=document.getElementById('editSect-'+sect);
    if (ed) { // toogle
      sec.parentNode.removeChild(sec.parentNode.lastChild);
      return;
    }
    var href=obj.href.replace(/=edit/,'=edit/ajax');
    function showform(form) {
      if (form.substring(0,5) == 'false') return;
      //var node=document.createElement('li');
      //node.innerHTML=msg;
      //chat.appendChild(node);
      var f=document.createElement('div');
      f.setAttribute('id','editSect-'+sect);
      f.innerHTML=form;
      sec.parentNode.appendChild(f);
    }

    if (typeof fetch == 'function') {
      fetch(url, { method: 'GET' })
        .then(function(res) { return res.text(); })
        .then(showform);
      return;
    }

    HTTPGet(href, showform);
  }
}

function savePage(obj) {
  obj.elements['action'].value+='/ajax';
  //alert(self.location);
  var sec=document.getElementById('sect-'+obj.section.value);
  var toSend = '';
  for (var i=0;i<obj.elements.length;i++) {
    if (obj.elements[i].name != '')  {
      toSend += (toSend ? '&' : '') + obj.elements[i].name + '='
                                  + encodeURIComponent(obj.elements[i].value);
      //alert(obj.elements[i].name+'='+obj.elements[i].value);
    }
  }

  function saveform(form) {

  if (form.substring(0,4) == 'true') {
    var ed=document.getElementById('editSect-'+obj.section.value);
    if (ed) { // toogle
      sec.parentNode.removeChild(sec.parentNode.lastChild);
    }

    toSend = 'action=markup&all=1&section=' + obj.section.value;
    if (typeof fetch == "function") {
      fetch(self.location, {
        body: toSend,
        method: "POST",
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
      })
        .then(function(res) { return res.text(); })
        .then(function(form) {
          sec.parentNode.innerHTML = form;
      });
      return false;
    }
    HTTPPost(self.location,toSend,function(form){
      sec.parentNode.innerHTML=form;
    });
    return false;
  } else {
    var f=document.createElement('div');
    f.setAttribute('id','editSect-'+obj.section.value);
    // show error XXX
    f.innerHTML=form;
    sec.parentNode.appendChild(f);
  }

  }

  if (typeof fetch == "function") {
    fetch(self.location, {
      body: toSend,
      method: "POST",
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
      .then(function(res) { return res.text(); })
      .then(saveform);
    return false;
  }

  HTTPPost(self.location,toSend,saveform);
  return false;
}

