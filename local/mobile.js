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
    }
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
    if (node.nodeName == 'A' && node.href.match(/(\.png|jpe?g|gif)$/i)) {
        if (typeof o._src == 'undefined') o._src = '';
        if (o._src == '') {
            o._src = String(o.src);
            src = String(node.href);
        } else {
            src = o._src;
            o._src = '';
        }
    } else {
        src = String(o.src);
        // toggle m=0 query string property
        var query;
        if (is_m)
            query = '&thumbwidth=' + device_width;
        else
            query = '&thumb=0';
        var re = new RegExp(query);

        if (src.match(re)) {
            src = src.replace(re, '');
        } else {
            src+= query;
        }
    }
    var img = new Image();
    img.src = src;
    img.onload = function() {
        // save image width
        if (typeof o._zoom == 'undefined') o._zoom = 0;

        if (o._zoom == 0) {
            if (o.getAttribute('width') > 0) {
                o._width = o.width;
		o.removeAttribute('width');
            }
            if (o.getAttribute('height') > 0) {
                o._height = o.height;
		o.removeAttribute('height');
            }
            o._zoom = 1;
        } else {
            if (typeof o._width != 'undefined')
                o.width = o._width;
            if (typeof o._height != 'undefined')
                o.height = o._height;
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

    for (var i = 0; i < img.length; i++) {
        if (img[i].src.match(/action=fetch|download/)) {
            var node = img[i].parentNode;
            if (is_m) {
                if (img[i].getAttribute('width') > 0)
		    img[i].removeAttribute('width');
                if (img[i].getAttribute('height') > 0)
		    img[i].removeAttribute('height');
            }
            if (img[i].naturalWidth < 64)
                continue;

            var el;
            if (node.nodeName == 'A')
                el = img[i];
            else
                el = node;

            el.onclick = (function(o) {
                return function() {
                    toggle(o);
                    return false;
                };
            })(img[i]);

            if (node.nodeName == 'A') continue;

            var a = document.createElement('a');
            var expand = document.createTextNode('+');
            a.className = 'zoom';
            if (is_m)
                a.href = img[i].src + '&m=0';
            else
                a.href = img[i].src + '&thumb=0';
            a.setAttribute('target', '_blank');
            a.onclick = function(e) {
	        if (e.stopPropagation)
                    e.stopPropagation();
	        else if (e.preventDefault)
                    e.preventDefault();
		e.cancelBubble = true;
            };
            img[i].style.display = 'inline';

            a.appendChild(expand);
            img[i].parentNode.insertBefore(a, img[i].nextSibling);
        }
    }
}

// onload
if (window.addEventListener)
    window.addEventListener("load",init_images,false);
else if (window.attachEvent)
    window.attachEvent("onload",init_images);
})();
// vim:et:sts=4:sw=4:
