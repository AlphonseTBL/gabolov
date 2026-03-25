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

function activateAdminSection(targetSection) {
    var sections = document.querySelectorAll('.admin-section');
    var links = document.querySelectorAll('.js-admin-nav-link');

    sections.forEach(function (section) {
        section.classList.toggle('is-active', section.dataset.section === targetSection);
    });

    links.forEach(function (link) {
        link.classList.toggle('active', link.dataset.target === targetSection);
    });

    if (targetSection === 'reportes') {
        renderAdminReportCharts();
    }
}

function readJsonScript(scriptId) {
    var scriptEl = document.getElementById(scriptId);
    if (!scriptEl) {
        return [];
    }

    try {
        return JSON.parse(scriptEl.textContent || '[]');
    } catch (error) {
        return [];
    }
}

function renderAdminReportCharts() {
    if (typeof Chart === 'undefined') {
        return;
    }

    var modelCanvas = document.getElementById('adminModelUsageChart');
    var userCanvas = document.getElementById('adminUserUsageChart');
    if (!modelCanvas || !userCanvas) {
        return;
    }

    var topModels = readJsonScript('adminReportTopModelsData');
    var topUsers = readJsonScript('adminReportTopUsersData');

    if (window.adminModelUsageChartInstance) {
        window.adminModelUsageChartInstance.destroy();
    }
    if (window.adminUserUsageChartInstance) {
        window.adminUserUsageChartInstance.destroy();
    }

    window.adminModelUsageChartInstance = new Chart(modelCanvas, {
        type: 'bar',
        data: {
            labels: topModels.map(function (item) { return item.modelo; }),
            datasets: [{
                label: 'Viajes',
                data: topModels.map(function (item) { return item.viajes; }),
                backgroundColor: 'rgba(57, 255, 20, 0.55)',
                borderColor: 'rgba(57, 255, 20, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { ticks: { color: '#d7d7d7' }, grid: { color: 'rgba(255,255,255,0.08)' } },
                y: { beginAtZero: true, ticks: { color: '#d7d7d7' }, grid: { color: 'rgba(255,255,255,0.08)' } }
            },
            plugins: { legend: { labels: { color: '#f1f1f1' } } }
        }
    });

    window.adminUserUsageChartInstance = new Chart(userCanvas, {
        type: 'bar',
        data: {
            labels: topUsers.map(function (item) { return String(item.usuario_id); }),
            datasets: [{
                label: 'Viajes',
                data: topUsers.map(function (item) { return item.viajes; }),
                backgroundColor: 'rgba(35, 166, 213, 0.55)',
                borderColor: 'rgba(35, 166, 213, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { ticks: { color: '#d7d7d7' }, grid: { color: 'rgba(255,255,255,0.08)' } },
                y: { beginAtZero: true, ticks: { color: '#d7d7d7' }, grid: { color: 'rgba(255,255,255,0.08)' } }
            },
            plugins: { legend: { labels: { color: '#f1f1f1' } } }
        }
    });
}

function exportAdminReportToPdf() {
    var reportArea = document.getElementById('adminReportExportArea');
    if (!reportArea || typeof html2canvas === 'undefined' || !window.jspdf || !window.jspdf.jsPDF) {
        alert('No se pudo generar el PDF.');
        return;
    }

    html2canvas(reportArea, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#0b0f19'
    }).then(function (canvas) {
        var imageData = canvas.toDataURL('image/png');
        var jsPDF = window.jspdf.jsPDF;
        var pdf = new jsPDF('p', 'mm', 'a4');
        var pageWidth = pdf.internal.pageSize.getWidth();
        var pageHeight = pdf.internal.pageSize.getHeight();
        var margin = 10;
        var imgWidth = pageWidth - (margin * 2);
        var imgHeight = (canvas.height * imgWidth) / canvas.width;
        var heightLeft = imgHeight;
        var position = margin;

        pdf.addImage(imageData, 'PNG', margin, position, imgWidth, imgHeight);
        heightLeft -= (pageHeight - margin * 2);

        while (heightLeft > 0) {
            position = heightLeft - imgHeight + margin;
            pdf.addPage();
            pdf.addImage(imageData, 'PNG', margin, position, imgWidth, imgHeight);
            heightLeft -= (pageHeight - margin * 2);
        }

        var month = reportArea.dataset.month || 'reporte';
        pdf.save('reporte-uso-' + month + '.pdf');
    });
}

function setAdminSectionInUrl(sectionName) {
    var url = new URL(window.location.href);
    url.searchParams.set('admin_section', sectionName);
    window.history.replaceState({}, '', url.toString());
}

document.addEventListener('click', function (event) {
    var navLink = event.target.closest('.js-admin-nav-link');
    if (!navLink) {
        var exportButton = event.target.closest('#adminReportExportBtn');
        if (exportButton) {
            event.preventDefault();
            exportAdminReportToPdf();
        }
        return;
    }

    event.preventDefault();
    var targetSection = navLink.dataset.target;
    if (!targetSection) {
        return;
    }

    activateAdminSection(targetSection);
    setAdminSectionInUrl(targetSection);
});

(function initAdminSectionState() {
    var sections = document.querySelectorAll('.admin-section');
    if (!sections.length) {
        return;
    }

    var url = new URL(window.location.href);
    var section = url.searchParams.get('admin_section') || 'dashboard';
    activateAdminSection(section);
})();
