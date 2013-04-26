// dynamic input form
function daysago(obj) {
  var ele=obj;
  if (ele.childNodes[0].innerHTML=='...') {
    //ele.innerHTML="<input type='text' name='daysago'"+
    //  " size='5' onChange='daysago(this)' />";
    //ele.focus(); // not work
    var node = document.createElement("input");
    node.setAttribute("type","text");
    node.setAttribute("size","5");
    node.setAttribute("onChange","daysago(this)");
    ele.parentNode.replaceChild(node,ele);
    node.focus();
  } else {
    var my=''+self.location;
    if (my.match(/ago=/,my))
      my=my.replace(/ago=\d+/,'ago='+obj.value);
    else
      my+='?ago='+obj.value;
    self.location=my;
  }
  return false;
}
