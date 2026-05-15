
function loading(id) {
    document.getElementById( id ).innerHTML = "<div id='loader'></div>";
}
function getRange(clicked_id) {
	if (typeof clicked_id == "undefined") {
		var range = document.getElementById("range").value;
	} else {
		var range = clicked_id;
	}
	if (window.XMLHttpRequest) {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
			document.getElementById("mgrResults").innerHTML = this.responseText;
		}
	}

	loading("mgrResults");

	xmlhttp.open("GET", range + ".php", true);
	xmlhttp.send();

}
function fetchURL(clicked_id) {	
	if (clicked_id.includes("?")) {		
		var pieces = clicked_id.split("?");
		clicked_id = pieces[0];
		var params = pieces[1];
		var temp;
		var parsed = "";
        var returnDiv = "";
		if (params.includes("&")) {
			var vars = params.split("&");
			for (let i=0; i<vars.length; i++) {
				if (vars[i].includes("=")) {
					pairs = vars[i].split("=");
					if (pairs[1] == "") {
						parsed += pairs[0] + "=";
						if (!document.getElementById(pairs[0]).value) {
							console.log(pairs[0]);
							if (!confirm("Missing Value for " + pairs[0] + ". To continue anyway, click Continue. Otherwise, click Cancel to correct")) {
								return false;
								} else {
									parsed += "&";
								}
						} else {
							parsed += document.getElementById(pairs[0]).value + "&";
						}						
					} else {
                        if (pairs[0] == "confirm") {
                            var message = pairs[1].split("_").join(" ");
                            message = message.replace("/", "?");
                            if (confirm(message) == false) {
                                return false;
                            }
                        } else if (pairs[0] == "return") {
                            returnDiv = pairs[1];
                        } else if (pairs[0] == "required") {
                            if (!document.getElementById(pairs[1]).value) {
                                alert("A value for " + pairs[1] + " is required.");
                                return false;
                            }
                        } else {
                            parsed += (pairs[0] + "=" + pairs[1] + "&");
                        }						
					}
				} else {
					parsed += vars[i] + "&";
				}
			}
		} else if (params.includes("=")) {
			pairs = params.split("=");
			if (pairs[1] == "") {
				parsed += pairs[0] + "=";
				if (!document.getElementById(pairs[0]).value) {
					if (!confirm("Missing Value for " + pairs[0] + ". To continue anyway, click Continue. Otherwise, click Cancel to correct")) {
						return false;
						} else {
							parsed += "&";
						}
				} else {
					parsed += document.getElementById(pairs[0]).value + "&";
				}						
			} else {
				parsed += (pairs[0] + "=" + pairs[1] + "&");
			}
		}
		if (parsed !== "") {
			params = parsed.substring(0, parsed.length - 1);
		}
	}
	if (window.XMLHttpRequest) {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
    if (returnDiv == "") {
        returnDiv = "mgrResults";
    } 
	xmlhttp.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
			document.getElementById(returnDiv).innerHTML = this.responseText;
		}
	}

	loading(returnDiv);

	if (params) {
		xmlhttp.open("GET", "" + clicked_id + ".php?" + params, true);
	} else {
		xmlhttp.open("GET", "" + clicked_id + ".php", true);
	}
	
	xmlhttp.send();

}

function duplicateDropdownVals() {
	var dropdowns = document.querySelectorAll('select');
	var selectedValues = {};

	dropdowns.forEach(function(dropdown) {
		var selectedValue = dropdown.value;
		if (selectedValue !== '') { // Check if the value is not blank
			if (selectedValues[selectedValue]) {
				dropdown.style.backgroundColor = 'yellow'; // Highlight the dropdown
				selectedValues[selectedValue].style.backgroundColor = 'yellow'; // Highlight the other dropdown with the same value
			} else {
				dropdown.style.backgroundColor = ''; // Reset background color if not a duplicate
			}
			selectedValues[selectedValue] = dropdown;
		} else {
			dropdown.style.backgroundColor = ''; // Reset background color for blank value
		}
	});
}


function checkLeadType() {
	var typeField = document.getElementById('type');
	var campField = document.getElementById('camp');
	var deptField = document.getElementById('dept');

	if (typeField.value === 'ROUST') {
		campField.removeAttribute('required');
		deptField.removeAttribute('required');
		campField.setAttribute('disabled', 'disabled');
		deptField.setAttribute('disabled', 'disabled');
	} else {
		campField.setAttribute('required', 'required');
		deptField.setAttribute('required', 'required');
		campField.removeAttribute('disabled');
		deptField.removeAttribute('disabled');
	}
}

