/*    Inspired by O'Reilly's Javascript library and Netscape's Developer
      Forum fabulous cross-browser JavaScript validation library				*/


function isblank(s) {
	for(var i = 0; i < s.length; i++) {
		var c = s.charAt(i);
		if ((c != ' ') && (c != '\n') && (c != '\t'))
			return false;
	}
	return true;
}

function checkMissing(f) {

	var msg;
	var empty_fields = '';
	var e;

	for (var i = 0; i < f.length; i++) {

		e = f.elements[i];

/*			alert ("Looping element # " + i + " NAME =" + f.elements[i].name +
					 " req_field_label =" + f.elements[i].req_field_label +
                " TYPE =" + f.elements[i].type + "\n VALUE =" + f.elements[i].value);
*/

		if (!e.req_field_label)
			continue;

		if ( (e.type == "text") || (e.type == "textarea") ) {
			if ( (e.value == null) || (e.value == "") || isblank(e.value) ) {
				empty_fields += "\n          " + e.req_field_label;
				continue;
			}
		}
		else if ( e.type == "select-one" ) {
			if(e.selectedIndex == 0) {
				empty_fields += "\n          " + e.req_field_label;
					continue;
			}
		}
	}
	if (!empty_fields)
		return true;

	msg  = "______________________________________________________\n\n"
	msg += "Your informaton was not saved because of the following errors.\n";
	msg += "Please correct these errors and re-save.\n";
	msg += "______________________________________________________\n\n"
	msg += "- The following required fields are empty:\n" + empty_fields + "\n\n";

	alert(msg);
	return false;
}


var whitespace = " \t\n\r";	/*  Needed for functions below  */


function stripCharsInBag(s, bag) {

	var i;
	var returnString = "";

    for (i = 0; i < s.length; i++) {
        var c = s.charAt(i);
        if (bag.indexOf(c) == -1)
				returnString += c;
    }
    return returnString;
}


function stripCharsNotInBag(s, bag) {

	var i;
	var returnString = "";

	for (i = 0; i < s.length; i++) {
		var c = s.charAt(i);
		if (bag.indexOf(c) != -1)
			returnString += c;
	}

	return returnString;
}


function isWhitespace(s) {

	if (isEmpty(s))
		return true;

	var i;

	for (i = 0; i < s.length; i++) {
		var c = s.charAt(i);
		if (whitespace.indexOf(c) == -1)
			return false;
	}
	return true;
}


function isEmpty(s) {
	return ((s == null) || (s.length == 0))
}


function isDigit(c) {
	return ((c >= "0") && (c <= "9"))
}


function isInteger(s) {

	if ( isEmpty(s) )
		return false;

	var i;

    for (i = 0; i < s.length; i++) {
        var c = s.charAt(i);
        if (!isDigit(c))
				return false;
    }
    return true;
}


function validPhone(p) {

	/* Dependencies:  isDigit(), isInteger(), stripCharsInBag   */

	var normalizedPhone = stripCharsInBag(p, "()-. ");

       if (isInteger(normalizedPhone) && normalizedPhone.length == 10)
			return true;
       else {
			alert("The phone number is invalid. Make sure you included an area code.\nAny reasonable phone number format will do, such as (555) 123-4567 or 555-123-4567");
			return false;
		}
}


function isEmail(s) {

	if (isEmpty(s))
		return false;

	if (isWhitespace(s))
		return false;

	if ( stripCharsInBag(s, "@-_.ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789") )
		return false;

	if ( s.indexOf("..") != -1 )
		return false;

	if ( s.indexOf("@@") != -1 )
		return false

	var i = 1;
	var sLength = s.length;

	while ((i < sLength) && (s.charAt(i) != "@")) {
		i++;
   }

	if ((i >= sLength) || (s.charAt(i) != "@"))
		return false;
	else
		i += 2;

	while ((i < sLength) && (s.charAt(i) != ".")) {
		i++;
	}

	if ((i >= sLength - 1) || (s.charAt(i) != "."))
		return false;
	else
		return true;
}

function validEmail(e) {

	if (isEmail(e))
		return true;
	else {
		alert("The e-mail address is invalid.\nA valid example is foo@myorg.com");
		return false;
	}
}

function checkPasswords(f, pLength) {

	var newPassword			= f['user_data[Password]'].value;
	var newPasswordRetyped	= f.retyped_pw.value;

	if ( f.old_pw ) {		/*  Only check old value if element exists (won't for admins)  */

		var oldPassword	= f.old_pw.value;

		if ( isEmpty(oldPassword) ) {
			alert("Please type your old password.\nWe need it to verify that this is really you.");
			return false;
		}
	}

	if ( isEmpty(newPassword) ) {
		alert("Please enter a new password.");
		return false;
	}

	if ( isEmpty(newPasswordRetyped) ) {
		alert("Please retype the new password in the \"Confirmation\" box.\nEntering it twice helps to prevent typos.");
		return false;
	}

	if ( newPassword != newPasswordRetyped ) {
		alert("The new password and confirmation do not match.  Please re-enter them.");
		return false;
	}

	return passwordIsLongEnough( newPassword, pLength );
}


function passwordIsLongEnough(p, pLength) {

	if ( p.length < pLength ) {
		alert("The password should be at least " + pLength + " characters or more.");
		return false;
	}
	return true;
}	


function toggle_checkboxes(f) {

        for (var i = 0; i < f.length; i++) {
                e = f.elements[i];

                if (e.type != "checkbox")
                        continue;

                if (e.name == "toggleBoxes")            // Don't toggle the master toggle switch
                        master_state=e.checked;
                else
                        e.checked = master_state;
        }
}
