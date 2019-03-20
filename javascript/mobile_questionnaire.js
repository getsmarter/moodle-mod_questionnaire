var requiredInputs = [];

setTimeout(function() {

    var button = document.getElementsByClassName('button button-md button-default button-default-md button-block button-block-md');
    var disableSaveButton = document.getElementsByClassName('hidden-submit-button-check-true');
    var allSliders = document.getElementsByClassName('range range-md');
    var nextButton = document.getElementsByClassName('next-button');
    var allCheckboxes = document.getElementsByClassName('item item-block item-md item-checkbox');

    if(typeof(button.mod_questionnaire_submit_questionnaire_response) != 'undefined' && disableSaveButton.length > 0) { //basic idea behind the validation for the button hiding logic, using disabled for now since it's an option in ionic
        button.mod_questionnaire_submit_questionnaire_response.disabled = true;
    }

    if(typeof(nextButton) != 'undefined' && nextButton.length > 0) {
        for(var x = 0; x < nextButton.length; x++) {
            nextButton[x].disabled = true;
        }
    }

    // var requiredInputs = []; //required inputs, this is an array with references to the required inputs for the questionnaire
    window.clicked_input = e => {
        checkIfFinalRequiredResponse(e);
    };
    var checkboxes = document.getElementsByClassName('questionnaire-checkbox-checked');
    for(var i = 0; i < checkboxes.length; i++) {  
        checkboxes[i].childNodes[0].className+= ' ' + 'checkbox-checked';
    };

    var allNaApplicableSliders = document.getElementsByClassName('na-applicable'); //when a user chooses N/A on slider
    if(typeof(allNaApplicableSliders) != 'undefined' && allNaApplicableSliders.length > 0) {
        var completedSliders = document.getElementsByClassName('na-applicable na-completed');
        if(typeof(completedSliders) != 'undefined' && completedSliders.length > 0) {
            for(var i = 0; i < completedSliders.length; i++){
                for(var x = 0; x < allSliders.length; x++) {
                    var naCheck = typeof(allSliders[x].getAttribute('data-na')) != 'undefined';
                    if(allSliders[x].getAttribute('max') == allSliders[x].getAttribute('ng-reflect-model')) {
                        completedSliders[x].innerHTML = 'N/A';
                    }
                }
                break;
            }
        }

        var observerOptions = {
            childList: true,
            attributes: true,
            subtree: true, //Omit or set to false to observe only changes to the parent node.
            characterData: true,
        }
        for(var x = 0; x < allNaApplicableSliders.length; x++) {
            var observer = new MutationObserver(callback);
            observer.observe(allNaApplicableSliders[x], observerOptions);
        }
    }

    if(typeof(allSliders) != 'undefined' && allSliders.length > 0) { //NA onload 
        for(var x = 0; x < allSliders.length; x++) {
            var counter = 1;
            //here I need to check if it is na applicable
            var naCheck = allSliders[x].getAttribute('data-na');
            for(var i = 0; i < allSliders[x].childNodes[1].children.length - 3; i++) {

                if(naCheck && allSliders[x].getAttribute('max') == (i + 1)) {
                    allSliders[x].childNodes[1].children[i].innerHTML = '<p style="margin-left: -15px; width: 25px;">N/A</p>';
                } else {
                    allSliders[x].childNodes[1].children[i].innerHTML = counter;
                }
                
                allSliders[x].childNodes[1].children[i].style.paddingTop = '10px';
                allSliders[x].childNodes[1].children[i].style.width = '0px';
                counter++;
            }
        }

        var observerOptions = {
            childList: true,
            attributes: true,
            subtree: true, //Omit or set to false to observe only changes to the parent node.
            characterData: true,
        }
        for(var x = 0; x < allSliders.length; x++) {
            var observer = new MutationObserver(sliderObserver);
            observer.observe(allSliders[x], observerOptions);
        }
    }

    if(typeof(allCheckboxes) != 'undefined' && allCheckboxes.length > 0) { //NA onload 
        var observerOptions = {
            childList: true,
            attributes: true,
            subtree: true, //Omit or set to false to observe only changes to the parent node.
            characterData: true,
        }
        for(var x = 0; x < allCheckboxes.length; x++) {
            var observer = new MutationObserver(checkboxObserver);
            observer.observe(allCheckboxes[x], observerOptions);
        }
    }
}, 300);

