document.addEventListener('DOMContentLoaded', function () {
  function setInvalid(input, message) {
    if (!input) return;
    input.classList.add('is-invalid');
    const fb = input.nextElementSibling;
    if (fb && fb.classList.contains('invalid-feedback')) fb.textContent = message;
  }

  function clearInvalid(input) {
    if (!input) return;
    input.classList.remove('is-invalid');
  }

  const signinForm = document.getElementById('signinForm');
  const registerForm = document.getElementById('registerForm');

  if (signinForm) {
    signinForm.addEventListener('submit', function (e) {
      let valid = true;
      const username = signinForm.querySelector('[name="username"]');
      const password = signinForm.querySelector('[name="password"]');
      clearInvalid(username);
      clearInvalid(password);

      if (!username || !username.value.trim()) {
        setInvalid(username, 'Please enter your username.');
        valid = false;
      }

      if (!password || password.value.length < 6) {
        setInvalid(password, 'Please enter your password (min 6 characters).');
        valid = false;
      }

      if (!valid) e.preventDefault();
    });
  }

  if (registerForm) {
    registerForm.addEventListener('submit', function (e) {
      let valid = true;
      const username = registerForm.querySelector('[name="username"]');
      const password = registerForm.querySelector('[name="password"]');
      const confirm = registerForm.querySelector('[name="confirm"]');
      clearInvalid(username);
      clearInvalid(password);
      clearInvalid(confirm);

      if (!username || !username.value.trim()) {
        setInvalid(username, 'Please enter a username.');
        valid = false;
      }

      if (!password || password.value.length < 6) {
        setInvalid(password, 'Password must be at least 6 characters.');
        valid = false;
      }

      if (!confirm || password.value !== confirm.value) {
        setInvalid(confirm, 'Passwords do not match.');
        valid = false;
      }

      if (!valid) e.preventDefault();
    });
  }
});
