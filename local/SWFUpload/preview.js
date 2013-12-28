function alignImg(obj,val) {
    var preview = obj.parentNode;
    var elem = preview.getElementsByTagName("img");
    var i,j;
    for (i=0;i<elem.length;i++) {
        rad=elem[i].className = 'alignImg';
    }

    obj.className = 'alignImg selected';

    var tag = document.getElementById("insertTag");
    if (tag) {
        var href= tag.href;
        var dum=href.split(/,/);
        if (val == 'normal') {
            dum[1]="''";
        } else {
            dum[1]="'" + '?align='+val+"'";
        }
        tag.href=dum.join(",");
        if (dum[2] != '') {
            if (tag.href.substr(0, 10) == 'javascript') {
                eval(tag.href.substr(11));
            }
        }
    }
}

function showImgPreview(filename,temp) {
    var preview = document.getElementById("filePreview");
    if (!preview) return;
    var tag_open='attachment:',tag_close='';
    var href_open='',href_close='';
    var jspreview=0;
    var icon_dir = _url_prefix + '/imgs/plugin/UploadedFiles/gnome';
    var img_dir = _url_prefix + '/local/SWFUpload/images';
    var preview_width='100px';
    var alt='';
    var fname='';
    var path;

    var form=document.getElementById("filesDisplay").getElementsByTagName("form")[0];
    var mydir= '';
    if (form.mysubdir) {
        mydir = form.mysubdir.value;
    }

    var ef = document.getElementById('editform');
    if (ef) {
        jspreview=1;
    }

    //var loc = location.protocol + '//' + location.host;
    //if (location.port) loc += ':' + location.port;
    //path = loc + _url_prefix + '/pds/.swfupload/' + mydir + filename;
    path = _url_prefix + '/pds/.swfupload/' + mydir + filename;

    if (jspreview) {
        tag_open="attachment:"; tag_close="";
//      if (opener != value) tag_open+=opener;
        alt="alt='" + tag_open + filename + tag_close +"'";
    }

    var m=filename.match(/\.(.{1,4})$/);
    var ext=m[1].toLowerCase();
    var isImg=0;
    var myAlign='';
    if (ext && ext.match(/gif|png|jpeg|jpg|bmp/)) {
        if (temp) {
            var postdata = 'action=markup/ajax&value=' + encodeURIComponent("attachment:" + filename);
            var myhtml='';
            var href = self.location + '';
            href = href.replace(/\?action=edit/, '');
            myhtml= HTTPPost(href, postdata);

            var m = myhtml.match(/<img src=(\'|\")([^\'\"]+)\1/i); // strip div tag
            path = m[2];
        }
        fname="<img src='" + path + "' width='" + preview_width + ' ' + alt + " />";
        isImg=1;

        if (jspreview) {
            myAlign =" <img src='" + img_dir + "/normal.png' class='alignImg' onclick='javascript:alignImg(this,\"normal\")' />";
            myAlign+=" <img src='" + img_dir + "/left.png'  class='alignImg' onclick='javascript:alignImg(this,\"left\")' />";
            myAlign+=" <img src='" + img_dir + "/right.png'  class='alignImg' onclick='javascript:alignImg(this,\"right\")' />";
        }
    } else {
        if (ext.match(/^(wmv|avi|mpeg|mpg|swf|wav|mp3|ogg|midi|mid|mov)$/)) {
            tag_open='[[Media('; tag_close=')]]';
            alt=tag_open + filename + tag_close;
        } else if (!ext.match(/^(bmp|c|h|java|py|bak|diff|doc|css|php|xml|html|mod|rpm|deb|pdf|ppt|xls|tgz|gz|bz2|zip)$/)) {
            ext='unknown';
        }
        fname="<img src='" + icon_dir + "/" + ext + ".png' " +  alt + " />";
    }
    if (jspreview) {
        //if (strpos($file,' '))
        link="javascript:insertTags('" + tag_open + "','" +  tag_close + "','" + filename + "',true)";
        href_open="<a id='insertTag' href=\""+ link + "\">";href_close="</a>";
    } else if (isImg && form.use_lightbox.value) {
        var myclick='myLightbox.start(this)';
        if (form.use_lightbox.value == 2) {
            myclick='LightBox._show(1)';
        }
        href_open="<a href=\""+ path + "\" rel=\"lightbox[mmswf]\" onclick=\"" + myclick + "; return false;\">";href_close="</a>";
    }

    var align = document.getElementById("previewAlign");
    align.innerHTML=myAlign;
    preview.innerHTML=href_open + fname + href_close;
}

