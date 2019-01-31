setTimeout(function() { 
    var button = document.getElementsByClassName("button button-md button-default button-default-md button-block button-block-md");
    if(typeof(button.mod_questionnaire_submit_questionnaire_response) != 'undefined') { //basic idea behind the validation for the button hiding logic, using disabled for now since it's an option in ionic
        button.mod_questionnaire_submit_questionnaire_response.hidden = true;
    }
    window.clicked_input = e => {
        checkIfFinalRequiredResponse(e);
    };
}, 300);

function checkIfFinalRequiredResponse (e) {
    var button = document.getElementsByClassName("button button-md button-default button-default-md button-block button-block-md");
    if(e[0] == e[1] && typeof(button.mod_questionnaire_submit_questionnaire_response) != 'undefined') { //basic idea behind the validation for the button hiding logic, using disabled for now since it's an option in ionic
        button.mod_questionnaire_submit_questionnaire_response.hidden = false;
    }
}