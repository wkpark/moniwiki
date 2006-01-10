/*
    based on http://www.onlinetools.org/tools/easydynfont.php
*/

// and size to avoid errors
var _defaultHeight="100%"
var _cookie_lineheight='_MONI_LINEHEIGHT_';
var _nosave=false;

function drawform() {
    if (!document.layers){
        document.write("<div id=\"lineheightForm\">");
        document.write("[<a href=\"javascript:addHeight(10)\">+</a>|");
        document.write("<a href=\"javascript:addHeight(-10)\">-</a>]");
        document.write("</div>");
    }
}
/*
    function init()
    loads the cookiedata and changes the document accordingly, if there is no
    cookie, sets the standard settings and stores it
*/
function initLineHeight(){
    if (!document.layers){
        val=getCookie(_cookie_lineheight);
        if (val!=null){
            document.getElementsByTagName("body").item(0).style.lineHeight=val;
        } else {
            document.getElementsByTagName("body").item(0).style.lineHeight=_defaultHeight;
            storeHeight()
        }
    }
    // Special setting, if you want to use the "don't save" chekbox
    //_nosave=document.dynform.nosave.checked
}
/*
    function addHeight(add)
    increases the lineheigth of the document by "add", negative values make the
    lineheight smaller.
*/
function addHeight(add){
    if (!document.layers){
        doc = document.getElementsByTagName("body").item(0)
        val=parseInt(doc.style.lineHeight)+add;
        doc.style.lineHeight=val+"%";
        if (_nosave==false) storeHeight()
    }
}
/*
    function SetHeight(add)
    sets the lineheight of the document.
*/
function setHeight(add){
    if (!document.layers){
        document.getElementsByTagName("body").item(0).style.lineHeight=add+"%";
        if (_nosave==false) storeHeight()
    }
}

/*
    function storeHeight()
    saves the current settings of the document in a cookie
*/
function storeHeight(){
    var exp = new Date();
    exp.setTime(exp.getTime() + 24*60*60*90*1000);
    val=document.getElementsByTagName("body").item(0).style.lineHeight;
    setCookie(_cookie_lineheight,val,exp);
}

function setCookie(name, value, expires, path, domain, secure) {
    var curCookie = name + "=" + escape(value) +
    ((expires) ? "; expires=" + expires.toGMTString() : "") +
    ((path) ? "; path=" + path : "") +
    ((domain) ? "; domain=" + domain : "") +
    ((secure) ? "; secure" : "")
    document.cookie = curCookie
}

function getCookie(name) {
    var prefix = name + "="
    var cookieStartIndex = document.cookie.indexOf(prefix)
    if (cookieStartIndex == -1)
    return null
    var cookieEndIndex = document.cookie.indexOf(";", cookieStartIndex +
    prefix.length)
    if (cookieEndIndex == -1)
    cookieEndIndex = document.cookie.length
    return unescape(document.cookie.substring(cookieStartIndex +
    prefix.length,
    cookieEndIndex))
}

initLineHeight();
drawform();
// vim:et:sts=4:
