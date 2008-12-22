// http://code.google.com/p/syntaxhighlighter
// syntaxhighlighter wrapper for MoniWiki
//
// $Id$
// 
function shOnload() {
    var tags = document.getElementsByTagName('pre');
    if (tags.length == 0) return;
    var head = document.getElementsByTagName("head")[0];
    var list = [];
    var syntax = {
        'c':'Cpp',
        'c#':'Cpp',
        'cpp':'Cpp',
        'c-sharp':'Cpp',
        'css':'Css',
        'java':'Java',
        'javascript':'JScript',
        'jscript':'JScript',
        'php':'Php',
        'python':'Python',
        'ruby':'Ruby',
        'xml':'Xml',
        'html':'Xml'
    };

    for(var i = 0; i < tags.length; i++) {
        var m = tags[i].className.match(/wiki\s+(c|cpp|c#|xml|c-sharp|css|java|javascript|php|python|ruby|xml|html)/);
        if (m && syntax[m[1]]) {
            if (!list[syntax[m[1]]]) {
                var js = document.createElement('script');
                js.type = 'text/javascript';
                js.src = _url_prefix + '/local/dp.SyntaxHighlighter/Uncompressed/shBrush' + syntax[m[1]]+ '.js';
                head.appendChild(js);
            }
            tags[i].setAttribute('name','code');
            //tags[i].className = syntax[m[1]] + ':showcolumns';
            tags[i].className = syntax[m[1]];
            list[syntax[m[1]]]= 1;
        }
    }
    dp.SyntaxHighlighter.ClipboardSwf = _url_prefix + '/local/dp.SyntaxHighlighter/Scripts/clipboard.swf';
    dp.sh.HighlightAll('code',true,true);
    return;
}

//window.addEvent("domready", shOnload);
(function () {
    var head = document.getElementsByTagName("head")[0];
    var js = document.createElement('script');
    js.type = 'text/javascript';
    js.src = _url_prefix + '/local/dp.SyntaxHighlighter/Uncompressed/shCore.js';
    head.appendChild(js);

    // onload
    var oldOnload = window.onload;
    if (typeof window.onload != 'function') {
        window.onload = shOnload;
    } else {
        window.onload = function() {
            oldOnload();
            shOnload();
        }
    }
})();

// vim:et:sts=4:sw=4:
