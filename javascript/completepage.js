// CTED-3085 | Enhancement | Accessibility 2023 | Styling
// Allow users to use the enter key to select radio buttons.
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('phpesp_response');
    form.addEventListener('keypress', function(e) {
        if (e.keyCode === 13) {
            e.preventDefault();
        }
    });
}, false);
