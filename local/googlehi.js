// from http://www.edgewall.org/chrome/common/js/trac.js
// Adapted from http://www.kryogenix.org/code/browser/searchhi/
function MoniSearchHighlight() {
  if (!document.createElement) return;

  var div = document.getElementById("wikiBody");
  if (!div) return;

  function getSearchWords(url) {
    if (url.indexOf('?') == -1) return [];
    var queryString = url.substr(url.indexOf('?') + 1);
    var params = queryString.split('&');
    var act = 0;
    for (var i=0;i<params.length;i++) {
      var param = params[i].split('=');
      if (param.length < 2) continue;
      if (param[0] == 'action' && param[1] == 'highlight') {
        act = 1;
      } else if (act == 1 && param[0] == 'value') {
        param[0] = 'q';
      }
      if (param[0] == 'q' || param[0] == 'p') { // q= for Google, p= for Yahoo
        if (param[1].match(/^\d+$/)) continue;
        var query = decodeURIComponent(param[1].replace(/\+/g, ' '));
        if (query[0] == '!') query = query.slice(1);
        words = query.split(/(".*?")|('.*?')|(\s+)/);
        var words2 = new Array();
        for (var w in words) {
          try {
            words[w] = words[w].replace(/^\s+$/, '');
            if (words[w] != '') {
              words2.push(words[w].replace(/^['"]/, '').replace(/['"]$/, ''));
            }
          } catch(e){};
        }
        return words2;
      }
    }
    return [];
  }

  function highlightWord(node, word, searchwordindex) {
    // If this node is a text node and contains the search word, highlight it by
    // surrounding it with a span element
    if (node.nodeType == 3) { // Node.TEXT_NODE
      var pos = node.nodeValue.toLowerCase().indexOf(word.toLowerCase());
      if (pos >= 0 && !/^searchword\d$/.test(node.parentNode.className)) {
        var span = document.createElement("span");
        span.className = "searchword" + (searchwordindex % 5);
        span.appendChild(document.createTextNode(
          node.nodeValue.substr(pos, word.length)));
        node.parentNode.insertBefore(span, node.parentNode.insertBefore(
          document.createTextNode(node.nodeValue.substr(pos + word.length)),
            node.nextSibling));
        node.nodeValue = node.nodeValue.substr(0, pos);
        return true;
      }
    } else if (!node.nodeName.match(/button|select|textarea/i)) {
      // Recurse into child nodes
      for (var i = 0; i < node.childNodes.length; i++) {
        if (highlightWord(node.childNodes[i], word, searchwordindex)) i++;
      }
    }
    return false;
  }

  var words = getSearchWords(document.URL);
  if (!words.length) words = getSearchWords(document.referrer);
  if (words.length) {
    for (var w in words) {
      if (words[w].length) highlightWord(div, words[w], w);
    }
  }
}


if (window.addEventListener) window.addEventListener("load",MoniSearchHighlight,false);
else if (window.attachEvent) window.attachEvent("onload",MoniSearchHighlight);
