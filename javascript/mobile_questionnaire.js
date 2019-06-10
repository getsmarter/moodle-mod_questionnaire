var requiredInputs = [];

(function (t) {

	setTimeout(function() {

		var questionnaireCheck = document.getElementsByClassName('questionnaire-module');
		if(typeof(questionnaireCheck) == 'undefined') {
			return;
		}

        var pageNumArray = document.getElementsByClassName('pagenum-current');
        var pageNum = pageNumArray[pageNumArray.length - 1].innerHTML;
        var button = document.getElementsByClassName('questionnaire submit-button');
        var disableSaveButtonFalse = document.getElementsByClassName('hidden-submit-button-check-false-' + pageNum);
        var allSliders = document.getElementsByClassName('range');
        var nextButton = document.getElementsByClassName('questionnaire next-button');
        var allCheckboxes = document.getElementsByClassName('item-checkbox');
        var allTextBoxes = document.getElementsByClassName('questionnaire-text');
        var backButton = document.getElementsByClassName('back-button');
        var dateTime = document.getElementsByClassName('datetime');
        var select = document.getElementsByClassName('select');
        var input = document.getElementsByClassName('input');

	    window.clicked_input = e => {
	        checkIfFinalRequiredResponse(e);
	    };

	    window.localStorage.setItem('pageNum', pageNum);

	    var checkboxes = document.getElementsByClassName('questionnaire-checkbox-checked-' + pageNum );
	    for (var i = 0; i < checkboxes.length; i++) {
	        checkboxes[i].click();
	    };

	    for (var x = 0; x < backButton.length; x++) {
	        backButton[x].addEventListener('click', onBackButtonClick);
	    }

	    // Setting up observer for sliders that are completed and na applicable.
	    var allNaApplicableSliders = document.getElementsByClassName('na-applicable'); // When a user chooses N/A on slider.
	    if (typeof(allNaApplicableSliders) != 'undefined' && allNaApplicableSliders.length > 0) {
	        var completedSliders = document.getElementsByClassName('na-applicable na-completed');
	        if (typeof(completedSliders) != 'undefined' && completedSliders.length > 0) {
	            for (var i = 1; i < completedSliders.length; i++) {
	                for (var x = 0; x < allSliders.length; x++) {
	                    var naCheck = typeof(allSliders[x].getAttribute('data-na')) != 'undefined';
	                    if (allSliders[x].getAttribute('max') == allSliders[x].getAttribute('ng-reflect-model')) {
	                        completedSliders[i].innerHTML = 'N/A';
	                    }
	                }
	                break;
	            }
	        }

	        var observerOptions = {
	            childList: true,
	            attributes: true,
	            subtree: true, // Omit or set to false to observe only changes to the parent node.
	            characterData: true,
	        }
	        for (var x = 0; x < allNaApplicableSliders.length; x++) {
	            var observer = new MutationObserver(naObserver);
	            observer.observe(allNaApplicableSliders[x], observerOptions);
	        }
	    }

	    // Setting up observer for sliders on page.
	    if (typeof(allSliders) != 'undefined' && allSliders.length > 0) { // NA onload.
	        for(var x = 0; x < allSliders.length; x++) {
	            var counter = 1;
	            // Here I need to check if it is na applicable.
	            var naCheck = allSliders[x].getAttribute('data-na');
	            for (var i = 0; i < allSliders[x].childNodes[1].children.length - 3; i++) {

	                if (naCheck && allSliders[x].getAttribute('max') == (i + 1)) {
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
	            subtree: true, // Omit or set to false to observe only changes to the parent node.
	            characterData: true,
	        }
	        for (var x = 0; x < allSliders.length; x++) {
	            var observer = new MutationObserver(sliderObserver);
	            observer.observe(allSliders[x], observerOptions);
	        }
	    }

	    // Setting up observer for checkboxes.
	    if (typeof(allCheckboxes) != 'undefined' && allCheckboxes.length > 0) { // NA onload.
	        var observerOptions = {
	            attributes: true,
	            characterData: true,
	        }
	        for (var x = 0; x < allCheckboxes.length; x++) {
	            var observer = new MutationObserver(checkboxObserver);
	            observer.observe(allCheckboxes[x], observerOptions);
	        }
	    }

	    // Setting up observer for textboxes.
	    if (typeof(allTextBoxes) != 'undefined' && allTextBoxes.length > 0) { // NA onload.

	        var observerOptions = {
	            childList: true,
	            attributes: true,
	            subtree: true, // Omit or set to false to observe only changes to the parent node.
	            characterData: true,
	        }
	        for (var x = 0; x < allTextBoxes.length; x++) {
	            var observer = new MutationObserver(textBoxObserver);
	            observer.observe(allTextBoxes[x], observerOptions);
	        }
	    }

	    // Setting up observer for datetime.
		if (typeof(dateTime) != 'undefined' && dateTime.length > 0) { // NA onload.

	        var observerOptions = {
	            attributes: true
	        }
	        for (var x = 0; x < dateTime.length; x++) {
	            var observer = new MutationObserver(dateTimeObserver);
	            observer.observe(dateTime[x], observerOptions);
	        }
	    }

        // Setting up observer for select.
        if (typeof(select) != 'undefined' && select.length > 0) { // NA onload.

            var observerOptions = {
                attributes: true
            }
            for (var x = 0; x < select.length; x++) {
                var observer = new MutationObserver(selectObserver);
                observer.observe(select[x], observerOptions);
            }
        }

        // Setting up observer for input.
        if (typeof(input) != 'undefined' && input.length > 0) { // NA onload.

            var observerOptions = {
                attributes: true
            }
            for (var x = 0; x < input.length; x++) {
                var observer = new MutationObserver(inputObserver);
                observer.observe(input[x], observerOptions);
            }
        }	    

	    if (typeof(button) != 'undefined' && disableSaveButtonFalse.length == 0) { // Basic idea behind the validation for the button hiding logic, using disabled for now since it's an option in ionic.
	        for (var x = 0; x < button.length; x++) {
	            button[x].disabled = true;
	        }
	    }

	    if (typeof(nextButton) != 'undefined' && nextButton.length > 0 && disableSaveButtonFalse.length == 0) {
	        for (var x = 0; x < nextButton.length; x++) {
	            nextButton[x].disabled = true;
	        }
	    }

	}, 300);

})(this);

function checkIfFinalRequiredResponse (e) {
    if (!requiredInputs.includes(e[0])) {
        requiredInputs.push(e[0]); // Only push if it has not been added to the array already.
    }

    var finalRequiredAnswer = e[1];
    var numberOfRequiredAnswers = 0;
    for (var x = 0; x < requiredInputs.length; x++) {
        // First need to check that all answers before required answer are in array.
        // Then set a flag that I can check later.
        numberOfRequiredAnswers++;
    }

    var requiredInput = false;
    if (requiredInputs.includes(e[1])) {
        requiredInput = true;
    }

    var button = document.getElementsByClassName('questionnaire submit-button');
    var nextButton = document.getElementsByClassName('questionnaire next-button');
    if (requiredInput === true && numberOfRequiredAnswers == finalRequiredAnswer && typeof(button) != 'undefined') {
        for (var i = 0; i < button.length; i++) {
            button[i].disabled = false;
        }
    }
    if (requiredInput === true && numberOfRequiredAnswers == finalRequiredAnswer && typeof(nextButton) != 'undefined') {
        for (var i = 0; i < nextButton.length; i++) {
            nextButton[i].disabled = false;
        }
    }
}

function naObserver(mutationList, observer) {
    mutationList.forEach((mutation) => {
        switch (mutation.type) {
            case 'characterData':
                var restingNaValue = mutation.target.parentElement.getAttribute('data-final');
                var currentNaValue = mutation.target.data;
                if (currentNaValue == restingNaValue) {
                    mutation.target.data = 'n/a';
                }
                break;
        }
    });
}

function sliderObserver(mutationList, observer) {
    mutationList.forEach((mutation) => {
        switch (mutation.type) {
            case 'characterData':
                var currentRequiredValue = mutation.target.parentElement.parentElement.parentElement.parentElement.getAttribute('data-currentinput');
                var finalRequiredInput = mutation.target.parentElement.parentElement.parentElement.parentElement.getAttribute('data-finalinput');

                if (!requiredInputs.includes(currentRequiredValue)) {
                    requiredInputs.push(currentRequiredValue); // Only push if it has not been added to the array already.
                }

                var numberOfRequiredAnswers = 0;
                for (var x = 0; x < requiredInputs.length; x++) {
                    // First need to check that all answers before required answer are in array.
                    // Then set a flag that I can check later.
                    numberOfRequiredAnswers++;
                }

                var requiredInput = false;
                if (requiredInputs.includes(finalRequiredInput)) {
                    requiredInput = true;
                }

                var button = document.getElementsByClassName('questionnaire submit-button');
                var nextButton = document.getElementsByClassName('questionnaire next-button');

                if (requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(button) != 'undefined') {
                    for(var x = 0; x < button.length; x++) {
                        button[x].disabled = false;
                    }
                }
                if (requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(nextButton) != 'undefined') {
                    for (var i = 0; i < nextButton.length; i++) {
                        nextButton[i].disabled = false;
                    }
                }
            break;
        }
    });
}

function checkboxObserver(mutationList, observer) {
    mutationList.forEach((mutation) => {
        switch (mutation.type) {
            case 'attributes':
                var currentRequiredValue = mutation.target.childNodes[0].getAttribute('data-currentinput');
                var finalRequiredInput = mutation.target.childNodes[0].getAttribute('data-finalinput');
                var pageNumArray = document.getElementsByClassName('pagenum-current');
                var pageNum = window.localStorage.getItem('pageNum');

                if (typeof(mutation.target.childNodes[0].childNodes[1]) == undefined) {
                    return;
                }

                try {
                    var ariaCheck = mutation.target.childNodes[0].childNodes[1].getAttribute('aria-checked');
                    if (ariaCheck == 'true') {
                        if (!mutation.target.childNodes[0].classList.contains('questionnaire-checkbox-checked-' + pageNum)) {
                            mutation.target.childNodes[0].classList.add('questionnaire-checkbox-checked-' + pageNum );
                        }
                    } else if (ariaCheck == 'false') {
                        if (mutation.target.childNodes[0].classList.contains('questionnaire-checkbox-checked-' + pageNum)) {
                            mutation.target.childNodes[0].classList.remove('questionnaire-checkbox-checked-' + pageNum );
                        }
                    }
                } catch (err) {}

                if (!requiredInputs.includes(currentRequiredValue) && currentRequiredValue) {
                    requiredInputs.push(currentRequiredValue); // Only push if it has not been added to the array already.
                }

                var button = document.getElementsByClassName('questionnaire submit-button');
                var nextButton = document.getElementsByClassName('questionnaire next-button');
                var numberOfRequiredAnswers = 0;
                for (var x = 0; x < requiredInputs.length; x++) {
                    // First need to check that all answers before required answer are in array.
                    // Then set a flag that I can check later.
                    numberOfRequiredAnswers++;
                }

                var requiredInput = false;
                if (requiredInputs.includes(finalRequiredInput)) {
                    requiredInput = true;
                }

                if (requiredInput == true && numberOfRequiredAnswers == finalRequiredInput ) {
                    for (var x = 0; x < button.length; x++) {
                        button[x].disabled = false;
                    }
                }
                if (requiredInput == true && numberOfRequiredAnswers == finalRequiredInput ) {
                    for (var i = 0; i < nextButton.length; i++){
                        nextButton[i].disabled = false;
                    }
                }

                // Last check here.
                var checkedCheckboxes = document.getElementsByClassName('questionnaire-checkbox-checked-' + pageNum );
                if (checkedCheckboxes.length == 0) {
                    for (var x = 0; x < button.length; x++) {
                        button[x].disabled = true;
                    }

                    for (var i = 0; i < nextButton.length; i++) {
                        nextButton[i].disabled = true;
                    }
                }
            break;
        }
    });
}

function textBoxObserver(mutationList, observer) {
    mutationList.forEach((mutation) => {
        switch (mutation.type) {
            case 'attributes':

                var currentRequiredValue = mutation.target.parentElement.getAttribute('data-currentinput');
                var finalRequiredInput = mutation.target.parentElement.getAttribute('data-finalinput');
                var button = document.getElementsByClassName('questionnaire submit-button');

                if (!currentRequiredValue && !finalRequiredInput) {
                    return;
                }

                if (mutation.target.value == '') {
                    for (var x = 0; x < button.length; x++) {
                        button[x].disabled = true;
                    }
                    return;
                }

                if (!requiredInputs.includes(currentRequiredValue)) {
                    requiredInputs.push(currentRequiredValue); // Only push if it has not been added to the array already.
                }

                var numberOfRequiredAnswers = 0;
                for (var x = 0; x < requiredInputs.length; x++) {
                    // First need to check that all answers before required answer are in array.
                    // Then set a flag that I can check later.
                    numberOfRequiredAnswers++;
                }

                var requiredInput = false;
                if (requiredInputs.includes(finalRequiredInput)) {
                    requiredInput = true;
                }

                var nextButton = document.getElementsByClassName('questionnaire next-button');

                if (requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(button) != 'undefined') {
                    for (var x = 0; x < button.length; x++) {
                        button[x].disabled = false;
                    }
                }
                if (requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(nextButton) != 'undefined') {
                    for (var i = 0; i < nextButton.length; i++){
                        nextButton[i].disabled = false;
                    }
                }
            break;
        }
    });
}

function dateTimeObserver(mutationList, observer) {
    mutationList.forEach((mutation) => {

        switch (mutation.type) {
            case 'attributes':

                var currentRequiredValue = mutation.target.getAttribute('data-currentinput');
                var finalRequiredInput = mutation.target.getAttribute('data-finalinput');
                var button = document.getElementsByClassName('questionnaire submit-button');

                if (!currentRequiredValue && !finalRequiredInput) {
                    return;
                }

                if (mutation.target.innerText == '') {
                    for (var x = 0; x < button.length; x++) {
                        button[x].disabled = true;
                    }
                    return;
                }
                // Might need to build a check function to make sure that the date time adheres to certain policies

                if (!requiredInputs.includes(currentRequiredValue)) {
                    requiredInputs.push(currentRequiredValue); // Only push if it has not been added to the array already.
                }

                var numberOfRequiredAnswers = 0;
                for (var x = 0; x < requiredInputs.length; x++) {
                    // First need to check that all answers before required answer are in array.
                    // Then set a flag that I can check later.
                    numberOfRequiredAnswers++;
                }

                var requiredInput = false;
                if (requiredInputs.includes(finalRequiredInput)) {
                    requiredInput = true;
                }

                var nextButton = document.getElementsByClassName('questionnaire next-button');

                if (requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(button) != 'undefined') {
                    for (var x = 0; x < button.length; x++) {
                        button[x].disabled = false;
                    }
                }
                if (requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(nextButton) != 'undefined') {
                    for (var i = 0; i < nextButton.length; i++){
                        nextButton[i].disabled = false;
                    }
                }
            break;
        }
    });
}

function selectObserver(mutationList, observer) {
    mutationList.forEach((mutation) => {

        switch (mutation.type) {
            case 'attributes':

                var currentRequiredValue = mutation.target.getAttribute('data-currentinput');
                var finalRequiredInput = mutation.target.getAttribute('data-finalinput');
                var button = document.getElementsByClassName('questionnaire submit-button');

                if (!currentRequiredValue && !finalRequiredInput) {
                    return;
                }

                // Not inner text need to change to actual data input here and not inner text
                if (mutation.target.innerText == '') {
                    for (var x = 0; x < button.length; x++) {
                        button[x].disabled = true;
                    }
                    return;
                }
                
                if (!requiredInputs.includes(currentRequiredValue)) {
                    requiredInputs.push(currentRequiredValue); // Only push if it has not been added to the array already.
                }

                var numberOfRequiredAnswers = 0;
                for (var x = 0; x < requiredInputs.length; x++) {
                    // First need to check that all answers before required answer are in array.
                    // Then set a flag that I can check later.
                    numberOfRequiredAnswers++;
                }

                var requiredInput = false;
                if (requiredInputs.includes(finalRequiredInput)) {
                    requiredInput = true;
                }

                var nextButton = document.getElementsByClassName('questionnaire next-button');

                if (requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(button) != 'undefined') {
                    for (var x = 0; x < button.length; x++) {
                        button[x].disabled = false;
                    }
                }
                if (requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(nextButton) != 'undefined') {
                    for (var i = 0; i < nextButton.length; i++){
                        nextButton[i].disabled = false;
                    }
                }
            break;
        }
    });
}

function inputObserver(mutationList, observer) {
    mutationList.forEach((mutation) => {

        switch (mutation.type) {
            case 'attributes':

                var currentRequiredValue = mutation.target.getAttribute('data-currentinput');
                var finalRequiredInput = mutation.target.getAttribute('data-finalinput');
                var button = document.getElementsByClassName('questionnaire submit-button');

                if (!currentRequiredValue && !finalRequiredInput) {
                    return;
                }

                // Need to run this through a text checker before allowing a submission
                if (mutation.target.children[0].value == '') {
                    for (var x = 0; x < button.length; x++) {
                        button[x].disabled = true;
                    }
                    return;
                }

                if (!requiredInputs.includes(currentRequiredValue)) {
                    requiredInputs.push(currentRequiredValue); // Only push if it has not been added to the array already.
                }

                var numberOfRequiredAnswers = 0;
                for (var x = 0; x < requiredInputs.length; x++) {
                    // First need to check that all answers before required answer are in array.
                    // Then set a flag that I can check later.
                    numberOfRequiredAnswers++;
                }

                var requiredInput = false;
                if (requiredInputs.includes(finalRequiredInput)) {
                    requiredInput = true;
                }

                var nextButton = document.getElementsByClassName('questionnaire next-button');

                if (requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(button) != 'undefined') {
                    for (var x = 0; x < button.length; x++) {
                        button[x].disabled = false;
                    }
                }
                if (requiredInput === true && numberOfRequiredAnswers == finalRequiredInput && typeof(nextButton) != 'undefined') {
                    for (var i = 0; i < nextButton.length; i++){
                        nextButton[i].disabled = false;
                    }
                }
            break;
        }
    });
}

function onBackButtonClick() {
    var pageNumber = window.localStorage.getItem('pageNum');
    pageNumber--;
    window.localStorage.setItem('pageNum', pageNumber);
}
