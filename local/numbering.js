//
// from the MoinMoin: http://moinmoin.wikiwikiweb.de
//

function isnumbered(obj) {
  var c = obj.firstChild;
  while(true) {
    if (c.tagName && c.className == 'line') {
      if (c.firstChild && c.firstChild.className == 'lineNumber') {
        return true;
      } else {
        return false;
      }
    }
    var c = c.nextSibling;
  }
  return false;
}
function nformat(num,chrs,add) {
  var nlen = Math.max(0,chrs-(''+num).length), res = '';
  while (nlen>0) { res += ' '; nlen-- }
  return res+num+add;
}
function addnumber(did, nstart, nstep) {
  var c = document.getElementById(did), l = c.firstChild, n = 1;
  if (!isnumbered(c))
    if (typeof nstart == 'undefined') nstart = 1;
    if (typeof nstep  == 'undefined') nstep = 1;
    n = nstart;
    var ls = c.getElementsByTagName('span');
    for (var i=0;i<ls.length;i++) {
      var l = ls[i];
      if (l.tagName == 'SPAN' && l.className == 'line') {
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
  return false;
}
function remnumber(did) {
  var c = document.getElementById(did), l = c.firstChild;
  if (isnumbered(c))
    var ls = c.getElementsByTagName('span');
    for (var i=0;i<ls.length;i++) {
      var l = ls[i];
      if (l.tagName == 'SPAN' && l.firstChild.className == 'lineNumber') l.removeChild(l.firstChild);
    }
  return false;
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
