/*
	Based	on	the ground-breaking DOM	work of Andrew	Porter Glendinning @	http://www.serve.com/apg/
	See the original version of this	@ http://glendinning.org/webbuilder/sortTable/
*/


var sortedOn = -1;	/* Bug in original assumed first column was already sorted (=0) */

function SortTable(sortOn) {

	var br = navigator.userAgent.toLowerCase();

	if ((br.indexOf("msie 5") != -1) && (br.indexOf("mac_") != -1)) {
			var msg='Unfortunately, the column sort feature is '+
				'incompatible with IE 5.x on the Mac.  We are '+
				'against browser detection on general principle, but '+
				'literally, this feature will CRASH your browser. '+
				'Safari, Opera, and Mozilla (and probably any other '+
				'modern browser) do NOT have this limitation.  If '+
				'you are really angry, please feel free to Use the Source '+
				'and submit a bug fix to codetrack@openbugs.org';
	
	   alert(msg);
		return(false); 
	}


	/*	document.getElementById('resultsFooter').style.visibility = "hidden"; */

	var table =	document.getElementById('results');
	var tbody =	table.getElementsByTagName('tbody')[0];
	var rows	= tbody.getElementsByTagName('tr');

	var rowArray =	new Array();
	for (var	i=0, length=rows.length; i<length; i++) {
			rowArray[i]	= new	Object;
			rowArray[i].oldIndex	= i;
			/* Next line was originally firstChild, but wrong if leading a href link exists inside TD */
			rowArray[i].value	= rows[i].getElementsByTagName('td')[sortOn].lastChild.nodeValue;
	}

	if	(sortOn == sortedOn)	{
		 rowArray.reverse();
	}
	else {
		sortedOn	= sortOn;
		rowArray.sort(RowCompare);
	}

	var newTbody =	document.createElement('tbody');
	for (i=0, length=rowArray.length; i<length;	i++) {
			newTbody.appendChild(rows[rowArray[i].oldIndex].cloneNode(true));
	}
	table.replaceChild(newTbody, tbody);


/* Added lines below to restore alternating styles on each TR background color */

	tbody =	table.getElementsByTagName('tbody')[0];
	rows	= tbody.getElementsByTagName('tr');

	for (i=0, length=rows.length; i<length; i++)
			rows[i].className = ( (i%2) ? "rowOdd" :	"rowEven" );
}

function RowCompare(a, b)	{

	if ( (parseInt(a.value, 10 )) && (parseInt(b.value, 10)) )		/* Don't misread leading zeros as octal, i.e., 0132 */
		return (parseInt(a.value, 10) - parseInt(b.value, 10));

	var aVal = a.value.toLowerCase();
	var bVal = b.value.toLowerCase();

	return (aVal == bVal	? 0 :	(aVal > bVal ?	1 : -1));

}
