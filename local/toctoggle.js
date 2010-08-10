// imported from the WikiMedia and modified for the MoniWiki
//

function showTocToggle(target, showBtn,hideBtn) {
  if (document.createTextNode) {
    // Uses DOM calls to avoid document.write + XHTML issues

    var toc = document.getElementById(target);
    var linkHolder = toc.firstChild;
    if (!linkHolder) return;

    var outerSpan = document.createElement('span');
    outerSpan.className = 'toctoggle';

    var toggleLink = document.createElement('a');
    toggleLink.id = 'togglelink';
    toggleLink.className = 'internal';
    toggleLink.href = 'javascript:toggleToc("' + target + '")';

    var showToc = document.createElement('span');
    showToc.id = 'show' + target;
    showToc.style.display = 'none';
    showToc.innerHTML = showBtn;

    var hideToc = document.createElement('span');
    hideToc.id = 'hide' + target;
    hideToc.innerHTML = hideBtn;

    toggleLink.appendChild(hideToc);
    toggleLink.appendChild(showToc);

    outerSpan.appendChild(toggleLink);

    linkHolder.appendChild(document.createTextNode(' '));
    linkHolder.appendChild(outerSpan);
  }
}

function toggleToc(el) {
    var toc = document.getElementById(el);
    if (!toc) return;

    if (toc)
    	toc = toc.getElementsByTagName('dl')[0];
    var showtoc=document.getElementById('show' + el);
    var hidetoc=document.getElementById('hide' + el);
    var toggleLink = document.getElementById('togglelink')
  
    if(toc && toggleLink && toc.style.display == 'none') {
       toc.style.display='block';
       showtoc.style.display='none';
       hidetoc.style.display='';
    } else {
       toc.style.display='none';
       showtoc.style.display='';
       hidetoc.style.display='none';
    }
}
