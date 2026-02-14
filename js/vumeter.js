// ===============================
// VU-METER REALISTA + CONTADOR REAL
// ===============================

let isTransmitting = false;
let currentLevel = 5;
let txStartTime = null; // timestamp de inicio TX
let durationInterval = null;

// ===============================
// Animación suave VU
// ===============================
function smoothRandomStep() {
    const vu = document.getElementById("vumeter");
    if (!vu) return;

    if (!isTransmitting) {
        currentLevel += (5 - currentLevel) * 0.1;
    } else {
        const target = 30 + Math.random() * 50;
        currentLevel += (target - currentLevel) * 0.25;
    }

    currentLevel = Math.max(5, Math.min(95, currentLevel));
    vu.style.width = currentLevel + "%";
}

// ===============================
// Formatear segundos a HH:MM:SS
// ===============================
function formatDuration(seconds) {
    const hrs = String(Math.floor(seconds / 3600)).padStart(2, '0');
    const mins = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
    const secs = String(seconds % 60).padStart(2, '0');
    return `${hrs}:${mins}:${secs}`;
}

// ===============================
// Actualiza contador cada segundo
// ===============================
function startDurationCounter() {
    if (durationInterval) clearInterval(durationInterval);

    durationInterval = setInterval(() => {
        if (!txStartTime) return;

        const now = Date.now();
        const seconds = Math.floor((now - txStartTime) / 1000);

        const txinfo = document.getElementById("tx-info");
        if (txinfo) {
            const dur = formatDuration(seconds);
            txinfo.querySelector(".duracion").textContent = dur;
        }
    }, 1000);
}

// ===============================
// AJAX cada 3 segundos
// ===============================
function actualizarVuMeter() {
    fetch("?ajax=tx")
        .then(r => r.json())
        .then(data => {

            const vu     = document.getElementById("vumeter");
            const txinfo = document.getElementById("tx-info");
            if (!vu || !txinfo) return;

            if (data.active) {

                if (!isTransmitting) {
                    // Nueva transmisión detectada
                    txStartTime = Date.now();
                    startDurationCounter();
                }

                isTransmitting = true;
                vu.classList.add("vu-active");

                txinfo.innerHTML =
                    `🔴 Transmitiendo: <strong>${data.callsign}</strong> 
                     (${data.radioid}) – TG ${data.tg}
                     <br>Duración: <strong class="duracion">00:00:00</strong>`;

            } else {

                isTransmitting = false;
                txStartTime = null;

                if (durationInterval) {
                    clearInterval(durationInterval);
                    durationInterval = null;
                }

                vu.classList.remove("vu-active");
                txinfo.textContent = "No hay transmisión activa.";
            }

        })
        .catch(err => {
            console.error("Error consultando estado TX:", err);
        });
}

// ===============================
// INICIO
// ===============================
setInterval(smoothRandomStep, 250);
setInterval(actualizarVuMeter, 3000); // ahora cada 3 segundos
actualizarVuMeter();
