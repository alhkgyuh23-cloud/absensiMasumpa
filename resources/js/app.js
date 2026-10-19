//
import "./bootstrap";

window.togglePassword = function () {
    const password = document.getElementById("password");
    const eyeOpen = document.getElementById("eye-open");
    const eyeClosed = document.getElementById("eye-closed");

    if (password.type === "password") {
        password.type = "text";

        eyeOpen.classList.add("hidden");
        eyeClosed.classList.remove("hidden");
    } else {
        password.type = "password";

        eyeOpen.classList.remove("hidden");
        eyeClosed.classList.add("hidden");
    }
};
