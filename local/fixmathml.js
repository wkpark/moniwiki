/*
March 19, 2004 MathHTML (c) Peter Jipsen http://www.chapman.edu/~jipsen
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or (at
your option) any later version.
This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
(at http://www.gnu.org/copyleft/gpl.html) for more details.

import some codes from ASCIIMathML.js by wkpark at kldp.org
2005/03/27
*/

var isIE = document.createElementNS==null;

if (document.getElementById==null) 
  alert("This webpage requires a recent browser such as\
\nMozilla/Netscape 7+ or Internet Explorer 6+MathPlayer")

// all further global variables start with "AM"

function MLcreateElementXHTML(t) {
  if (isIE) return document.createElement(t);
  else return document.createElementNS("http://www.w3.org/1999/xhtml",t);
}

function AMisMathMLavailable() {
  var nd = MLcreateElementXHTML("center");
  nd.appendChild(document.createTextNode("To view the "));
  var an = MLcreateElementXHTML("a");
  an.appendChild(document.createTextNode("ASCIIMathML"));
  an.setAttribute("href","http://www.chapman.edu/~jipsen/asciimath.html");
  nd.appendChild(an);
  nd.appendChild(document.createTextNode(" notation use Internet Explorer 6+"));  
  an = MLcreateElementXHTML("a");
  an.appendChild(document.createTextNode("MathPlayer"));
  an.setAttribute("href","http://www.dessci.com/en/products/mathplayer/download.htm");
  nd.appendChild(an);
  nd.appendChild(document.createTextNode(" or Netscape/Mozilla/Firefox"));
  if (navigator.appName.slice(0,8)=="Netscape") 
    if (navigator.appVersion.slice(0,1)>="5") return null;
    else return nd;
  else if (navigator.appName.slice(0,9)=="Microsoft")
    try {
        var ActiveX = new ActiveXObject("MathPlayer.Factory.1");
        return null;
    } catch (e) {
        return nd;
    }
  else return nd;
}

var MLmathml = "http://www.w3.org/1998/Math/MathML";

function MLcreateElementMathML(t) {
  if (isIE) return document.createElement("mml:"+t);
  else return document.createElementNS(MLmathml,t);
}

function MLcreateMmlNode(name,frag) {
  var node = MLcreateElementMathML(name);
  node.appendChild(frag);
  return node;
}

function MLfixNode(n,linebreaks) {
  var mtch, str, arr;
  if (n.nodeName=="MATH") {
    n.parentNode.replaceChild(convertMath(n),n);
  } else {
    for (var i=0; i<n.childNodes.length; i++)
      MLfixNode(n.childNodes[i], linebreaks);
  }
}

function convertMath(node) {// for Gecko
  if (node.nodeType==1) {
    var newnode =
      document.createElementNS("http://www.w3.org/1998/Math/MathML",
        node.nodeName.toLowerCase());
    for(var i=0; i < node.attributes.length; i++)
      newnode.setAttribute(node.attributes[i].nodeName,
        node.attributes[i].nodeValue);
    for (var i=0; i<node.childNodes.length; i++) {
      var st = node.childNodes[i].nodeValue;
      if (st==null || st.slice(0,1)!=" " && st.slice(0,1)!="\n")
        newnode.appendChild(convertMath(node.childNodes[i]));
    }
    return newnode;
  }
  else return node;
}

function fixMmlById(objId) {
  MLbody = document.getElementById(objId);
  MLfixNode(MLbody, false);
  if (isIE) { //needed to match size and font of formula to surrounding text
    var frag = document.getElementsById(objId);
    for (var i=0;i<frag.length;i++) frag[i].update()
  }
}
