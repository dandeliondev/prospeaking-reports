var intervalMS = 480000;
var showReportIntervalVar;


function reverseOrder(clicked_id) {

	var array = clicked_id.split("|");
	var order = array[1];

	if (array[0] != "LHR1") {
		if (order == "DESC") {
			document.getElementById(clicked_id).id = array[0] + "|ASC";
		} else {
			document.getElementById(clicked_id).id = array[0] + "|DESC";
		}
	} else {
		if (document.getElementById("LHR|ASC")) {
			document.getElementById("LHR|ASC").id = "LHR|ASC";
		} else {
			document.getElementById("LHR|DESC").id = "LHR|ASC";
		}
	}
}


//get collection stats from database -- sort by LHR
function showReport(clicked_id) {
	if (window.XMLHttpRequest) {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
			document.getElementById("results").innerHTML = this.responseText;
		}
	}
	var sort = document.getElementById("sort").innerHTML;
	if (clicked_id == "") {
		sort = "";
	} else 	if (clicked_id !== sort) {
		if (clicked_id.split("|")[0] == sort.split("|")[0]) {
			if (sort.split("|")[1] == "DESC") {
				sort = sort.split("|")[0] + "|ASC";
			} else {
				sort = sort.split("|")[0] + "|DESC";
			}
		} else {
			sort = clicked_id;
		}
	} else {
		if (sort.split("|")[1] == "DESC") {
			sort = sort.split("|")[0] + "|ASC";
		} else {
			sort = sort.split("|")[0] + "|DESC";
		}
	}
	if (sort !== "") {
		document.getElementById("sort").innerHTML = sort;
	}	
	var range = document.getElementById("range").value;
	var start = document.getElementById("start").value;
	var end = document.getElementById("end").value;
    if (start > end) {
        alert("Start Date cannot be after End Date");
        return false;
    }
	var team = document.getElementById("team").value;
	var cluster = document.getElementById("cluster").value;
	var agentCamp = document.getElementById("agentCamp").value;
	var leadType = document.getElementById("leadType").value;
	var agent = document.getElementById("agent").value;	
	var camp = document.getElementById("campaign").value;
	//var sb = document.getElementById("sb").checked;
	//var live = document.getElementById("live").checked;
	var list = document.getElementById("list").value;
	var combSB = document.getElementById("combSB").checked;
	//var hours = document.getElementById("hours").checked;
	var hours = Array.from(menu.querySelectorAll('input:checked')).map(cb => cb.value);
	hours = hours.join('|');
	hours = hours.replace("8", "7|8");
	hours = hours.replace("16", "16|17");

    loading("results");
	
	xmlhttp.open("GET", "getReport.php?range=" + range + "&start=" + start + "&end=" + end + "&team=" + team + "&cluster=" + cluster + "&camp=" + camp + "&agentCamp=" + agentCamp + "&leadType=" + leadType + "&agent=" + agent + /*"&sb=" + sb + "&live=" + live + */"&list=" + list + "&combSB=" + combSB + "&hours=" + hours + "&sort=" + sort, true);
	xmlhttp.send();
}


function showReportInterval(clicked_id) {
	document.getElementById("autoRefresh").checked = true;
	showUpdatedDate();
    checkSalesNoAmt();
	showReport(clicked_id);
	showReportIntervalVar = setInterval(function () {
		showReport(clicked_id);
		showUpdatedDate();
        checkSalesNoAmt();
	}, intervalMS);
}

//get Last Updated Date from database
function showUpdatedDate() {
	if (window.XMLHttpRequest) {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
			document.getElementById("UpdatedDate").innerHTML = this.responseText;
		}
	}
	var range = document.getElementById("range").value;
	var end = document.getElementById("end").value;


	xmlhttp.open("GET", "getTimeStamp.php?range=" + range + "&end=" + end, true);
	xmlhttp.send();
}

//get Last Updated Date from database
function checkSalesNoAmt() {
	if (window.XMLHttpRequest) {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
			document.getElementById("leftHeader").innerHTML = this.responseText;
		}
	}

	xmlhttp.open("GET", "checkSalesNoAmt.php", true);
	xmlhttp.send();
}

function clearIntervals() {
	document.getElementById("autoRefresh").checked = false;
	clearInterval(showReportIntervalVar);
}


//get collection stats from database -- sort by Average Wait
function resetOptions() {
    $( "#agent" ).val( "" );
    $( "#range" ).val( "DAILY" );
    $( "#list" ).val( "" );
    $( '#hours' ).prop( 'checked', false );
    $( '#combSB' ).prop( 'checked', false );
    $( '#range' ).css( 'display', "" );
    $( '#custom' ).css( 'display', "none" );
    $( '#start' ).val( "" );
    $( '#end' ).val( "" );
    $( '#leadType' ).val( "" );
    $( '#agentCamp' ).val( "" );
    $( '#campaign' ).val( "" );
    $( '#cluster' ).val( "" );
    $( '#team' ).val( "" );
    $('#hourSelect input[type=checkbox]').prop('checked', false);           // uncheck all
    $('#hourSelect .multiselect-toggle').text('--Hour--');                // reset label
    $('#hourSelect .multiselect-menu').hide();   
}

function loading(elem) {
    document.getElementById( elem ).innerHTML = "<div id='loader'></div>";
}

function checkCustom(value) {
    if (value == "CUSTOM") {
        document.getElementById("range").style.display = "none";
        document.getElementById("custom").style.display = "";
    }
    if (value == "RESET") {
        document.getElementById("range").style.display = "";
        document.getElementById("range").value = "DAILY";
        document.getElementById("custom").style.display = "none";
        document.getElementById("start").value = "";
        document.getElementById("end").value = "";
    }
}

function resetViews(id) {
    if (id == "agentCamp") {
        $( '#agent' ).val( "" );
    } else {
         $( '#agentCamp' ).val( "" );
    }
}

function updateDropDowns(id) {
	let selectElements = document.getElementsByTagName("select");
	for (let i = 0; i < selectElements.length; i++) {
		selectElements[i].disabled = true; 
	}

	var range = document.getElementById("range").value;
	var start = document.getElementById("start").value;
	var end = document.getElementById("end").value;
	var cluster = document.getElementById("cluster").value;	
	var list = document.getElementById("list").value;
	var agentCamp = document.getElementById("agentCamp").value;
	var leadType = document.getElementById("leadType").value;
	var agent = document.getElementById("agent").value;
	var campaign = document.getElementById("campaign").value;
	if (window.XMLHttpRequest) {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
            var p = this.responseText.split("|");
			document.getElementById("cluster").innerHTML = p[0];
			document.getElementById("list").innerHTML = p[1];
			document.getElementById("agentCamp").innerHTML = p[2]; 
			document.getElementById("campaign").innerHTML = p[3]; 
			document.getElementById("leadType").innerHTML = p[4];
			document.getElementById("agent").innerHTML = p[5]; 
			for (let i = 0; i < selectElements.length; i++) {
				selectElements[i].disabled = false; 
			}
		}
	}

	xmlhttp.open("GET", "getDropDowns.php?range=" + range + "&start=" + start + "&end=" + end + "&cluster=" + cluster + "&list=" + list + "&agentCamp=" + agentCamp + "&leadType=" + leadType + "&agent=" + agent + "&campaign=" + campaign, true);
	xmlhttp.send();
}
