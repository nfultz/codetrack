/*  Create label property for validation message on required fields in new/edited user form  */
/*  Has to be done in flow of HTML, not onsubmit due to bugs in Opera and some NN            */

if (document.getElementById) {
	
	var f = document.getElementById('userForm');

	f['user_data[First_Name]'].focus();
	
	f['user_data[First_Name]'].req_field_label = "User's first name";
	f['user_data[Last_Name]'].req_field_label = "User's last name";
	f['user_data[Email]'].req_field_label	= "e-mail Address";

	/*
	f['user_data[Phone]'].req_field_label = 'Phone number';
	f['user_data[Password]'].req_field_label = 'An initial password';
	*/

}