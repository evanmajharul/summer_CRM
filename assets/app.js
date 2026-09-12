document.addEventListener('DOMContentLoaded', () => {
    const worker = document.getElementById('workerSelect');
    const start = document.getElementById('startDate');
    const end = document.getElementById('endDate');
    const hours = document.getElementById('previewHours');
    const earning = document.getElementById('previewEarning');

    function updatePreview() {
        if (!worker || !start || !end || !hours || !earning) return;

        const selected = worker.options[worker.selectedIndex];
        const rate = parseFloat(selected?.dataset?.rate || '0');
        const startValue = new Date(start.value);
        const endValue = new Date(end.value);
        const milliseconds = endValue - startValue;
        const calculatedHours = milliseconds > 0 ? milliseconds / 3600000 : 0;

        hours.textContent = calculatedHours.toFixed(2);
        earning.textContent = (calculatedHours * rate).toFixed(2);
    }

    [worker, start, end].forEach(element => {
        if (element) element.addEventListener('change', updatePreview);
    });

    updatePreview();
});