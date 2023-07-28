// CTED-3085 | Enhancement | Accessibility 2023 | Styling
// Allow users to use the enter key to select radio buttons.
// But also allow enter to work when a button is focused (e.g. submit button).
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById("phpesp_response");
    document.addEventListener("keydown", function(e) {
        if (e.keyCode === 13) {
            if (e.target.type === "submit") {
                form.submit();
            } else {
                e.preventDefault();
            }
        }
    });
}, false);
