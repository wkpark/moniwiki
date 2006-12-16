Math.roundf = function(val, precision) {
    var p = this.pow(10, precision);
    return this.round(val * p) / p;
}

function byId(id) {
    return document.getElementById(id);
}

// Default upload start function.
uploadStart = function(fileObj) {
    byId("filesDisplay").style.display = "block";

    if (document.getElementById(fileObj.name)) {
        byId(fileObj.name).className = "uploading";
        return false;
    }
    var li = document.createElement("li");
    var txt = document.createTextNode(fileObj.name);

    li.className = "uploading";
    li.id = fileObj.name;

    var prg = document.createElement("span");
    prg.id = fileObj.name + "progress";
    prg.className = "progressBar";
    
    li.appendChild(txt);
    li.appendChild(prg);

    byId("mmUploadFileListing").appendChild(li);

    delFiles();
}

uploadProgress = function(fileObj, bytesLoaded) {
    var pie = document.getElementById("fileProgressInfo");
    var proc = Math.ceil((bytesLoaded / fileObj.size) * 100);

    pie.style.background = "url(" + _url_prefix + "/local/SWFUpload/images/progressbar.png) repeat-y -" + (100 - proc) + "px 0";
    pie.innerHTML = proc + " %";

    var progress = byId(fileObj.name + "progress");
    progress.style.background = pie.style.background;
}

uploadComplete = function(fileObj) {
    var unt= new Array('Bytes','KB','MB','GB','TB');
    var size=fileObj.size;
    var i;
    for (i=0;i<4;i++) {
        if (size <= 1024) {
            break;
        }
        size=size/1024;
    }
    size= Math.roundf(size,2) + " " + unt[i];

    byId(fileObj.name).className = "uploadDone";
    byId(fileObj.name).innerHTML = "<input type='checkbox' checked='checked' />"
        + "<a href='javascript:showImgPreview(\"" + fileObj.name + "\")'>" + fileObj.name + "</a>" + " (" + size + ")";

    var pie = byId("fileProgressInfo");
    pie.style.background='';
    pie.innerHTML = "";
}

uploadCancel = function() {
    alert("Cancel!");
}

function delFiles() {
    var listing = byId("mmUploadFileListing");
    var elem = listing.getElementsByTagName("li");

    for (var i=0;i<elem.length;i++) {
        var chk= elem[i].getElementsByTagName("input")[0];
        if (chk.type=='checkbox' && chk.checked==0) {
            elem[i].parentNode.removeChild(elem[i]);
        }
    }
}

function fileSubmit(obj) {
    var listing = byId("mmUploadFileListing");
    var elem = listing.getElementsByTagName("li");
    var selected = new Array();
    var form = obj.parentNode;
    var j=0;

    for (var i=0;i<elem.length;i++) {
        var chk= elem[i].getElementsByTagName("input")[0];
        if (chk.type=='checkbox' && chk.checked==1) {
            var inp = document.createElement('INPUT');
            inp.setAttribute("name",'MYFILES[]');
            inp.setAttribute("type",'hidden');
            inp.setAttribute("value",elem[i].id);
            form.appendChild(inp);
        }
    }
}

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
    }
}

function showImgPreview(filename) {
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

    mydir = mydir ? mydir:'';

    path = _url_prefix + '/pds/.swfupload/' + mydir + filename;

    if (preview.className=="previewTag") {
        jspreview=1;
    }

    if (jspreview) {
        tag_open="attachment:"; tag_close="";
//      if (opener != value) tag_open+=opener;
//      alt="alt='" + tag_open + filename + tag_close +"'";
    }

    var m=filename.match(/\.(.{1,4})$/);
    var ext=m[1].toLowerCase();
    var isImg=0;
    var myAlign='';
    if (ext && ext.match(/gif|png|jpeg|jpg|bmp/)) {
        fname="<img src='" + path + "' width='" + preview_width + ' ' + alt + " />";
        isImg=1;

        if (jspreview) {
            myAlign="<div id='previewAlign'>";
            myAlign+=" <img src='" + img_dir + "/normal.png' class='alignImg' onclick='javascript:alignImg(this,\"normal\")' />";
            myAlign+=" <img src='" + img_dir + "/left.png'  class='alignImg' onclick='javascript:alignImg(this,\"left\")' />";
            myAlign+=" <img src='" + img_dir + "/right.png'  class='alignImg' onclick='javascript:alignImg(this,\"light\")' />";
            myAlign+="</div>";
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

    preview.innerHTML=myAlign + href_open + fname + href_close;
}

/*
 * vim:et:sts=4:sw=4
 */