function uploadList() {
	if (window.XMLHttpRequest) {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
			document.getElementById("mgrResults").innerHTML = this.responseText;
		}
	}

	var camp = document.getElementById("camp").value;
	var name = encodeURIComponent(document.getElementById("name").value);
	var dept = document.getElementById("dept").value;
	var type = document.getElementById("type").value;
	var file = document.getElementById("file").value;

	loading("mgrResults");
	
	xmlhttp.open("POST", "Lists/uploadLists.php", true);
	xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xmlhttp.send("upload&camp=" + camp + "&name=" + name, true);
}

function getUpdateList(camp) {
	if (window.XMLHttpRequest) {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
			document.getElementById("campLists").innerHTML = this.responseText;
		}
	}
    
    document.getElementById("previewButton").disabled = true;

	loading("campLists");
    
	xmlhttp.open("GET", "Lists/listUpdater.php?getLists&camp=" + camp, true);
	xmlhttp.send();
}
function enableListUpdaterPreview() {
    document.getElementById("previewButton").disabled = false;
}


function checkDailySales() {
    
	var date = document.getElementById("date").value;
    
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
    
    loading("results");

	xmlhttp.open("GET", "Reports/Sales/uploadSales.php?date=" + date, true);
	xmlhttp.send();
}

function uploadSalesToNAMS() {
    
	var date = document.getElementById("date").value;
    
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
    
    loading("results");

	xmlhttp.open("GET", "Reports/Sales/dailySales_NAMS.php?date=" + date, true);
	xmlhttp.send();
    
}


function selectThis(clicked_id) {
    var pieces = clicked_id.split("|");
    document.getElementById(pieces[0]).value = pieces[1];
}

function dncAdd() {
	if (document.getElementById('dncNum').value == "" || document.getElementById('dncCamp').value == "") {
		alert('Please enter both a phone number and a campaign (or Global) and Submit');
		return false;
	}

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


	var number = encodeURIComponent(document.getElementById("dncNum").value);
	var camp = document.getElementById('dncCamp').value;

	loading("results");

	xmlhttp.open("GET", "Admin/addDNC.php?number=" + number + "&camp=" + camp, true);
	xmlhttp.send();

}

function amdCheckCamp(camp) {
    if(document.getElementById("curCamp")) {
        if (document.getElementById("curCamp").innerHTML !== camp) {
            document.getElementById("update").disabled = true;
        } else {
            document.getElementById("update").disabled = false;
        }
    }
}

function deleteQueue(id) {
	if (window.XMLHttpRequest) {
		// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function () {
		if (this.readyState == 4 && this.status == 200) {
			document.getElementById("mgrResults").innerHTML = this.responseText;
		}
	}

	loading("mgrResults");

	xmlhttp.open("GET", "Lists/checkQueue.php?deleteList&id=" + id, true);
	xmlhttp.send();
}


function updateSecDisplay() {
	var dispo = document.getElementById('dispo').value;
	var AMs = document.getElementById('AMs');
	var NIs = document.getElementById('NIs');

	if (dispo === 'A') {
		AMs.style.display = 'inline';
		NIs.style.display = 'none';
	} else if (dispo === 'NI') {
		AMs.style.display = 'none';
		NIs.style.display = 'inline';
	} else if (dispo === 'DEC') {
		AMs.style.display = 'none';
		NIs.style.display = 'none';
	} else {
		AMs.style.display = 'none';
		NIs.style.display = 'none';
	}
}

function triggerFullDayDPH() {
  const date = document.getElementById("import_date").value;
  if (!date) {
    alert("Please select a date.");
    return;
  }

  loading("mgrResults"); // your existing spinner logic

  const xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4 && xhr.status === 200) {
      document.getElementById("mgrResults").innerHTML = xhr.responseText;
    }
  };
  xhr.open("GET", "Admin/fullDay.php?date=" + encodeURIComponent(date), true);
  xhr.send();
}

function revertToOriginal() {
	if (!confirm("You are about to deactivate the Hourly DPH Report.. and activate the original DPH Report. Click OK to continue or Cancel")) {
		return false;
	};
  
	loading("mgrResults"); // your existing spinner logic
  
	const xhr = new XMLHttpRequest();
	xhr.onreadystatechange = function () {
	  if (xhr.readyState === 4 && xhr.status === 200) {
		document.getElementById("mgrResults").innerHTML = xhr.responseText;
	  }
	};
	xhr.open("GET", "Admin/revertToOriginal.php?revert", true);
	xhr.send();
  }
