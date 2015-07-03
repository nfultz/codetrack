/*  Create label property for validation message on required fields in new/edited bug form   */
/* Has to be done in flow of HTML, not onsubmit due to bugs in Opera and some NN             */

if (document.getElementById) {

	/*  Alternative technique is: document.forms['passwordForm'].elements['user_data[Password]'].focus();  */
	
	var f = document.getElementById('bF');

	f['bug_data[Module]'].focus();

	f['bug_data[Severity]'].req_field_label     = 'Severity';
	f['bug_data[Summary]'].req_field_label      = 'Summary of Problem';
	f['bug_data[Description]'].req_field_label  = 'Full Description';
	f['bug_data[Submitted_By]'].req_field_label = 'Submitting User';

}
