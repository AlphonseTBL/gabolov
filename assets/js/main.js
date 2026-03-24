function hideLoader() {
    setTimeout(function () {
        var loader = document.getElementById('loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }, 1000);
}

var modalOpeners = {};

if (typeof AOS !== 'undefined') {
    AOS.init({ duration: 1000, once: true });
}

if (typeof particlesJS !== 'undefined') {
    particlesJS('particles-js', {
        particles: {
            number: { value: 60, density: { enable: true, value_area: 800 } },
            color: { value: '#ffffff' },
            shape: { type: 'circle' },
            opacity: { value: 0.6, random: false },
            size: { value: 3, random: true },
            line_linked: {
                enable: true,
                distance: 160,
                color: '#ffffff',
                opacity: 0.45,
                width: 1.5
            },
            move: {
                speed: 1.8,
                direction: 'none',
                random: false,
                straight: false,
                out_mode: 'out',
                bounce: false
            }
        },
        interactivity: {
            detect_on: 'canvas',
            events: { onhover: { enable: true, mode: 'grab' } },
            modes: { grab: { distance: 180, line_linked: { opacity: 0.8 } } }
        }
    });
}

function openModal(modalElement) {
    if (!modalElement) {
        return;
    }

    if (document.activeElement) {
        modalOpeners[modalElement.id] = document.activeElement;
    }

    modalElement.hidden = false;
    modalElement.classList.add('is-open');
    modalElement.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
}

function closeModal(modalElement) {
    if (!modalElement) {
        return;
    }

    if (modalElement.contains(document.activeElement) && document.activeElement) {
        document.activeElement.blur();
    }

    modalElement.classList.remove('is-open');
    modalElement.hidden = true;
    modalElement.setAttribute('aria-hidden', 'true');

    if (!document.querySelector('.bike-modal.is-open')) {
        document.body.classList.remove('modal-open');
    }

    var opener = modalOpeners[modalElement.id];
    if (opener && typeof opener.focus === 'function' && document.contains(opener)) {
        opener.focus();
    }
}

document.addEventListener('click', function (event) {
    var loginOpenTrigger = event.target.closest('.js-login-open');
    if (loginOpenTrigger) {
        event.preventDefault();
        openModal(document.getElementById('loginModal'));
        return;
    }

    var historyOpenTrigger = event.target.closest('.js-history-open');
    if (historyOpenTrigger) {
        event.preventDefault();
        openModal(document.getElementById('historyModal'));
        return;
    }

    var registerOpenTrigger = event.target.closest('.js-register-open');
    if (registerOpenTrigger) {
        event.preventDefault();
        openModal(document.getElementById('registerModal'));
        return;
    }

    var trigger = event.target.closest('.js-bike-trigger');
    var modalElement = document.getElementById('bikeInfoModal');

    if (trigger && modalElement) {
        event.preventDefault();
        var title = document.getElementById('bikeModalTitle');
        var image = document.getElementById('bikeModalImage');
        var unit = document.getElementById('bikeModalUnit');
        var state = document.getElementById('bikeModalState');
        var bikeModelInput = document.getElementById('bikeModalBikeModel');
        var reserveButton = document.getElementById('bikeModalReserveButton');

        var bikeState = trigger.dataset.bikeState || 'Desconocido';
        var isAvailable = bikeState === 'Disponible';
        var bikeModel = trigger.dataset.bikeModel || '';
        var available = trigger.dataset.bikeAvailable || '0';
        var total = trigger.dataset.bikeTotal || '0';

        if (title) {
            title.textContent = trigger.dataset.bikeName || 'Detalle de bicicleta';
        }

        if (image) {
            image.src = trigger.dataset.bikeImage || '';
            image.alt = trigger.dataset.bikeName || 'Imagen de bicicleta';
        }

        if (unit) {
            unit.textContent = 'Unidades disponibles: ' + available + ' de ' + total;
        }

        if (state) {
            state.textContent = 'Estado: ' + bikeState;
        }

        if (bikeModelInput) {
            bikeModelInput.value = bikeModel;
        }

        if (reserveButton) {
            reserveButton.disabled = !isAvailable;
            reserveButton.textContent = isAvailable ? 'Confirmar reserva' : 'Unidad ocupada';
        }

        openModal(modalElement);
        return;
    }

    var closeTrigger = event.target.closest('.js-bike-close, .js-register-close, .js-login-close, .js-history-close');
    if (closeTrigger) {
        event.preventDefault();
        closeModal(closeTrigger.closest('.bike-modal'));
        return;
    }

    if (event.target.classList && event.target.classList.contains('bike-modal')) {
        closeModal(event.target);
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
        return;
    }

    var openedModal = document.querySelector('.bike-modal.is-open');
    if (!openedModal) {
        return;
    }

    closeModal(openedModal);
});
