document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('newVisitorForm');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    let valid = true;
    const name = form.querySelector('[name="visitor_name"]');
    const purpose = form.querySelector('[name="purpose"]');
    const contact = form.querySelector('[name="contact"]');

    [name, purpose].forEach(clearInvalid);

    if (!name || !name.value.trim()) {
      setInvalid(name, 'Please enter visitor name.');
      valid = false;
    }

    if (!purpose || !purpose.value.trim()) {
      setInvalid(purpose, 'Please select purpose.');
      valid = false;
    }

    if (contact && contact.value) {
      // basic phone validation: allow digits, +, spaces, -, parentheses
      const re = /^[0-9+()\-\s]{4,32}$/;
      if (!re.test(contact.value)) {
        setInvalid(contact, 'Please enter a valid contact number.');
        valid = false;
      }
    }

    if (!valid) e.preventDefault();
  });

  function setInvalid(input, message) {
    if (!input) return;
    input.classList.add('is-invalid');
    let fb = input.nextElementSibling;
    if (!fb || !fb.classList.contains('invalid-feedback')) {
      fb = document.createElement('div');
      fb.className = 'invalid-feedback';
      input.parentNode.insertBefore(fb, input.nextSibling);
    }
    fb.textContent = message;
  }

  function clearInvalid(input) {
    if (!input) return;
    input.classList.remove('is-invalid');
  }
});
