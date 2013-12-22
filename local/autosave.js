/**
 *
 * @author: Won-Kyu Park <wkpark@kldp.org>
 * @since: 2008-12-29
 * @date: 2013-12-10
 * @name: a autosave javascript module.
 * @Description: a autosave Javascript module.
 * @url: MoniWiki:AutoSave
 * @version: 0.5
 * @license: GPLv2
 *
 */

if ( typeof _ == 'undefined') {
    _ = function(msgid) {
        return msgid;
    };
}

// encapsulate
(function() {

function format_time(time) {
    var hms = [ time.getHours(),
                time.getMinutes(),
                time.getSeconds()];
    for (var i = 0; i < hms.length; i++) {
        if (hms[i] < 10)
            hms[i] = "0" + hms[i];
    }
    return hms.join(':');
}

function moni_autosave_reset(form) {
    // save or preview reset cookie for the selected page

    var key = location.host + form.getAttribute('action');

    if (localStorage)
        delete localStorage[key];

    var href = location + '';

    // remove saved page
    var postdata = 'action=autosave/ajax&remove=1';
    var ret = '';
    ret = HTTPPost(href, postdata);
    return true;
}

function moni_autosave(textarea, min, sec) {
    if (typeof min == 'undefined')
        min = 3; // 3-minuite
    if (typeof sec == 'undefined')
        sec = 20; // 20-second

    if (!this.timer) {
        var form = document.getElementById('editform');
        var key = location.host + form.getAttribute('action');
        var ret = '';
        var stamp = 0;

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
        var savetext = textarea.value;
        if (localStorage && localStorage[key]) {
            var p = localStorage[key].indexOf("\n");
            var cstamp = 0;
            var saved = '';

            if (p > 0) {
                cstamp = localStorage[key].substr(0,p);
                saved = localStorage[key].substr(p+1);
            }

            if (stamp > cstamp) saved = ret;
            var s = saved.replace(/\n$/, '');
            var o = savetext.replace(/\n$/, '');

            if (saved != '' && s != o &&
                    confirm("Auto saved text found.\nAre you sure to restore page ?\n(You can undo/redo with Ctrl-Z/Ctrl-Shift-Z)") ) {
                textarea.value = saved;

                delete localStorage[key];
            }
        } else if (ret != '' && ret != savetext &&
                confirm("Auto saved text found.\nAre you sure to restore page ?\n(You can undo/redo with Ctrl-Z/Ctrl-Shift-Z)") ) {
            textarea.value = ret;
        }
        self = this;
        self.textarea = textarea;

        textarea.onblur = function() {
                clearInterval(self.timer);
                clearInterval(self.timer2);
                self.timer = null;
                self.timer2 = null;
                local_save(self.textarea);
            };

        this.timer2 = setInterval(function() { ajax_save(self.textarea); } ,min * 60*1000); // ajax_save
        if (localStorage)
            this.timer = setInterval(function() { local_save(self.textarea); } , sec * 1000); // local storage save
    }
}

function local_save(textarea) {
    var form = document.getElementById('editform');
    var key = location.host + form.getAttribute('action');

    var time = new Date();
    var stamp = time.getTime();

    var orig = textarea.value;
    var updated = false;

    // is it changed ?
    if (localStorage[key]) {
        var p = localStorage[key].indexOf("\n");
        saved = localStorage[key].substr(p+1);
        var s = saved.replace(/\n$/, '');
        var o = orig.replace(/\n$/, '');
        if (s == o) return;
        updated = true;
    }
    localStorage[key] = stamp + "\n" + orig;

    var exp = new Date(); // 7-days expires
    exp.setTime(exp.getTime() + 7*24*60*60*1000);

    var state = document.getElementById('save_state');
    var txt;
    if (updated && state) {
        state.innerHTML = '';
        txt = document.createTextNode(_("Save the current text temporary..."));
        txt.nodeValue+= ' (' + format_time(time) + ')';
        state.appendChild(txt);
        state.style.display = 'block';

        setTimeout(function() {
            state.innerHTML = '';
            state.style.display = 'none';
        }, 5000);
    }
}

function ajax_save(textarea) {
    var href = location+'';
    var time = new Date();
    var stamp = time.getTime();
    var postdata = 'action=autosave/ajax&savetext=' + encodeURIComponent(textarea.value);
    postdata += '&datestamp=' + stamp;

    var loading = new Image();
    loading.src = _url_prefix + '/imgs/misc/saving.gif';
    loading.setAttribute('style','vertical-align:middle');

    var state = document.getElementById('save_state');
    var txt;
    if (state) {
        state.innerHTML = '';
        txt = document.createTextNode(_("Save the current text temporary..."));
        state.appendChild(loading);
        state.appendChild(txt);
        state.style.display = 'block';
    }

    var ret;
    ret = HTTPPost(href, postdata,
        function(ret) {
            state.innerHTML = '';
            if (ret == 'true') {
                loading.src = _url_prefix + '/imgs/misc/saved.png';
                txt = document.createTextNode(_("Successfully saved as a temporary file"));
            } else {
                loading.src = _url_prefix + '/imgs/smile/alert.png';
                txt = document.createTextNode(_("Fail to autosave."));
            }
            state.appendChild(loading);
            state.appendChild(txt);

            setTimeout(function() {
                state.innerHTML = '';
                state.style.display = 'none';
            }, 5000);
        }
    );
}

function init_autosave() {
    var form = document.getElementById('editform');

    if (form) {
        form.onsubmit = function() {
            return moni_autosave_reset(this);
        };
        var txtarea = form.savetext;
        txtarea.onclick = function() {
            return moni_autosave(this);
        };
    }
}

// onload
var oldOnload = window.onload;
window.onload = function(ev) {
    try { oldOnload(); } catch(e) {};
    init_autosave();
};
})();

/*
 * vim:et:sts=4:sw=4:
 */
