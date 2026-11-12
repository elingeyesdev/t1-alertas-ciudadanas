import '@tabler/core/dist/js/tabler.min.js'
const panelInputs = document.querySelectorAll('.login-form .form-control')
panelInputs.forEach((input) => {
    input.addEventListener('input', () => input.classList.remove('is-invalid'))
})
