//
// from the MoinMoin: http://moinmoin.wikiwikiweb.de
//

function isnumbered(obj) {
  return obj.childNodes.length && obj.firstChild.childNodes.length && obj.firstChild.firstChild.className == 'lineNumber';
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
    while (l != null) {
      if (l.tagName == 'SPAN') {
        var s = document.createElement('SPAN');
        s.className = 'lineNumber'
        s.appendChild(document.createTextNode(nformat(n,4,' ')));
        n += nstep;
        if (l.childNodes.length)
          l.insertBefore(s, l.firstChild)
        else
          l.appendChild(s)
      }
      l = l.nextSibling;
    }
  return false;
}
function remnumber(did) {
  var c = document.getElementById(did), l = c.firstChild;
  if (isnumbered(c))
    while (l != null) {
      if (l.tagName == 'SPAN' && l.firstChild.className == 'lineNumber') l.removeChild(l.firstChild);
      l = l.nextSibling;
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
