function slideshowhandler(ev,obj,url,prev,next) {
  e = ev ? ev : window.event;
  if(window.event) { // for IE
    if(e.keyCode>0) {
      cc=e.keyCode;
      ch=String.fromCharCode(e.keyCode);
    } else {
      cc=null;
      ch=null;
    }
    //alert('IE:'+cc+',"'+ch+'"');
  } else { // for Mozilla
    cc=e.keyCode;
    if(e.charCode>0) {
      ch=String.fromCharCode(e.charCode);
    } else {
      ch='';
    }
  }
  //if (cc!=13) return;
  //alert(prev+',',next);
  if (ch == ' ' || (cc==13 && ch=='')) {
    var my=''+self.location;
    if (next != '') {
      if (my.search(/&p=\d+/) != -1) {
        my=my.replace(/&p=\d+/, '&p='+next);
      } else {
        my=my.replace(/action=slideshow/i, 'action=SlideShow&p='+next);
      }
      self.location=my;
    }
  } else {

  }
  return true;
}


