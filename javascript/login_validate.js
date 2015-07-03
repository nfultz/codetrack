
/*   Because Opera is inconsistent on username node instantiation, and NN4 is just stupid...   */

var f = document.forms[0].elements[1];
f.focus();


function validateLogin(f) {

	if ( !f['userLogin[username]'].value ) {
		alert("A username is required to access this system.");
		f['userLogin[username]'].focus();
		return false;
	}
	if ( f['userLogin[password]'].value.length < 1 ) {
		alert("A valid password is required to access this system.");
		f['userLogin[password]'].focus();
		return false;
	}
  else
	 return true;
}