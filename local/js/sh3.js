// http://code.google.com/p/syntaxhighlighter
// syntaxhighlighter_3.0.xx wrapper for MoniWiki
//
// $Id$
//

if ( typeof _ == 'undefined') {
    _ = function(msgid) {
        return msgid;
    };
}

function shOnload() {
    var tags = document.getElementsByTagName('pre');
    if (tags.length == 0) return;
    var shdir = 'scripts';
    var head = document.getElementsByTagName("head")[0];
    var list = [];
    var js = null;
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
                js = document.createElement('script');
                js.type = 'text/javascript';
                js.src = _url_prefix + '/local/syntaxhighlighter_3.0.83/' + shdir + '/shBrush' + syntax[m[1]]+ '.js';
                head.appendChild(js);
            }
            //tags[i].setAttribute('name','code');
            //tags[i].className = syntax[m[1]] + ':showcolumns';
            tags[i].className = 'brush: ' + syntax[m[1]].toLowerCase();
            list[syntax[m[1]]]= 1;
        }
    }
    if (js) {
        js.onreadystatechange = function() { // IE
            if (this.readyState == 'complete')
                SyntaxHighlighter.highlight();
        }
        // safari fix.
        if ( 0 && navigator.userAgent.toLowerCase().indexOf('applewebkit') != -1) {
            (function() {
                var timer = setInterval(function() { 
                    if (/loaded|complete/.test(document.readyState)) {
                        clearInterval(timer);  
                        SyntaxHighlighter.highlight();
                    }
                }, 10);
            })();
        }
        js.onload = function() {
            SyntaxHighlighter.highlight();
            return;
        }
    }
    return;
}

//window.addEvent("domready", shOnload);
(function () {
    var shdir = 'scripts';
    // var shdir = 'Uncompressed';
    var head = document.getElementsByTagName("head")[0];
    var js = document.createElement('script');
    js.type = 'text/javascript';
    js.src = _url_prefix + '/local/syntaxhighlighter_3.0.83/' + shdir + '/shCore.js';
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
