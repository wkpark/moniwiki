/**
 *
 * @author: Won-Kyu Park <wkpark@kldp.org>
 * @date: 2008-12-29
 * @name: a autosave javascript module.
 * @Description: a autosave Javascript module.
 * @url: MoniWiki:AutoSave
 * @version: $Revision$
 * @license: GPL
 *
 * $Id$
 *
 */

var _cookie_autosave='_MONI_SAVE_';

if ( typeof _ == 'undefined') {
    _ = function(msgid) {
        return msgid;
    };
}

function cookieToVar(cookie) {
    if (!cookie) return {};
    var txt=cookie.split(/\0\0/);
    var txts={};
    for (var i=0;i<txt.length;i++) {
        var p=txt[i].indexOf(':');
        var k='',v='';
        if (p != -1) {
            k=txt[i].substring(0,p);
            v=txt[i].substr(p+1);
            txts[k]=v;
        }
    }
    return txts;
}

function varToCookie(val) {
    var cookie='';
    for (var k in val) cookie+=k+':'+val[k]+'\0\0';
    return cookie;
}

function moni_autosave_reset(form) {
    setCookie(_cookie_autosave,'');
}

function moni_autosave(form, min, sec) {
    var val=getCookie(_cookie_autosave);

    if (typeof min == 'undefined')
        min = 3; // 3-minuite
    if (typeof sec == 'undefined')
        sec = 10; // 10-second

    if (!this.timer) {
        var key = location+'';
        var ret = '';
        var stamp = 0;
        key = key.replace(/^https?:\/\//,'');
        txts = cookieToVar(val);

        {
            var href = location+'';
            var postdata = 'action=autosave/ajax&retrive=1';
            ret = HTTPPost(href, postdata);
            if (ret.match(/^false/)) {
                ret = null;
            } else {
                var p = ret.indexOf("\n");
                // 1230555376142
                if (p > 0) {
                    stamp = ret.substr(0, p) + '000'; // 10-digits
                    ret = ret.substr(p+1);
                }
            }
        }
        if (txts[key]) {
            if ( confirm("Auto saved text found.\nAre you sure to restore page ?\n(You can undo/redo with Ctrl-Z/Ctrl-Shift-Z)") ) {
                var p = txts[key].indexOf("\n");
                var cstamp = 0;
                var saved = '';
                if (p > 0) {
                    cstamp = txts[key].substr(0,p);
                    saved = txts[key].substr(p+1);
                }

                if (stamp > cstamp) saved = ret;
                form.elements['savetext'].value = saved;

                delete txts[key];
                var cookie = varToCookie(txts);
                setCookie(_cookie_autosave, cookie);
            }
        } else if (ret && confirm("Auto saved text found.\nAre you sure to restore page ?\n(You can undo/redo with Ctrl-Z/Ctrl-Shift-Z)") ) {
            form.elements['savetext'].value = ret;
        }
        self = this;
        self.form = form;
        this.timer2 = setInterval(function() { ajax_save(self.form); } ,min * 60*1000);
        this.timer = setInterval(function() {
            var val=getCookie(_cookie_autosave);
            var cookie;
            var txts=cookieToVar(val);
            var key=location+'';
            key=key.replace(/^https?:\/\//,'');
            var time = new Date();
            var stamp = time.getTime();

            txts[key] = stamp + "\n" + self.form.elements['savetext'].value;
            cookie=varToCookie(txts);

            var exp = new Date(); // 7-days expires
            exp.setTime(exp.getTime() + 7*24*60*60*1000);

            setCookie(_cookie_autosave, cookie, exp );
        } , sec * 1000); // cookie save
    }
}

function ajax_save(form) {
    var href = location+'';
    var time = new Date();
    var stamp = time.getTime();
    var postdata = 'action=autosave/ajax&savetext=' + encodeURIComponent(form.elements['savetext'].value);
    postdata += '&datestamp=' + stamp;

    var loading = new Image();
    loading.src = _url_prefix + '/imgs/loading.gif';
    loading.setAttribute('style','vertical-align:middle');

    var state = document.getElementById('save_state');
    if (state) {
        txt = document.createTextNode(_("Current text saved temporary."));
        state.appendChild(loading);
        state.appendChild(txt);
    }

    setTimeout(function() { state.removeChild(loading); state.removeChild(txt); }, 5000);
    var ret = HTTPPost(href, postdata);
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


/*
 * vim:et:sts=4:sw=4:
 */
