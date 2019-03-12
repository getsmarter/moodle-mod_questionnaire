setTimeout(function() { 
    var button = document.getElementsByClassName('button button-md button-default button-default-md button-block button-block-md');
    var allRangeCheck = document.getElementsByClassName('hidden-submit-button-check-false');
    var rangeSliderNaCheck = document.getElementById('range-ranges');
    var allSliders = document.getElementsByClassName('range range-md');

    if(typeof(button.mod_questionnaire_submit_questionnaire_response) != 'undefined' && allRangeCheck.length == 0) { //basic idea behind the validation for the button hiding logic, using disabled for now since it's an option in ionic
        button.mod_questionnaire_submit_questionnaire_response.disabled = true;
    }
    if(typeof(rangeSliderNaCheck) != 'undefined' && typeof(rangeSliderNaCheck) != null) {
        var allNaApplicableSliders = document.getElementsByClassName('na-applicable');
        for(var x = 0; x < allNaApplicableSliders.length; x++) {
            allNaApplicableSliders[x].innerHTML = 'n/a';
        }
    }
    var requiredInputs = []; //required inputs, this is an array with references to the required inputs for the questionnaire
    window.clicked_input = e => {
        checkIfFinalRequiredResponse(e, requiredInputs);
    };
    var checkboxes = document.getElementsByClassName('questionnaire-checkbox-checked');
    for(var i = 0; i < checkboxes.length; i++) {  
        checkboxes[i].childNodes[0].className+= ' ' + 'checkbox-checked';
    };

    var targetNodes = document.getElementsByClassName('range range-md');
    var observerOptions = {
      childList: true,
      attributes: true,
      subtree: true //Omit or set to false to observe only changes to the parent node.
    }

    for(x = 0; x < targetNodes.length; x++) {
        var observer = new MutationObserver(callback);
        observer.observe(targetNodes[x], observerOptions);
    }

    

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
    var button = document.getElementsByClassName('button button-md button-default button-default-md button-block button-block-md');
    if(requiredInput === true && numberOfRequiredAnswers ==  finalRequiredAnswer && typeof(button.mod_questionnaire_submit_questionnaire_response) != 'undefined') {
        button.mod_questionnaire_submit_questionnaire_response.disabled = false;
    }
}

function callback(mutationList, observer) {
  mutationList.forEach((mutation) => {
    switch(mutation.type) {
      case 'childList':
      console.log('childlist');
        /* One or more children have been added to and/or removed
           from the tree; see mutation.addedNodes and
           mutation.removedNodes */
        break;
      case 'attributes':
      console.log('childlist');
        /* An attribute value changed on the element in
           mutation.target; the attribute name is in
           mutation.attributeName and its previous value is in
           mutation.oldValue */
        break;
    }
  });
}