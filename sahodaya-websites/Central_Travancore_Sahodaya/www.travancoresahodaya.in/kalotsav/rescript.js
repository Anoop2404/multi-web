let isRecaptchaValidated = false;

function toggleRecaptchaFormMessage(type = "error", hide = false) {
  document.getElementById(`recaptcha-form-${type}`).style.display = hide
    ? "none"
    : "inherit";
}

function onRecaptchaSuccess() {
  isRecaptchaValidated = true;
  document.getElementById("recaptcha-form").submit();
}

function onRecaptchaError() {
  toggleRecaptchaFormMessage("error");
  toggleRecaptchaFormMessage("success", true);
}

function onRecaptchaResponseExpiry() {
  onRecaptchaError();
}

window.onload = function () {
  const recaptchaForm = document.getElementById("recaptcha-form");
  recaptchaForm.addEventListener("submit", function (e) {
    e.preventDefault();

    // failure
    if (!isRecaptchaValidated) {
      toggleRecaptchaFormMessage("error");
      toggleRecaptchaFormMessage("success", true);
      return;
    }

    // success
    toggleRecaptchaFormMessage("error", true);
    toggleRecaptchaFormMessage("success");
    
  });
};
