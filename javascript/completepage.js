// CTED-3085 | Enhancement | Accessibility 2023 | Styling
// Allow users to use the enter key to select radio buttons.
// But also allow enter key to work when a button (or link) is focused (e.g. submit button).
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById("phpesp_response");
    document.addEventListener("keydown", function(e) {
        if (e.keyCode === 13) {
            if (e.target.type === "submit") {
                form.submit();
           } else if (e.srcElement.contains("<a ")) {
                // Allow link clicks to also be actionable by enter key.
            } else {
                e.preventDefault();
            }
        }
    });
}, false);
