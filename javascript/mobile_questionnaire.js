setTimeout(function() { 
    var button = document.getElementsByClassName("button button-md button-default button-default-md button-block button-block-md");
    if(typeof(button.mod_questionnaire_submit_questionnaire_response) != 'undefined') { //basic idea behind the validation for the button hiding logic, using disabled for now since it's an option in ionic
        button.mod_questionnaire_submit_questionnaire_response.disabled = true;
    }
    var requiredInputs = []; //required inputs, this is an array with references to the required inputs for the questionnaire
    window.clicked_input = e => {
        checkIfFinalRequiredResponse(e, requiredInputs);
    };
}, 300);

function checkIfFinalRequiredResponse (e, requiredInputs) {
    if(!requiredInputs.includes(e[0])) {
        requiredInputs.push(e[0]); //only push if it has not been added to the array already
    }
    
    var finalRequiredAnswer = e[1];
    var numberOfRequiredAnswers = 0;
    for(var x = 0; x < requiredInputs.length; x++) {
       //first need to check that all answers before required answer are in array
       //then set a flag that I can check later
        if(requiredInputs[x] <= finalRequiredAnswer) { //checking if the inputs are less than the required input or the required input
            //and increment initial check
            numberOfRequiredAnswers++;
        } 
    }

    var requiredInput = false;
    if(requiredInputs.includes(e[1])) {
        requiredInput = true;
    }
    var button = document.getElementsByClassName("button button-md button-default button-default-md button-block button-block-md");
    if(requiredInput === true && numberOfRequiredAnswers ==  finalRequiredAnswer && typeof(button.mod_questionnaire_submit_questionnaire_response) != 'undefined') {
        button.mod_questionnaire_submit_questionnaire_response.disabled = false;
    }
}