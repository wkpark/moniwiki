Math.roundf = function(val, precision) {
    var p = this.pow(10, precision);
    return this.round(val * p) / p;
}

function $(id) {
    return document.getElementById(id);
}

// Default upload start function.
uploadStart = function(fileObj) {
    $("filesDisplay").style.display = "block";

    if (document.getElementById(fileObj.name)) {
        $(fileObj.name).className = "uploading";
        return false;
    }
    var li = document.createElement("li");
    var txt = document.createTextNode(fileObj.name);

    li.className = "uploading";
    li.id = fileObj.name;
    
    var prg = document.createElement("span");
    prg.id = fileObj.name + "progress";
    prg.className = "progressBar"
    
    li.appendChild(txt);
    li.appendChild(prg);

    $("mmUploadFileListing").appendChild(li);

    delFiles();
}

uploadProgress = function(fileObj, bytesLoaded) {
    var pie = document.getElementById("fileProgressInfo");
    var proc = Math.ceil((bytesLoaded / fileObj.size) * 100);

    pie.style.background = "url(" + _url_prefix + "/local/SWFUpload/images/progressbar.png) repeat-y -" + (100 - proc) + "px 0";
    pie.innerHTML = proc + " %";
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

    $(fileObj.name).className = "uploadDone";
    $(fileObj.name).innerHTML = "<input type='checkbox' checked='checked' />"
        + "<a href='javascript:showImgPreview(\"" + fileObj.name + "\")'>" + fileObj.name + "</a>" + " (" + size + ")";

    var pie = document.getElementById("fileProgressInfo");
    pie.style.background='';
    pie.innerHTML = "";
}

uploadCancel = function() {
    alert("Cancel!");
}

function delFiles() {
    var listing = $("mmUploadFileListing");
    var elem = listing.getElementsByTagName("li");

    for (var i=0;i<elem.length;i++) {
        var chk= elem[i].getElementsByTagName("input")[0];
        if (chk.type=='checkbox' && chk.checked==0) {
            elem[i].parentNode.removeChild(elem[i]);
        }
    }
}

function fileSubmit(obj) {
    var listing = $("mmUploadFileListing");
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
    //alert(form.innerHTML);
}

function showImgPreview(filename) {
    var preview = document.getElementById("filePreview");
    if (preview) {
        preview.innerHTML='<img src="' + _url_prefix + '/pds/_swfupload/' + filename + '" width="100px" />';
    }
}

/*
 * vim:et:sts=4:sw=4
 */
