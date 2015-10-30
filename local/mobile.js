/**
 * MoniWiki automatic image switcher for mobile devices
 *
 * @author  wkpark at kldp.org
 * @since   2014/02/04
 * @license GPLv2
 */
function is_mobile() {
    var ua = navigator.userAgent;
    if (ua.match(/Android/i) ||
        ua.match(/webOS/i) ||
        ua.match(/iPhone/i) ||
        ua.match(/iPad/i) ||
        ua.match(/iPod/i) ||
        ua.match(/BlackBerry/i) ||
        ua.match(/Windows Phone/i)
       ) {
        return true;
    } else if (location.host.match(/^m\./))
        // force mobile mode with m.foobar.com hostname
        return true;

    return false;
}

(function() {
var is_m = is_mobile();
var device_width = (screen.width > window.innerWidth) ? window.innerWidth : screen.width;

if (device_width >= 800) {
    device_width = 800;
} else {
    var w = 160;
    while (w <= device_width)
        w+= 160;
    w-= 160;
    device_width = w;
}

/* thumb width defined ? */
if (typeof thumb_width != 'undefined' && thumb_width < device_width)
    device_width = thumb_width;

function toggle(o) {
    var node = o.parentNode;
    if (node.nodeName != 'A' && typeof o._wrapper == 'undefined') {
        // wrap images to show loading spinner
        o._wrapper = document.createElement('div');
        o._wrapper.className = 'wrapper loading';
        o.parentNode.insertBefore(o._wrapper, o);
        o._wrapper.appendChild(o);
    }
    var src;
    if (node.nodeName == 'A' && node.href.match(/\.(png|jpe?g|gif)$/i)) {
        if (typeof o._src == 'undefined') o._src = '';
        if (o._src == '') {
            o._src = String(o.src);
            src = String(node.href);
        } else {
            src = o._src;
            o._src = '';
        }
    } else {
        if (typeof o._src == 'undefined') o._src = '';
        src = String(o.src);
        var query;
        var m = false;

        // toggle thumbnails
        if (o._src != '') {
            src = o._src; // restore
            o._src = '';
        } else if ((m = src.match(/thumbnails\/(.*)\.w\d+\.(png|jpe?g|gif)$/i))) {
            o._src = src; // save
            src = src.substring(0, m.index);
            src+= m[1] + "." + m[2];
        } else if (src.match(/action=(download|fetch)/)) {
            // toggle m=0 query string
            if (is_m)
                query = '&thumbwidth=' + device_width;
            else
                query = '&thumb=0';

            if (src.match(/&thumb(width)?=\d+/)) {
                src = src.replace(/&thumb(width)?=\d+/, '');
            } else {
                src+= query;
            }
        }
    }
    var img = new Image();
    img.src = src;
    img.onload = function() {
        // save image width
        if (typeof o._zoom == 'undefined') o._zoom = 0;

        if (o._zoom == 0) {
            if (o.getAttribute('width')) {
                o._width = o.getAttribute('width');
		o.removeAttribute('width');
            }
            if (o.getAttribute('height')) {
                o._height = o.getAttribute('height');
		o.removeAttribute('height');
            }
            o._zoom = 1;
        } else {
            if (typeof o._width != 'undefined')
                o.setAttribute('width', o._width);
            if (typeof o._height != 'undefined')
                o.setAttribute('height', o._height);
            o._zoom = 0;
        }
        o.src = img.src;
        if (typeof o._wrapper != 'undefined')
            o._wrapper.className = 'wrapper';
    };

    var a = o._wrapper.parentNode.getElementsByTagName('A');
    for (var i = 0; i < a.length; i++) {
        if (a[i].getAttribute('class') == 'zoom') {
            a[i].setAttribute('class', 'zoom out');
            break;
        } else if (a[i].getAttribute('class') == 'zoom out') {
            a[i].setAttribute('class', 'zoom');
            break;
        }
    }
}

function init_images() {
    var img = document.getElementsByTagName('img');
    var query = '&thumbwidth=[0-9]+';
    var re = new RegExp(query);
    var no_gif_thumbnails = typeof no_gif_thumbnails !== 'undefined' ? no_gif_thumbnails : false;

    for (var i = 0; i < img.length; i++) {
        var m = null;
        var dataSrc = img[i].getAttribute('data-src');

        if (dataSrc || (m = img[i].src.match(/thumbnails\/(.*)\.w\d+\.(png|jpe?g|gif)$/i)) ||
                img[i].src.match(/action=fetch|download/)) {

            // do not use thumbnail for gif
            if (no_gif_thumbnails && img[i].src.match(/gif$/i)) continue;

            var node = img[i].parentNode;
            if (is_m) {
                if (img[i].getAttribute('width'))
		    img[i].removeAttribute('width');
                if (img[i].getAttribute('height'))
		    img[i].removeAttribute('height');
            }

            var el;
            if (node.nodeName == 'A')
                el = img[i];
            else
                el = node;

            if (!dataSrc)
            el.onclick = (function(o) {
                return function() {
                    toggle(o);
                    return false;
                };
            })(img[i]);

            if (node.nodeName == 'A') continue;

            var a = document.createElement('a');
            var expand;
            if (dataSrc) {
                expand = document.createTextNode('Open');
                a.className = 'new-window';
            } else {
                expand = document.createTextNode('+');
                a.className = 'zoom';
            }

            if (m) {
                var src;
                if (dataSrc)
                    src = dataSrc;
                else
                    src = String(img[i].src);
                src = src.substring(0, m.index);
                src+= m[1] + "." + m[2];
                a.href = src;
            } else {
                var src;
                if (dataSrc)
                    src = dataSrc;
                else
                    src = String(img[i].src);
                if (src.match(/&thumb(width)?=\d+/)) {
                    src = src.replace(/&thumb(width)?=\d+/, '');
                }
                if (is_m)
                    a.href = src + '&m=0';
                else
                    a.href = src + '&thumb=0';
            }

            a.setAttribute('target', '_blank');
            a.onclick = function(e) {
	        if (e.stopPropagation)
                    e.stopPropagation();
	        else if (e.preventDefault)
                    e.preventDefault();
		e.cancelBubble = true;
            };
            img[i].style.display = 'inline';
            if (!dataSrc && m == null && is_m) {
                var src = String(img[i].src);
                if (!src.match(re))
                    src+= '&thumbwidth=' + device_width;
                img[i].src = src;
            }

            a.appendChild(expand);
            img[i].parentNode.insertBefore(a, img[i].nextSibling);

            if (dataSrc) {
                var anchor = img[i].nextSibling;
                var info = null;
                if (anchor)
                    info = anchor.nextSibling;

                if (info && info.className.match(/info/)) {
                    info.style.display = 'block';
                    info.style.position = 'relative';
                }
            }
            img[i].onload = function() {
                /*
                if (this.naturalWidth && this.naturalWidth > 128) return;
                */
                var anchor = this.nextSibling;
                if (anchor)
                    var info = anchor.nextSibling;
                if (this.height && this.height < 128) {
                    if (anchor && anchor.className.match(/zoom/))
                        this.parentNode.removeChild(anchor);
                }
                var test = this.height / 3;
                if (test < 20)
                    test = 20;
                if (info && info.className.match(/info/) && info.offsetHeight > test) {
                    this.style.marginBottom = info.offsetHeight + 'px';
                    info.style.margin = '0';
                }
            };
        }
    }
}

// onload
if (jQuery)
    $(document).ready(function() { init_images(); });
else
if (window.addEventListener)
    window.addEventListener("DOMContentLoaded",init_images,false);
else if (window.attachEvent)
    window.attachEvent("onload",init_images);
})();
// vim:et:sts=4:sw=4:
