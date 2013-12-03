/**
 *
 * A Simple Javascript module to support I18n for the MoniWiki.
 *
 * @author: Won-Kyu Park <wkpark@kldp.org>
 * @date: 2008-12-18
 * @name: a simple I18n javascript module.
 * @Description: a Simple Javascript module to support I18n.
 * @url: MoniWiki:JavascriptI18n
 * @version: $Revision$
 * @license: GPL
 *
 * _translations = {
 *  "Continue to ?": "Hello World",
 * }
 *
 */

readLanguage = function(domain) {
    var supported = { 'ko': 1 };
    var lang = navigator.language || navigator.userLanguage;

    if (supported[lang.substr(0,2)]) {
        //
        // read a main language file
        //
        var head = document.getElementsByTagName("head")[0];
        var js = document.createElement('script');
        js.type = 'text/javascript';
        js.src = _url_prefix + '/local/js/locale/' + lang.substr(0,2) + '/' + domain + '.js';
        head.appendChild(js);
    }
};

loadLanguage = function() {
    // i18nize elements like as following
    // <span class="i18n" title="Hello">Hello World</span>
    if (!document.getElementsByClassName) return;

    var elems = document.getElementsByClassName('i18n');

    for (var i = 0; i < elems.length; i++) {
        if (elems[i].title != '' && elems[i].childNodes[0] && elems[i].childNodes[0].nodeValue)
            elems[i].childNodes[0].nodeValue = _(elems[i].title);
    }
}

_ = function(msgid) {
    if ( typeof _translations == "undefined") {
        return msgid;
    }

    var msgstr = _translations[msgid];
    if (!msgstr)
        msgstr = msgid;

    return msgstr;
};

//
// override the alert/confirm method.
//

(function () {
    var oldalert = window.alert;
    var oldconfirm = window.confirm;

    if (document.getElementById) {
        window.alert = function(txt) {
            oldalert(_(txt));
        }
        window.confirm = function(txt) {
            return oldconfirm(_(txt));
        }
    }

    // onload
    var oldOnload = window.onload;
    window.onload = function(ev) {
        try { oldOnload(); } catch(e) {};

        loadLanguage();
    }
})();

readLanguage('moniwiki');

// vim:et:sts=4:sw=4:
