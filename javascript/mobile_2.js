setTimeout(function() { 
    console.log("DOM is available now, yes yes it is"); 

    // var button = document.getElementsByClassName("button button-md button-default button-default-md button-block button-block-md");
    // button.mod_questionnaire_submit_questionnaire_response.hidden = false;
    
    window.clicked_input = e => {
        checkIfFinalRequiredResponse(e);
    };

}, 300);

function checkIfFinalRequiredResponse (e) {

    if(e[0] == e[1]) { //basic idea behind the validation for the button hiding logic, using disabled for now since it's an option in ionic
        var button = document.getElementsByClassName("button button-md button-default button-default-md button-block button-block-md");
        button.mod_questionnaire_submit_questionnaire_response.disabled = false;
        var button = document.getElementsByClassName("button button-md button-default button-default-md button-block button-block-md");
        button.mod_questionnaire_submit_questionnaire_response.hidden = false;
    }

}