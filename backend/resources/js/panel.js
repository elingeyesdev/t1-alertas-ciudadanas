// Panel de administración (Blade + Tabler).
// La versión ESM de Tabler activa dropdowns, menú colapsable, alertas y modales.
import { Modal } from '@tabler/core/dist/js/tabler.esm.min.js'

// Login: quita el rojo del campo al volver a escribir
document.querySelectorAll('.login-form .form-control').forEach((input) => {
    input.addEventListener('input', () => input.classList.remove('is-invalid'))
})

// ---------- Confirmación antes de acciones delicadas ----------
// Uso: <form data-confirm="texto" data-confirm-title="¿...?" data-confirm-color="danger|success|primary|warning">
const confirmEl = document.getElementById('confirm-modal')
if (confirmEl) {
    const modal = Modal.getOrCreateInstance(confirmEl)
    const okButton = confirmEl.querySelector('[data-confirm-ok]')
    const status = confirmEl.querySelector('[data-confirm-status]')
    const icon = confirmEl.querySelector('[data-confirm-icon]')
    let pendingForm = null

    document.addEventListener('submit', (event) => {
        const form = event.target
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) return
        event.preventDefault()
        pendingForm = form
        const color = form.dataset.confirmColor || 'danger'
        confirmEl.querySelector('[data-confirm-title]').textContent = form.dataset.confirmTitle || '¿Estás seguro?'
        confirmEl.querySelector('[data-confirm-text]').textContent = form.dataset.confirm
        okButton.className = `btn btn-${color} w-100`
        status.className = `modal-status bg-${color}`
        icon.className = `ti ti-alert-triangle text-${color} fs-1 mb-2 d-block`
        modal.show()
    })

    okButton.addEventListener('click', () => {
        if (!pendingForm) return
        pendingForm.dataset.confirmed = '1'
        okButton.disabled = true
        pendingForm.requestSubmit()
    })
    confirmEl.addEventListener('hidden.bs.modal', () => { okButton.disabled = false })
}

// ---------- Modal "Enviar correo" (usuarios) ----------
const mailModal = document.getElementById('mail-modal')
if (mailModal) {
    mailModal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget
        mailModal.querySelector('[data-mail-form]').action = trigger.dataset.mailAction
        mailModal.querySelector('[data-mail-to]').textContent = trigger.dataset.mailTo
    })
}

// ---------- Formulario de usuario: mostrar campos de autoridad ----------
const userForm = document.querySelector('[data-user-form]')
if (userForm) {
    const fieldset = userForm.querySelector('[data-authority-fields]')
    const sync = () => {
        const selected = userForm.querySelector('[data-role-input]:checked')
        if (!fieldset || !selected) return
        const isAuthority = selected.value === 'autoridad'
        fieldset.hidden = !isAuthority
        fieldset.querySelectorAll('[data-authority-required]').forEach((el) => { el.required = isAuthority })
    }
    userForm.querySelectorAll('[data-role-input]').forEach((el) => el.addEventListener('change', sync))
    sync()
}

// Evita doble envío en formularios normales
document.addEventListener('submit', (event) => {
    const form = event.target
    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get') return
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((b) => { b.disabled = true })
})