function checkIfFinalRequiredResponse (e) {
    if(!requiredInputs.includes(e[0])) {
        requiredInputs.push(e[0]); //only push if it has not been added to the array already
    }
    
    var finalRequiredAnswer = e[1];
    var numberOfRequiredAnswers = 0;
    for(var x = 0; x < requiredInputs.length; x++) {
       //first need to check that all answers before required answer are in array
       //then set a flag that I can check later
        numberOfRequiredAnswers++;
    }

    var requiredInput = false;
    if(requiredInputs.includes(e[1])) {
        requiredInput = true;
    }

    var button = document.getElementsByClassName('button button-md button-default button-default-md button-block button-block-md');
    var nextButton = document.getElementsByClassName('next-button button button-md button-outline button-outline-md button-block button-block-md');
    if(requiredInput === true && numberOfRequiredAnswers ==  finalRequiredAnswer && typeof(button.mod_questionnaire_submit_questionnaire_response) != 'undefined') {
        button.mod_questionnaire_submit_questionnaire_response.disabled = false;
    } 
    if(requiredInput === true && numberOfRequiredAnswers == finalRequiredAnswer && typeof(nextButton) != 'undefined') {
        for(var i = 0; i < nextButton.length; i++){
            nextButton[i].disabled = false;
        }
    }
}

function callback(mutationList, observer) {
  mutationList.forEach((mutation) => {
    switch(mutation.type) {
        case 'characterData':
        var restingNaValue = mutation.target.parentElement.getAttribute('data-final');
        var currentNaValue = mutation.target.data;
        if(currentNaValue == restingNaValue) {
            mutation.target.data = 'n/a';
        }
        break;
    }
  });
}

function sliderObserver(mutationList, observer) {
  mutationList.forEach((mutation) => {
    switch(mutation.type) {
        case 'characterData':
        var currentRequiredValue = mutation.target.parentElement.parentElement.parentElement.parentElement.getAttribute('data-currentinput');
        var finalRequiredInput = mutation.target.parentElement.parentElement.parentElement.parentElement.getAttribute('data-finalinput');

        if(!requiredInputs.includes(currentRequiredValue)) {
            requiredInputs.push(currentRequiredValue); //only push if it has not been added to the array already
        }

        var numberOfRequiredAnswers = 0;
        for(var x = 0; x < requiredInputs.length; x++) {
           //first need to check that all answers before required answer are in array
           //then set a flag that I can check later
           numberOfRequiredAnswers++;
        }

        var requiredInput = false;
        if(requiredInputs.includes(finalRequiredInput)) {
            requiredInput = true;
        }

        var button = document.getElementsByClassName('button button-md button-default button-default-md button-block button-block-md');
        var nextButton = document.getElementsByClassName('next-button button button-md button-outline button-outline-md button-block button-block-md');

        if(requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(button.mod_questionnaire_submit_questionnaire_response) != 'undefined') {
            button.mod_questionnaire_submit_questionnaire_response.disabled = false;

        } 
        if(requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(nextButton) != 'undefined') {
             for(var i = 0; i < nextButton.length; i++){
                nextButton[i].disabled = false;
            }
        }

        break;
    }
  });
}

function checkboxObserver(mutationList, observer) {
  mutationList.forEach((mutation) => {

    switch(mutation.type) {
        case 'attributes':

            var currentRequiredValue = mutation.target.parentElement.getAttribute('data-currentinput');
            var finalRequiredInput = mutation.target.parentElement.getAttribute('data-finalinput');

            if(!currentRequiredValue && !finalRequiredInput) {
                return;
            }   

            if(!requiredInputs.includes(currentRequiredValue)) {
                requiredInputs.push(currentRequiredValue); //only push if it has not been added to the array already
            }

            var numberOfRequiredAnswers = 0;
            for(var x = 0; x < requiredInputs.length; x++) {
               //first need to check that all answers before required answer are in array
               //then set a flag that I can check later
               numberOfRequiredAnswers++;
            }

            var requiredInput = false;
            if(requiredInputs.includes(finalRequiredInput)) {
                requiredInput = true;
            }

            var button = document.getElementsByClassName('button button-md button-default button-default-md button-block button-block-md');
            var nextButton = document.getElementsByClassName('next-button button button-md button-outline button-outline-md button-block button-block-md');

            if(requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(button.mod_questionnaire_submit_questionnaire_response) != 'undefined') {
                button.mod_questionnaire_submit_questionnaire_response.disabled = false;

            } 
            if(requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(nextButton) != 'undefined') {
                 for(var i = 0; i < nextButton.length; i++){
                    nextButton[i].disabled = false;
                }
            }
        break;
        }
    });
}
