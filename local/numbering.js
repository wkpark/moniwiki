//
// from the MoinMoin: http://moinmoin.wikiwikiweb.de
//
if ( typeof _ == 'undefined') {
    _ = function(msgid) {
        return msgid;
    };
}

function isnumbered(obj) {
  var c = obj.getElementsByTagName('li');
  if (c.length == 0) {
    c = obj.getElementsByTagName('span');
  }
  if (c.length > 0 && c[0].className.match(/line/)) {
    if (c[0].tagName == 'SPAN' && c[0].className == 'lineNumber') {
      return true;
    } else if (c[0].firstChild && c[0].firstChild.tagName == 'SPAN' && c[0].firstChild.className == 'lineNumber') {
      return true;
    } else {
      return false;
    }
  }
  return false;
}
function nformat(num,chrs,add) {
  var nlen = Math.max(0,chrs-(''+num).length), res = '';
  while (nlen>0) { res += ' '; nlen-- }
  return res+num+add;
}
function addnumber(did, nstart, nstep) {
  var c = document.getElementById(did), n = 1;
  if (!isnumbered(c)) {
    if (typeof nstart == 'undefined') nstart = 1;
    if (typeof nstep  == 'undefined') nstep = 1;
    n = nstart;
    var ls = c.getElementsByTagName('span');
    if (ls.length == 0)
      ls = c.getElementsByTagName('li');

    for (var i=0;i<ls.length;i++) {
      var l = ls[i];
      if (l.className.match(/line/) && l.className != 'lineNumber') {
        var s = document.createElement('SPAN');
        s.className = 'lineNumber'
        s.appendChild(document.createTextNode(nformat(n,4,' ')));
        n += nstep;
        if (l.childNodes.length)
          l.insertBefore(s, l.firstChild)
        else
          l.appendChild(s)
      }
    }
  }
  return;
}
function remnumber(did) {
  var c = document.getElementById(did);
  if (isnumbered(c)) {
    ls = c.getElementsByTagName('li');
    if (ls.length == 0) {
      var ls = c.getElementsByTagName('span');
      for (var i=0;i<ls.length;i++) {
        var l = ls[i];
        if (l.firstChild.tagName && l.firstChild.className == 'lineNumber') l.removeChild(l.firstChild);
      }
      return;
    }
    for (var i=0;i<ls.length;i++) {
      var l = ls[i];
      if (l.firstChild.className == 'lineNumber') l.removeChild(l.firstChild);
    }
  }
  return;
}
function togglenumber(did, nstart, nstep) {
  var c = document.getElementById(did);
  if (isnumbered(c)) {
    remnumber(did);
  } else {
    addnumber(did,nstart,nstep);
  }
  return false;
}

function addtogglebutton(id) {
    var c = document.getElementById(id);
    var a = document.createElement('a');
    var txt = document.createTextNode(_('Toggle line numbers'));
    a.appendChild(txt);
    a.href = '#';
    a.className = 'codenumbers';
    a.onclick = function() { return togglenumber(id,1,1); };
    c.parentNode.insertBefore(a,c);
}

// vim:et:sts=4:sw=4:
