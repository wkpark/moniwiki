var _cookie_autosave='_MONI_SAVE_';

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

function moni_autosave(form) {
    var val=getCookie(_cookie_autosave);

    if (!this.timer) {
        var key=location+'';
        key=key.replace(/^https?:\/\//,'');
        txts=cookieToVar(val);

        if (txts[key]) {
            if (true && confirm('Auto saved text found.\nAre you sure to restore page ?') ) {
                form.elements['savetext'].value=txts[key];

                delete txts[key];
                var cookie = varToCookie(txts);
                setCookie(_cookie_autosave, cookie);
            }
        }
        self = this;
        self.form=form;
        this.timer = setInterval(function() {
            var val=getCookie(_cookie_autosave);
            var cookie;
            var txts=cookieToVar(val);
            var key=location+'';
            key=key.replace(/^https?:\/\//,'');
            txts[key]=self.form.elements['savetext'].value;
            cookie=varToCookie(txts);

            var exp = new Date(); // 7-days
            exp.setTime(exp.getTime() + 7*24*60*60*1000);

            setCookie(_cookie_autosave, cookie, exp );
            } ,2000);
    }
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
 * vim:et:sts=4:sw=4
 */
