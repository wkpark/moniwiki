/**
 * Moniwiki multifile uploader
 *
 * @author  wkpark at gmail.com
 * @since   2014/01/02
 * @license GPLv2
 */

(function() {
function init_iframe() {
    // check editform
    var editform = document.getElementById('editform');
    if (editform) {
        var iframe = document.getElementById('upload-iframe');
        if (!iframe) {
            if (document.all)
                iframe = document.createElement('<iframe frameBorder="0" name="upload-iframe" width="1px" height="1px">');
            else
                iframe = document.createElement('iframe');
            iframe.setAttribute('id','upload-iframe');
            iframe.setAttribute('name','upload-iframe');
            iframe.setAttribute('style','display:none;border:0;');
            if (document.all) {
                // magic for IE6
                /*@cc_on
                if (@_jscript_version==5.6 ||
                    (@_jscript_version==5.7 && navigator.userAgent.toLowerCase().indexOf("msie 6.") != -1)) {
                    iframe.src = 'javascript:document.write("' + "<script>document.domain='" + document.domain + "';</" + "script>" + '");';
                }
                @*/
            }
            var body = document.getElementsByTagName('body')[0];
            body.appendChild(iframe);
        }
    }
}

function init_multiform() {
    for (var i = 1;; i++) {
        var id = 'upload' + i;

        var btn = document.getElementById('button-' + id);
        if (btn == null) break;

        // hide submit button
        btn.style.display = 'none';

        btn.onclick = (function(name) { return function() { check_attach(name); }; })(id);

        // init file input
        init_input(id);
    }
}

function check_attach(id) {
    // check if the form has attached files.
    var attachform = document.getElementById('form-' + id);
    var ok = false;
    var inputs = attachform.getElementsByTagName('input');

    for (i = 0; i < inputs.length; i++) {
        if (inputs[i].type == 'file' && (inputs[i].value != '' || inputs[i].files.length > 0)) {
            ok = true;
            break;
        }
    }
    if (ok == false)
        return false;

    // check editform
    var editform = document.getElementById('editform');
    if (editform) {
        // iframe upload
        iframe = document.getElementById('upload-iframe');
        if (!iframe) {
            init_iframe();
            iframe = document.getElementById('upload-iframe');
        }

        if (attachform) {
            // set domain name.
            if (location.host != document.domain) {
                if (document.all) {
                    var mydomain = document.createElement('<input name="domain">');
                } else {
                    var mydomain = document.createElement('input');
                    mydomain.setAttribute('name', 'domain');
                }

                mydomain.setAttribute('type', 'hidden');
                mydomain.setAttribute('value', document.domain + '');
                attachform.appendChild(mydomain);
            }

            attachform.setAttribute('target', 'upload-iframe');
            attachform.elements['action'].value='UploadFile/ajax';
        }

        var timer = setInterval(function() {check_upload_result(iframe, attachform, timer);}, 1500);
        return ok;
    } else {
        var timer = setInterval(function() { reset_form(attachform, false); clearInterval(timer); }, 1500);
    }
    return ok;
}

function check_upload_result(iframe, attach, timer) {
    if (!iframe) return;

    try {
        var doc = iframe.contentDocument || iframe.contentWindow.document;
    } catch(e) {
        // silently ignore
        alert('Error: '+ e + ' - Security restriction detected !\nPlease check your "document.domain=' + document.domain + '"');
        return;
    }
    if (!doc || !doc.body) return;

    var p = doc.body.firstChild;
    if (p && p.nodeType == 3 && p.nodeValue) { // text node
        eval("var ret = " + p.nodeValue);
        // remove iframe;
        iframe.parentNode.removeChild(iframe);
        alert(ret['title'] + "\n" + ret['msg']);
        for (var i = 0; i < ret['files'].length; i++) {
            if (ret['files'][i] == '') continue;
            insertTags('attachment:',' ', ret['files'][i], 3);
        }
        clearInterval(timer);
        reset_form(attach, true);
    }
}

// reset form
function reset_form(form, reset) {
    // remove file list
    var ul = form.getElementsByTagName('ul');
    if (ul) {
        while (ul[0].firstChild) {
            ul[0].removeChild(ul[0].firstChild);
        }
    }

    if (typeof reset !== 'undefined' && reset == true)
        form.reset();
}

function hsz(sz) {
    var unit = [ 'Bytes', 'kB', 'MB', 'GB', 'TB' ];
    for (var i = 0; i < 4; i++) {
        if (sz <= 1024) break;
        sz /= 1024;
    }
    return sz.toFixed(2) + ' ' + unit[i];
}

function init_input(id) {
    var inp = document.getElementById('file-' + id);
    inp.onchange = function() {
        var id = this.getAttribute('id');
        id = id.replace(/^file-/, '');

        var form = document.getElementById('form-' + id);

        // show submit button
        var btn = document.getElementById('button-' + id);
        if (btn) {
            btn.setAttribute('style','display:inline-block;');
            btn.style.display = 'inline-block';
        }

        var ul = form.getElementsByTagName('ul');
        if (!ul) return;

        ul = ul[0];
        while (ul.firstChild)
            ul.removeChild(ul.firstChild);

        if (!this.files) {
            // for old file input
            var name = name = this.value.replace(/^.*(\/|\\)/, "");
            this.files = [ { name : name } ];
        }

        for (var i = 0; i < this.files.length; i++) {
            var name, size;
            name = this.files[i].name;

            var li = document.createElement('li');

            // filename
            var span = document.createElement('span');
            var file = document.createTextNode(name);
            span.setAttribute('class', 'filename');
            span.appendChild(file);
            li.appendChild(span);

            if (this.files[i].size) {
                size = this.files[i].size;

                // filesize
                var sz = document.createElement('span');
                var txt = document.createTextNode(' (' + hsz(size) + ')');
                sz.setAttribute('class', 'filesize');
                sz.appendChild(txt);
                li.appendChild(sz);
            }

            ul.appendChild(li);
        }
    };
}

if (window.addEventListener) window.addEventListener("load", init_multiform, false);
else if (window.attachEvent) window.attachEvent("onload", init_multiform);
})();

// vim:et:sts=4:sw=4
