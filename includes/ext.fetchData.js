mw.loader.load("ext.cookie");

function chanceToHuman ( chance, type, time){
    if (type === "micromorts") {
    	// micromort in 1 in a million chance, so divide by a million
        chance = 1000000 / chance;
        // convert from each day to each month
        chance = chance * time;
    } else {
    	// convert from given time span to 1 month
    	chance = chance / time;
    	// convert from probability to "1 in x" format
    	chance = (1/chance); 
    }
    // round nicely and return
    if (chance > 1000) {
	    let digits = Math.round(chance).toString().length;
	    let rounded = Math.round(chance / Math.pow(10, digits - 2)) * Math.pow(10, digits - 2);
	    return "1 in " + rounded.toLocaleString();
    } else {
    	return "1 in " + Math.round(chance.toLocaleString());
    }
}

function validateClasses(classes, thisSpan) {
    // make sure we have the right number of arguments
    if (classes.length !== 4){
        thisSpan[0].innerHTML = "<p style=\"color:red\">FETCH ERROR: Bad number of classes found. format must be {{#fetchdata:table_name|data_type(micromorts/probability)|column_name(string)|time_span(Integer in months)}}.</p>";
        return false;
    }
    // make sure the given table name is actually valid
    try{ 
        window["dt2_" + classes[0]][0];
    } catch (error) {
        thisSpan[0].innerHTML = "<p style=\"color:red\">FETCH ERROR: dt2_" + classes[0] + " is not a valid table name.</p>";
        return false;
    }
    // make sure the data type is either micromorts or probability
    if (classes[1] !== "micromorts" && classes[1] != "probability"){
        thisSpan[0].innerHTML = "<p style=\"color:red\">FETCH ERROR: " + classes[1] + " is not a valid data type. Use either \"probability\" or \"micromorts\".</p>";
        return false;
    }
    // make sure the column name given is valid
    if ( window["dt2_" + classes[0]][0][classes[2]] === undefined ){
        thisSpan[0].innerHTML = "<p style=\"color:red\">FETCH ERROR: "+ classes[2] + " is not a valid column name.</p>";
        return false;
    }
    //make sure the time span is a valid number of months
    if (!/^\d+$/.test(classes[3]) || classes[3] <= 0){
        thisSpan[0].innerHTML = "<p style=\"color:red\">FETCH ERROR: Input a positive time span in months. Recieved: " + classes[3] + ".</p>";
        return false;
    }

}

fetchData = function (){
    // check for user age cookie
    let user_age = parseInt(RT.cookie.getCookie("userAge"), 10);
    // if user age not found, use default of 30
    if (user_age === -1 || user_age === "") user_age = 30;
    // loop through each <span class="fetchData ...">
    $('.fetchData').each( function() {
        // get list of classes from span as an array, slice out the fetchData class
        let classes = $(this)[0].className.split(' ').slice(1);

        if (validateClasses(classes, $(this)) === false) return true; // return true to continue to the next iteration
        
        
        
        let range;
        let found_row = 0;
        let i = -1;
        windowArray = window["dt2_" + [classes[0]]]
        // loop through row of array specified by classes[0]
        for ( let row of windowArray ) {
            
            if (found_row !== 0) break;
            // rows have age ranges "65-69"
            range = row.Age;
            range = range.split('-');
            for (let x = 0; x < range.length; x++){
                range[x] = parseInt(range[x], 10);
            };
            if ( user_age === 0 ){
                found_row = row;
            } else if ( user_age >= range[0] && user_age <= range[1] ){
                found_row = row;
            };
            i++;
        };
        // if a matching row is never found, use the last row
        let found_value;
        if (found_row === 0){
            found_row = windowArray.at(-1);
            found_value = found_row[classes[2]];
        } else {
            // This code is to estimate data for age values that are between rows 
            // find the age range of the row
            let rangeDiff
            if (range.length === 1) {
                rangeDiff = 1;
            } else {
                rangeDiff = 1 + range[1] - range[0];
            };
            // find the probability difference between this row and the next
            let probDiff = windowArray[i + 1][classes[2]] - found_row.qx;
            // divide the difference by the age range
            let ratio = probDiff / rangeDiff;
            // find where in the age range the user is
            let userDiff = user_age - range[0];
            // multiply the user's age by the divided difference
            userDiff = userDiff * ratio;
            // add that value to the value in found row
            found_value = parseFloat(found_row[classes[2]]) + userDiff;
        }
        console.log(found_value);
        
    
        $(this)[0].innerHTML = 
                    "<span>" + chanceToHuman( found_value, classes[1], classes[3] ) + "</span>";
    });
}

window.RT = window.RT || {};
window.RT.fetchData = fetchData;