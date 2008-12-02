var __realImg=new Array();
var __timer=new Array();

function autoTexImgTimer(img,rimg,timer,msec) {
    timer= window.setInterval(function() {
        if (timer != null && rimg.complete == true) {
            window.clearInterval(timer);
            timer=null;
            rimg.title=img.alt;
            img.src=rimg.src;
        }
    },msec);
}

function autoTexImgLoader(loadingGif) {
    var imgs = document.getElementsByTagName('img');
    var oldImage;

    // loop through all img tags
    for (var i=0; i<imgs.length; i++){
        var img = imgs[i];
        var attr = String(img.getAttribute('class'));

        if (img.getAttribute('src') && attr.match('tex')) {
            if (img.src.match(/loading\.gif$/))
                oldImage=img.title; // hack :>
            else
                oldImage=img.src;

            img.title=img.alt;
            img.src=loadingGif;
            __realImg[i] = new Image();
            var rImg=__realImg[i];

            __realImg[i].src=String(oldImage);

            autoTexImgTimer(img,__realImg[i],__timer[i],500);
        }
    }
}

// autoTexImgLoader(_url_prefix + '/imgs/loading.gif');
addLoadEvent(function() { autoTexImgLoader(_url_prefix + '/imgs/loading.gif') })

// vim:et:sts=4:sw=4:
