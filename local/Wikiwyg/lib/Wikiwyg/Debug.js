//------------------------------------------------------------------------------
// Debugging Support
//------------------------------------------------------------------------------

function XXX(msg) {
    if (! confirm(msg))
        throw("terminated...");
}

function JJJ(obj) {
    XXX(JSON.stringify(obj));
}


