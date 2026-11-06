/**
 * GigaChefAI — donut-chart КБЖУ на Chart.js
 * initNutritionChart(canvasId, {proteins, fats, carbs})
 */
function initNutritionChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || typeof Chart === 'undefined') return;

    const p = Number(data.proteins) || 0;
    const f = Number(data.fats) || 0;
    const c = Number(data.carbs) || 0;
    if (p + f + c === 0) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Белки', 'Жиры', 'Углеводы'],
            datasets: [{
                data: [p, f, c],
                backgroundColor: ['#6ee7b7', '#fda4af', '#c4b5fd'],
                borderWidth: 4,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16, font: { size: 13, family: 'Nunito' } } },
                tooltip: { callbacks: { label: (c) => `${c.label}: ${c.raw} г` } }
            }
        }
    });
}