/*  Create label property for validation message on required fields in new/edited project form  */
/*  Has to be done in flow of HTML, not onsubmit due to bugs in Opera and some NN            	*/

if (document.getElementById) {

	var f = document.getElementById('projectForm');

	f['project_data[Title]'].focus();
	f['project_data[Title]'].req_field_label = "A one or two word project Title";
	f['project_data[Description]'].req_field_label = "A brief Description of the project";

}


